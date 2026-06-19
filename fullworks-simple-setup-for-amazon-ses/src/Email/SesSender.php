<?php

namespace Fullworks\SimpleSetupForAmazonSes\Email;

defined( 'ABSPATH' ) || exit;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Fullworks\SimpleSetupForAmazonSes\Credentials;

class SesSender {

	private $client;
	private $options;
	private $lastError = '';

	public function __construct() {
		$this->options = get_option( 'fssfas_settings' );
		$this->initializeClient();
	}

	public function getLastError() {
		return $this->lastError;
	}

	private function initializeClient() {
		if ( ! Credentials::isConfigured() ) {
			$this->lastError = 'AWS credentials are not configured.';
			return;
		}

		try {
			$this->client = new SesClient(
				array(
					'version'     => 'latest',
					'region'      => Credentials::region(),
					'credentials' => array(
						'key'    => Credentials::accessKey(),
						'secret' => Credentials::secretKey(),
					),
				)
			);
		} catch ( \Exception $e ) {
			$this->lastError = 'SES client init failed: ' . $e->getMessage();
			$this->logDebug( $this->lastError );
		}
	}

	/**
	 * Send an email through Amazon SES.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Subject line.
	 * @param string       $message     Message body.
	 * @param string|array $headers     Additional headers.
	 * @param array        $attachments Attachment paths.
	 * @param string[]|null $redirect_to When a non-empty array, recipients are
	 *                                   rewritten to these catch-all addresses
	 *                                   and the originals preserved in
	 *                                   X-Original-* headers. Null disables it.
	 * @return bool
	 */
	public function send( $to, $subject, $message, $headers = '', $attachments = array(), $redirect_to = null ) {
		if ( ! $this->client ) {
			if ( '' === $this->lastError ) {
				$this->lastError = 'SES client not initialized.';
			}
			return false;
		}

		$raw_message = $this->buildRawMessage( $to, $subject, $message, $headers, $attachments, $redirect_to );
		if ( null === $raw_message ) {
			// $this->lastError already set by buildRawMessage().
			return false;
		}

		try {
			$result = $this->client->sendRawEmail(
				array(
					'RawMessage' => array(
						'Data' => $raw_message,
					),
				)
			);

			$this->lastError = '';
			$this->logDebug( 'sendRawEmail succeeded. MessageId=' . $result->get( 'MessageId' ) );
			return true;
		} catch ( AwsException $e ) {
			$this->lastError = sprintf(
				'[%s] %s (request id: %s)',
				$e->getAwsErrorCode() ?: 'unknown',
				$e->getAwsErrorMessage() ?: $e->getMessage(),
				$e->getAwsRequestId() ?: 'n/a'
			);
			$this->logDebug( 'sendRawEmail failed: ' . $this->lastError );
			return false;
		} catch ( \Exception $e ) {
			$this->lastError = $e->getMessage();
			$this->logDebug( 'sendRawEmail error: ' . $this->lastError );
			return false;
		}
	}

	/**
	 * Build the complete RFC 5322 message using WordPress' bundled PHPMailer.
	 *
	 * PHPMailer validates every address and strips CR/LF from header values, so
	 * untrusted input that reaches wp_mail() cannot be used to inject extra
	 * headers into the message handed to Amazon SES.
	 *
	 * @param string[]|null $redirect_to Catch-all recipients, or null.
	 * @return string|null The raw MIME message, or null on failure.
	 */
	private function buildRawMessage( $to, $subject, $message, $headers, $attachments, $redirect_to = null ) {
		$parsed = $this->parseHeaders( $headers );

		$from_email = $parsed['from_email'] ?? $this->options['from_email'] ?? get_option( 'admin_email' );
		$from_name  = $parsed['from_name'] ?? $this->options['from_name'] ?? get_bloginfo( 'name' );

		/*
		 * Load WordPress' bundled PHPMailer.
		 *
		 * The plugin handbook permits require_once of a core file when a
		 * function/class from that file is used immediately afterwards:
		 *   https://developer.wordpress.org/plugins/wordpress-org/common-issues/#calling-files-directly
		 *
		 * That exception applies here, and the require_once pattern below is
		 * the same one core uses inside wp_mail() in wp-includes/pluggable.php.
		 *
		 * We must load PHPMailer ourselves because this code path runs from
		 * the pre_wp_mail filter, which fires BEFORE wp_mail() reaches its own
		 * PHPMailer require. When we intercept successfully wp_mail() short-
		 * circuits and never executes those lines, so the class is unavailable
		 * on the first send of a request unless we load it here. WordPress
		 * exposes no public function that loads PHPMailer in isolation.
		 *
		 * PHPMailer is instantiated on the very next line to validate addresses
		 * and strip CR/LF from header values, defending against header-injection
		 * via wp_mail() arguments — see the docblock above. Exception.php is
		 * deliberately loaded separately, immediately before the try block
		 * whose catch arms reference \PHPMailer\PHPMailer\Exception.
		 */
		if ( ! class_exists( \PHPMailer\PHPMailer\PHPMailer::class, false ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		}
		$mail          = new \PHPMailer\PHPMailer\PHPMailer( true );
		$mail->CharSet = 'UTF-8';

		/*
		 * This is the latest position PHP syntax permits this require to sit
		 * before the catch arms below resolve \PHPMailer\PHPMailer\Exception.
		 * PHP forbids statements between try { ... } and its catch, and the
		 * Exception class must already be loaded when any PHPMailer call
		 * inside the try block throws — so this line is the closest we can
		 * place the require to its first use.
		 */
		if ( ! class_exists( \PHPMailer\PHPMailer\Exception::class, false ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		}
		try {
			$mail->setFrom( $from_email, $from_name );

			$redirecting = is_array( $redirect_to ) && ! empty( $redirect_to );

			if ( $redirecting ) {
				// Preserve the intended recipients for inspection, then deliver
				// only to the catch-all list. CC and BCC are dropped from
				// delivery entirely — kept solely in the X-Original-* headers.
				$this->addOriginalRecipientHeader( $mail, 'X-Original-To', $to );
				if ( ! empty( $parsed['cc'] ) ) {
					$this->addOriginalRecipientHeader( $mail, 'X-Original-Cc', $parsed['cc'] );
				}
				if ( ! empty( $parsed['bcc'] ) ) {
					$this->addOriginalRecipientHeader( $mail, 'X-Original-Bcc', $parsed['bcc'] );
				}

				$this->addRecipients( $mail, 'addAddress', $redirect_to );

				$original_label = $this->flattenRecipients( $to );

				// Always log redirected sends so trapped mail is never silent.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Fullworks SES] Mail redirected: original recipient(s) "%s" -> catch-all "%s".',
						'' !== $original_label ? $original_label : '(none)',
						implode( ', ', $redirect_to )
					)
				);

				if ( '' !== $original_label ) {
					/* translators: %1$s: original recipient(s); %2$s: original subject. */
					$subject = sprintf( '[SES redirected → %1$s] %2$s', $original_label, $subject );
				}
			} else {
				$this->addRecipients( $mail, 'addAddress', $to );
				if ( ! empty( $parsed['cc'] ) ) {
					$this->addRecipients( $mail, 'addCC', $parsed['cc'] );
				}
				if ( ! empty( $parsed['bcc'] ) ) {
					$this->addRecipients( $mail, 'addBCC', $parsed['bcc'] );
				}
			}

			if ( ! empty( $parsed['reply_to'] ) ) {
				$this->addRecipients( $mail, 'addReplyTo', $parsed['reply_to'] );
			}

			$mail->Subject = $subject;

			$content_type = $parsed['content_type'] ?? 'text/plain';
			$is_html      = stripos( $content_type, 'text/html' ) !== false || $this->looksLikeHtml( $message );
			$mail->isHTML( $is_html );
			$mail->Body = $message;

			foreach ( (array) $attachments as $name => $path ) {
				if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
					continue;
				}
				try {
					$mail->addAttachment( $path, is_string( $name ) ? $name : '' );
				} catch ( \PHPMailer\PHPMailer\Exception $e ) {
					$this->logDebug( 'Skipped attachment: ' . $e->getMessage() );
				}
			}

			$mail->preSend();
			return $mail->getSentMIMEMessage();
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$this->lastError = 'Failed to build email message: ' . $e->getMessage();
			$this->logDebug( $this->lastError );
			return null;
		}
	}

	/**
	 * Add one or more addresses to the PHPMailer instance.
	 *
	 * Accepts a comma-separated string or an array, and parses the
	 * "Name <address>" form the same way wp_mail() does. Invalid addresses are
	 * skipped rather than aborting the whole message.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mail   PHPMailer instance.
	 * @param string                         $method addAddress|addCC|addBCC|addReplyTo.
	 * @param string|array                    $value  Address(es).
	 */
	private function addRecipients( $mail, $method, $value ) {
		$value = is_array( $value ) ? implode( ',', $value ) : (string) $value;

		foreach ( explode( ',', $value ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}

			$name = '';
			if ( preg_match( '/(.*)<(.+)>/', $entry, $matches ) && count( $matches ) === 3 ) {
				$name  = trim( $matches[1] );
				$entry = trim( $matches[2] );
			}

			try {
				$mail->{$method}( $entry, $name );
			} catch ( \PHPMailer\PHPMailer\Exception $e ) {
				$this->logDebug( 'Skipped invalid recipient: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Flatten a recipient value (string or array) to a single, clean,
	 * comma-separated string with any CR/LF removed.
	 *
	 * @param string|array $value Recipient(s).
	 * @return string
	 */
	private function flattenRecipients( $value ) {
		$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		$value = str_replace( array( "\r", "\n" ), '', $value );

		return trim( $value );
	}

	/**
	 * Record the original recipients in a custom header before they are
	 * rewritten for redirect delivery.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mail  PHPMailer instance.
	 * @param string                         $name  Header name (e.g. X-Original-To).
	 * @param string|array                   $value Original recipient(s).
	 */
	private function addOriginalRecipientHeader( $mail, $name, $value ) {
		$flat = $this->flattenRecipients( $value );
		if ( '' === $flat ) {
			return;
		}

		try {
			$mail->addCustomHeader( $name, $flat );
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$this->logDebug( 'Skipped original-recipient header: ' . $e->getMessage() );
		}
	}

	private function logDebug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Fullworks SES] ' . $message );
		}
	}

	private function parseHeaders( $headers ) {
		$parsed = array();

		if ( empty( $headers ) ) {
			return $parsed;
		}

		// Convert headers to array if string.
		if ( is_string( $headers ) ) {
			$headers = explode( "\n", $headers );
		}

		foreach ( $headers as $header ) {
			if ( strpos( $header, ':' ) === false ) {
				continue;
			}

			list($name, $value) = explode( ':', $header, 2 );
			$name               = strtolower( trim( $name ) );
			$value              = trim( $value );

			switch ( $name ) {
				case 'from':
					if ( preg_match( '/(.+?)\s*<(.+?)>/', $value, $matches ) ) {
						$parsed['from_name']  = trim( $matches[1], '"\'' );
						$parsed['from_email'] = $matches[2];
					} else {
						$parsed['from_email'] = $value;
					}
					break;

				case 'reply-to':
					$parsed['reply_to'] = array( $value );
					break;

				case 'cc':
					$parsed['cc'] = array_map( 'trim', explode( ',', $value ) );
					break;

				case 'bcc':
					$parsed['bcc'] = array_map( 'trim', explode( ',', $value ) );
					break;

				case 'content-type':
					$parsed['content_type'] = $value;
					break;
			}
		}

		return $parsed;
	}

	/**
	 * Check if message content looks like HTML.
	 *
	 * @param string $message The message content to check.
	 * @return bool True if the message appears to contain HTML.
	 */
	private function looksLikeHtml( $message ) {
		// Check for common HTML tags (case-insensitive).
		$html_pattern = '/<(html|body|div|p|br|span|a|strong|em|b|i|u|h[1-6]|ul|ol|li|table|tr|td|th|img|head|style|script)[>\s\/]/i';
		return (bool) preg_match( $html_pattern, $message );
	}
}
