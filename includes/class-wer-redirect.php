<?php
/**
 * Core redirect logic.
 *
 * Hooks in early on `template_redirect` and sends the appropriate header.
 *
 * @package WP_Easy_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WER_Redirect {

	/** @var WER_Redirect Singleton */
	private static $instance = null;

	/**
	 * Get the singleton.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wire up the hook.
	 */
	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Decide whether to redirect and where.
	 */
	public function maybe_redirect() {
		// Never redirect admin, cron, REST, or login pages.
		if ( is_admin() || wp_doing_cron() || $this->is_rest_request() || $this->is_login_page() ) {
			return;
		}

		$settings = WER_Settings::get_settings();

		// Global redirect — redirect all front-end requests unless excluded.
		if ( '1' === $settings['enabled'] && ! empty( $settings['global_redirect_url'] ) ) {
			$exclude_logged_in = ( '1' === $settings['exclude_logged_in'] && is_user_logged_in() );

			if ( ! $exclude_logged_in ) {
				$this->do_global_redirect( $settings );
				return;
			}
		}

		// Individual redirect rules.
		$this->do_individual_redirects();
	}

	/**
	 * Perform the global (whole-site) redirect.
	 *
	 * @param array $settings Plugin settings.
	 */
	private function do_global_redirect( $settings ) {
		$target = $settings['global_redirect_url'];

		// Preserve path — append the current request path to the target.
		if ( '1' === $settings['preserve_path'] ) {
			$target = $this->append_path( $target );
		}

		// Preserve query string.
		$target = $this->append_query( $target );

		$this->send_redirect( $target, $settings['redirect_type'] );
	}

	/**
	 * Check and execute individual redirect rules.
	 */
	private function do_individual_redirects() {
		$redirects = WER_Settings::get_redirects();
		if ( empty( $redirects ) ) {
			return;
		}

		$request_path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( false === $request_path ) {
			return;
		}

		$request_path = trailingslashit( $request_path );

		foreach ( $redirects as $rule ) {
			if ( empty( $rule['from'] ) || empty( $rule['to'] ) ) {
				continue;
			}

			$from = trailingslashit( $rule['from'] );

			// Exact match or wildcard match.
			if ( $this->path_matches( $request_path, $from ) ) {
				$target = $rule['to'];

				// If the rule uses a wildcard, replace it in the target.
				if ( strpos( $from, '*' ) !== false && strpos( $target, '$1' ) !== false ) {
					$wildcard = $this->extract_wildcard( $request_path, $from );
					$target   = str_replace( '$1', $wildcard, $target );
				}

				$target = $this->append_query( $target );
				$this->send_redirect( $target, $rule['type'] );
				return;
			}
		}
	}

	/**
	 * Check whether a request path matches a from-pattern.
	 *
	 * Supports:
	 *  - Exact match: /old-page/ → /old-page/
	 *  - Wildcard:     /blog/*    → matches /blog/anything
	 *
	 * @param string $request Requested path (already slash-normalised).
	 * @param string $pattern From-pattern (already slash-normalised).
	 * @return bool
	 */
	private function path_matches( $request, $pattern ) {
		// Wildcard support — /old/* matches anything under /old/.
		if ( strpos( $pattern, '*' ) !== false ) {
			$regex = '#^' . preg_quote( str_replace( '*', '', $pattern ), '#' ) . '.+/?$#i';
			return (bool) preg_match( $regex, $request );
		}

		// Case-insensitive exact match.
		return 0 === strcasecmp( $request, $pattern );
	}

	/**
	 * Extract the portion matched by the wildcard.
	 *
	 * @param string $request Requested path.
	 * @param string $pattern From-pattern with *.
	 * @return string
	 */
	private function extract_wildcard( $request, $pattern ) {
		$prefix = str_replace( '*', '', $pattern );
		return ltrim( substr( $request, strlen( $prefix ) ), '/' );
	}

	/**
	 * Append the current request path to a base URL.
	 *
	 * @param string $url Target base URL.
	 * @return string
	 */
	private function append_path( $url ) {
		$path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( $path && '/' !== $path ) {
			$url = rtrim( $url, '/' ) . $path;
		}
		return $url;
	}

	/**
	 * Append the current query string if present.
	 *
	 * @param string $url Target URL.
	 * @return string
	 */
	private function append_query( $url ) {
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . $_SERVER['QUERY_STRING'];
		}
		return $url;
	}

	/**
	 * Send a redirect header and exit.
	 *
	 * @param string $url  Target URL.
	 * @param string $type HTTP status code (e.g. 301, 302).
	 */
	private function send_redirect( $url, $type = '301' ) {
		$code = WER_Settings::sanitize_redirect_type( $type );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- intentional external redirect.
		wp_redirect( $url, intval( $code ) );
		exit;
	}

	/**
	 * Is this a REST API request?
	 *
	 * @return bool
	 */
	private function is_rest_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Is this a wp-login.php or wp-register.php request?
	 *
	 * These pages are standalone and template_redirect should not fire on them,
	 * but some caching/SEO plugins invoke template_redirect early, so we guard
	 * explicitly.
	 *
	 * @return bool
	 */
	private function is_login_page() {
		$script = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : '';
		return in_array( $script, array( 'wp-login.php', 'wp-register.php' ), true );
	}
}
