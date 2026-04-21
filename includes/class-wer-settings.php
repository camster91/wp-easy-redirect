<?php
/**
 * Settings helper — CRUD for the two option rows.
 *
 * @package WP_Easy_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WER_Settings {

	/**
	 * Get all global settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'global_redirect_url' => '',
			'preserve_path'       => '1',
			'redirect_type'       => '301',
			'exclude_logged_in'   => '0',
			'enabled'             => '0',
		);
		$saved    = get_option( WER_OPTION_KEY, array() );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Update global settings.
	 *
	 * @param array $new New settings to save.
	 * @return bool
	 */
	public static function update_settings( $new ) {
		$old    = self::get_settings();
		$merged = wp_parse_args( $new, $old );
		return update_option( WER_OPTION_KEY, $merged );
	}

	/**
	 * Get individual redirect rules.
	 *
	 * @return array  [ [ 'from' => '/', 'to' => 'https://…', 'type' => '301' ], … ]
	 */
	public static function get_redirects() {
		$saved = get_option( WER_REDIRECTS_KEY, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Save individual redirect rules.
	 *
	 * @param array $redirects Array of redirect rule arrays.
	 * @return bool
	 */
	public static function update_redirects( $redirects ) {
		// Sanitise each row.
		$clean = array();
		foreach ( $redirects as $rule ) {
			if ( empty( $rule['from'] ) && empty( $rule['to'] ) ) {
				continue; // Drop blank rows.
			}
			$clean[] = array(
				'from' => sanitize_text_field( $rule['from'] ),
				'to'   => esc_url_raw( $rule['to'] ),
				'type' => self::sanitize_redirect_type( $rule['type'] ),
			);
		}
		return update_option( WER_REDIRECTS_KEY, $clean );
	}

	/**
	 * Ensure a redirect HTTP code is one we support.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function sanitize_redirect_type( $type ) {
		$allowed = array( '301', '302', '307', '308' );
		return in_array( $type, $allowed, true ) ? $type : '301';
	}
}
