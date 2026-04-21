<?php
/**
 * Plugin Name: WP Easy Redirect
 * Plugin URI:  https://github.com/wp-easy-redirect
 * Description: Redirect your entire site or individual URLs from within WordPress — no hosting access required.
 * Version:     1.0.0
 * Author:      WP Easy Redirect
 * License:     GPL-2.0+
 * Text Domain: wp-easy-redirect
 *
 * WP Easy Redirect is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'WER_VERSION', '1.0.0' );
define( 'WER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WER_OPTION_KEY', 'wer_settings' );
define( 'WER_REDIRECTS_KEY', 'wer_redirects' );

// ─── Boot ─────────────────────────────────────────────────────────────────────
require_once WER_PLUGIN_DIR . 'includes/class-wer-settings.php';
require_once WER_PLUGIN_DIR . 'includes/class-wer-redirect.php';
require_once WER_PLUGIN_DIR . 'includes/class-wer-admin-page.php';

/**
 * Initialise the plugin.
 */
function wer_init() {
	// Load the redirect logic on every request (front-end only handled inside the class).
	WER_Redirect::instance();

	// Admin UI.
	if ( is_admin() ) {
		WER_Admin_Page::instance();
	}
}
add_action( 'plugins_loaded', 'wer_init' );

/**
 * Activation hook — set sane defaults.
 */
function wer_activate() {
	$defaults = array(
		'global_redirect_url' => '',
		'preserve_path'       => '1',
		'redirect_type'       => '301',
		'exclude_logged_in'   => '0',
		'enabled'             => '0', // Off by default so the site doesn't break immediately.
	);
	if ( false === get_option( WER_OPTION_KEY ) ) {
		add_option( WER_OPTION_KEY, $defaults );
	}
	if ( false === get_option( WER_REDIRECTS_KEY ) ) {
		add_option( WER_REDIRECTS_KEY, array() );
	}
}
register_activation_hook( __FILE__, 'wer_activate' );

/**
 * Deactivation hook — nothing special needed.
 */
function wer_deactivate() {
	// Intentionally left blank. Settings persist.
}
register_deactivation_hook( __FILE__, 'wer_deactivate' );

/**
 * Uninstall hook — clean up.
 */
function wer_uninstall() {
	delete_option( WER_OPTION_KEY );
	delete_option( WER_REDIRECTS_KEY );
}
register_uninstall_hook( __FILE__, 'wer_uninstall' );
