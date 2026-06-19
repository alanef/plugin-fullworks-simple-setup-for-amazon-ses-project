<?php

namespace Fullworks\SimpleSetupForAmazonSes\Email;

defined( 'ABSPATH' ) || exit;

use Fullworks\SimpleSetupForAmazonSes\Credentials;
use Fullworks\SimpleSetupForAmazonSes\Redirect;

class MailHandler {

	private $sesSender;

	public function __construct() {
		add_filter( 'pre_wp_mail', array( $this, 'interceptMail' ), 10, 2 );
	}

	public function interceptMail( $null, $args ) {
		// If not configured, let WordPress handle it normally
		if ( ! Credentials::isConfigured() ) {
			return $null;
		}

		// Initialize SES sender
		if ( ! $this->sesSender ) {
			$this->sesSender = new SesSender();
		}

		// Extract mail arguments
		$to          = $args['to'];
		$subject     = $args['subject'];
		$message     = $args['message'];
		$headers     = isset( $args['headers'] ) ? $args['headers'] : '';
		$attachments = isset( $args['attachments'] ) ? $args['attachments'] : array();

		// When redirect mode is active, rewrite recipients to the catch-all list
		// so no real recipient is mailed. Passing null leaves recipients intact.
		$redirect_to = Redirect::isActive() ? Redirect::addresses() : null;

		// Send via SES
		$sent = $this->sesSender->send( $to, $subject, $message, $headers, $attachments, $redirect_to );

		// If sent successfully, prevent WordPress from sending again
		if ( $sent ) {
			// Return true to stop WordPress from processing the email
			return true;
		}

		// If failed, log the reason and let WordPress try its normal method.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$err = $this->sesSender->getLastError();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Fullworks SES] SES send failed, falling back to WordPress default mail. ' . $err );
		}

		return $null;
	}
}
