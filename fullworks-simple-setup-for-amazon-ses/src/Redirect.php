<?php

namespace Fullworks\SimpleSetupForAmazonSes;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the "redirect all mail to a catch-all address" settings.
 *
 * When active, every outgoing email handled by MailHandler is sent for real
 * through Amazon SES, but its recipients are rewritten to a fixed catch-all
 * list so that no real customer receives staging/test mail. The original
 * recipients are preserved in X-Original-* headers.
 *
 * Like Credentials, settings are resolved from PHP constants first (intended
 * for wp-config.php, typically bridged from environment variables) and then
 * from DB options:
 *
 *     define( 'FSSFAS_REDIRECT_MODE', getenv( 'FSSFAS_REDIRECT_MODE' ) ?: 'non_production' );
 *     define( 'FSSFAS_REDIRECT_TO', getenv( 'FSSFAS_REDIRECT_TO' ) ?: 'staging-mail@example.com' );
 */
class Redirect {

	const CONST_MODE = 'FSSFAS_REDIRECT_MODE';
	const CONST_TO   = 'FSSFAS_REDIRECT_TO';

	const MODE_NEVER          = 'never';
	const MODE_NON_PRODUCTION = 'non_production';
	const MODE_ALWAYS         = 'always';

	/**
	 * Allowed redirect modes.
	 *
	 * @return string[]
	 */
	public static function modes() {
		return array( self::MODE_NEVER, self::MODE_NON_PRODUCTION, self::MODE_ALWAYS );
	}

	/**
	 * The configured redirect mode.
	 *
	 * @return string One of the MODE_* constants. Defaults to MODE_NEVER.
	 */
	public static function mode() {
		$mode = self::resolve( self::CONST_MODE, 'redirect_mode', self::MODE_NEVER );

		return in_array( $mode, self::modes(), true ) ? $mode : self::MODE_NEVER;
	}

	/**
	 * The configured catch-all recipients.
	 *
	 * @return string[] Valid email addresses; empty when none configured.
	 */
	public static function addresses() {
		$raw = self::resolve( self::CONST_TO, 'redirect_to', '' );

		$addresses = array();
		foreach ( explode( ',', (string) $raw ) as $entry ) {
			$entry = trim( $entry );
			if ( '' !== $entry && is_email( $entry ) ) {
				$addresses[] = $entry;
			}
		}

		return array_values( array_unique( $addresses ) );
	}

	/**
	 * Whether redirection should currently rewrite recipients.
	 *
	 * Requires both an active mode and at least one valid catch-all address.
	 *
	 * @return bool
	 */
	public static function isActive() {
		if ( empty( self::addresses() ) ) {
			return false;
		}

		switch ( self::mode() ) {
			case self::MODE_ALWAYS:
				return true;
			case self::MODE_NON_PRODUCTION:
				return self::environment() !== 'production';
			default:
				return false;
		}
	}

	/**
	 * Whether a mode is selected but no usable catch-all address is configured.
	 *
	 * Used to surface a misconfiguration warning: in this state mail is NOT
	 * redirected and will be delivered to its real recipients.
	 *
	 * @return bool
	 */
	public static function isMisconfigured() {
		return self::mode() !== self::MODE_NEVER && empty( self::addresses() );
	}

	public static function isModeDefined() {
		return self::isDefined( self::CONST_MODE );
	}

	public static function isToDefined() {
		return self::isDefined( self::CONST_TO );
	}

	/**
	 * Current WordPress environment type.
	 *
	 * @return string
	 */
	private static function environment() {
		return function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	}

	private static function resolve( $constant, $option_key, $default ) {
		if ( self::isDefined( $constant ) ) {
			return (string) constant( $constant );
		}

		$options = get_option( 'fssfas_settings' );
		if ( is_array( $options ) && isset( $options[ $option_key ] ) && $options[ $option_key ] !== '' ) {
			return $options[ $option_key ];
		}

		return $default;
	}

	private static function isDefined( $constant ) {
		return defined( $constant ) && constant( $constant ) !== '';
	}
}
