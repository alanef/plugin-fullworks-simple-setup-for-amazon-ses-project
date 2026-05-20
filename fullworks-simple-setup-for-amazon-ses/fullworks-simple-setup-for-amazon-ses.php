<?php
/**
 * Plugin Name: Fullworks Simple Setup for Amazon SES
 * Plugin URI: https://fullworksplugins.com/products/fullworks-simple-setup-for-amazon-ses
 * Description: Send WordPress emails through Amazon SES (Simple Email Service).
 * Version: 1.3.3
 * Requires at least: 5.0
 * Requires PHP: 8.2
 * Author: Fullworks
 * Author URI: https://fullworksplugins.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fullworks-simple-setup-for-amazon-ses
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'FSSFAS_VERSION', '1.3.3' );
define( 'FSSFAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSSFAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( FSSFAS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once FSSFAS_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize the plugin
add_action(
	'plugins_loaded',
	function () {
		\Fullworks\SimpleSetupForAmazonSes\Plugin::getInstance();
	}
);

// Activation hook
register_activation_hook(
	__FILE__,
	function () {
		// Add default options
		add_option(
			'fssfas_settings',
			array(
				'aws_access_key' => '',
				'aws_secret_key' => '',
				'aws_region'     => 'us-east-1',
				'from_email'     => get_option( 'admin_email' ),
				'from_name'      => get_bloginfo( 'name' ),
			)
		);
	}
);

// Deactivation hook
register_deactivation_hook(
	__FILE__,
	function () {
		// Clean up if necessary
	}
);
