<?php
/**
 * Admin settings page.
 *
 * Registers a menu item under Settings → WP Redirect and renders
 * the form for both global and individual redirect rules.
 *
 * @package WP_Easy_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WER_Admin_Page {

	/** @var WER_Admin_Page Singleton */
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
		* Constructor — register hooks.
		*/
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Show a subtle admin bar notice when global redirect is active.
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_notice' ), 999 );
	}

	// ─── Menu ──────────────────────────────────────────────────────────────

	/**
		* Add submenu page under Settings.
		*/
	public function add_menu() {
		add_options_page(
			__( 'WP Easy Redirect', 'wp-easy-redirect' ),
			__( 'WP Redirect', 'wp-easy-redirect' ),
			'manage_options',
			'wp-easy-redirect',
			array( $this, 'render_page' )
		);
	}

	// ─── Settings Registration ─────────────────────────────────────────────

	/**
		* Register settings and their sanitisation callbacks.
		*/
	public function register_settings() {
		register_setting( 'wer_global_group', WER_OPTION_KEY, array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );
		register_setting( 'wer_rules_group', WER_REDIRECTS_KEY, array( 'sanitize_callback' => array( $this, 'sanitize_redirects' ) ) );
	}

	/**
		* Sanitise global settings before saving.
		*
		* @param array $input Raw input.
		* @return array
		*/
	public function sanitize_settings( $input ) {
		$clean = array();

		$clean['global_redirect_url'] = esc_url_raw( $input['global_redirect_url'] ?? '' );
		$clean['preserve_path']       = ! empty( $input['preserve_path'] ) ? '1' : '0';
		$clean['exclude_logged_in']   = ! empty( $input['exclude_logged_in'] ) ? '1' : '0';
		$clean['redirect_type']       = WER_Settings::sanitize_redirect_type( $input['redirect_type'] ?? '301' );
		$clean['enabled']             = ! empty( $input['enabled'] ) ? '1' : '0';

		// Safety: if the redirect target is the site itself, disable it.
		if ( ! empty( $clean['global_redirect_url'] ) ) {
			$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
			$target_host = wp_parse_url( $clean['global_redirect_url'], PHP_URL_HOST );
			if ( $site_host && $target_host && $site_host === $target_host ) {
				$clean['enabled'] = '0';
				add_settings_error( 'wer_settings', 'self-redirect', __( 'Global redirect disabled — the target URL is the same as this site.', 'wp-easy-redirect' ), 'error' );
			}
		}

		return $clean;
	}

	/**
		* Sanitise individual redirect rules before saving.
		*
		* @param mixed $input Raw input from the form.
		* @return array
		*/
	public function sanitize_redirects( $input ) {
		$clean = array();
		if ( ! is_array( $input ) ) {
			return $clean;
		}
		foreach ( $input as $rule ) {
			if ( empty( $rule['from'] ) && empty( $rule['to'] ) ) {
				continue;
			}
			$clean[] = array(
				'from' => sanitize_text_field( $rule['from'] ),
				'to'   => esc_url_raw( $rule['to'] ),
				'type' => WER_Settings::sanitize_redirect_type( $rule['type'] ?? '301' ),
			);
		}
		return $clean;
	}

	// ─── Enqueue ───────────────────────────────────────────────────────────

	/**
		* Enqueue CSS for the settings page only.
		*
		* @param string $hook Current admin page hook.
		*/
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_wp-easy-redirect' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wer-admin',
			WER_PLUGIN_URL . 'assets/admin.css',
			array(),
			WER_VERSION
		);

		wp_enqueue_script(
			'wer-admin',
			WER_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			WER_VERSION,
			true
		);

		// Pass the redirects option key to JS for dynamic row naming.
		wp_localize_script(
			'wer-admin',
			'werRedirectsKey',
			array( 'value' => WER_REDIRECTS_KEY )
		);
	}

	// ─── Admin Bar Notice ───────────────────────────────────────────────────

	/**
		* Show a small notice in the admin bar when the global redirect is live.
		*
		* @param WP_Admin_Bar $wp_admin_bar
		*/
	public function admin_bar_notice( $wp_admin_bar ) {
		$settings = WER_Settings::get_settings();
		if ( '1' === $settings['enabled'] && ! empty( $settings['global_redirect_url'] ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'wer-notice',
					'title' => '<span class="ab-icon" style="color:#d63638;">⟳</span> ' . __( 'Redirect Active', 'wp-easy-redirect' ),
					'href'  => admin_url( 'options-general.php?page=wp-easy-redirect' ),
					'meta'  => array( 'class' => 'wer-admin-bar-notice' ),
				)
			);
		}
	}

	// ─── Render ────────────────────────────────────────────────────────────

	/**
		* Render the settings page.
		*/
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = WER_Settings::get_settings();
		$redirects = WER_Settings::get_redirects();
		$types     = array(
			'301' => __( '301 — Moved Permanently', 'wp-easy-redirect' ),
			'302' => __( '302 — Found (Temporary)', 'wp-easy-redirect' ),
			'307' => __( '307 — Temporary Redirect', 'wp-easy-redirect' ),
			'308' => __( '308 — Permanent Redirect', 'wp-easy-redirect' ),
		);
		?>
		<div class="wrap wer-wrap">
			<h1><?php esc_html_e( 'WP Easy Redirect', 'wp-easy-redirect' ); ?> <small>v<?php echo esc_html( WER_VERSION ); ?></small></h1>

			<?php settings_errors( 'wer_settings' ); ?>

			<!-- ─── Global Redirect ──────────────────────────────────── -->
			<div class="wer-card">
				<h2><?php esc_html_e( 'Global Site Redirect', 'wp-easy-redirect' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Redirect the entire front-end of this site to another URL. Logged-in admins will still see the admin area.', 'wp-easy-redirect' ); ?>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'wer_global_group' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="wer-enabled"><?php esc_html_e( 'Enable Redirect', 'wp-easy-redirect' ); ?></label>
							</th>
							<td>
								<label class="wer-toggle">
									<input type="checkbox"
										id="wer-enabled"
										name="<?php echo esc_attr( WER_OPTION_KEY ); ?>[enabled]"
										value="1"
										<?php checked( $settings['enabled'], '1' ); ?> />
									<span class="wer-toggle-slider"></span>
								</label>
								<span class="description"><?php esc_html_e( 'Turn on to activate the redirect.', 'wp-easy-redirect' ); ?></span>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wer-global-url"><?php esc_html_e( 'Target URL', 'wp-easy-redirect' ); ?></label>
							</th>
							<td>
								<input type="url"
									id="wer-global-url"
									class="regular-text"
									name="<?php echo esc_attr( WER_OPTION_KEY ); ?>[global_redirect_url]"
									value="<?php echo esc_attr( $settings['global_redirect_url'] ); ?>"
									placeholder="https://new-site.example.com" />
								<p class="description"><?php esc_html_e( 'The full URL to redirect visitors to.', 'wp-easy-redirect' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wer-preserve-path"><?php esc_html_e( 'Preserve Path', 'wp-easy-redirect' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox"
										id="wer-preserve-path"
										name="<?php echo esc_attr( WER_OPTION_KEY ); ?>[preserve_path]"
										value="1"
										<?php checked( $settings['preserve_path'], '1' ); ?> />
									<?php esc_html_e( 'Append the current path to the target URL', 'wp-easy-redirect' ); ?>
								</p></label>
								<p class="description">
									<?php esc_html_e( 'e.g. example.com/about → new-site.example.com/about', 'wp-easy-redirect' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wer-redirect-type"><?php esc_html_e( 'Redirect Type', 'wp-easy-redirect' ); ?></label>
							</th>
							<td>
								<select id="wer-redirect-type"
									name="<?php echo esc_attr( WER_OPTION_KEY ); ?>[redirect_type]">
									<?php foreach ( $types as $code => $label ) : ?>
										<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $settings['redirect_type'], $code ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wer-exclude-logged-in"><?php esc_html_e( 'Exclude Logged-In Users', 'wp-easy-redirect' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox"
										id="wer-exclude-logged-in"
										name="<?php echo esc_attr( WER_OPTION_KEY ); ?>[exclude_logged_in]"
										value="1"
										<?php checked( $settings['exclude_logged_in'], '1' ); ?> />
									<?php esc_html_e( 'Don\'t redirect logged-in users', 'wp-easy-redirect' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Global Redirect', 'wp-easy-redirect' ) ); ?>
				</form>
			</div>

			<!-- ─── Individual Redirects ───────────────────────────────── -->
			<div class="wer-card">
				<h2><?php esc_html_e( 'Individual Redirect Rules', 'wp-easy-redirect' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Redirect specific URLs. Use * as a wildcard in the "From" field and $1 in the "To" field to capture the wildcard portion.', 'wp-easy-redirect' ); ?>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'wer_rules_group' ); ?>

					<table class="widefat fixed wer-redirects-table" id="wer-redirects-table">
						<thead>
							<tr>
								<th style="width:35%;"><?php esc_html_e( 'From (path)', 'wp-easy-redirect' ); ?></th>
								<th style="width:40%;"><?php esc_html_e( 'To (URL)', 'wp-easy-redirect' ); ?></th>
								<th style="width:15%;"><?php esc_html_e( 'Type', 'wp-easy-redirect' ); ?></th>
								<th style="width:10%;"></th>
							</tr>
						</thead>
						<tbody id="wer-redirects-body">
							<?php if ( ! empty( $redirects ) ) : ?>
								<?php foreach ( $redirects as $i => $rule ) : ?>
								<tr>
									<td><input type="text" class="regular-text" name="<?php echo esc_attr( WER_REDIRECTS_KEY ); ?>[<?php echo esc_attr( $i ); ?>][from]" value="<?php echo esc_attr( $rule['from'] ); ?>" placeholder="/old-page" /></td>
									<td><input type="url" class="regular-text" name="<?php echo esc_attr( WER_REDIRECTS_KEY ); ?>[<?php echo esc_attr( $i ); ?>][to]" value="<?php echo esc_attr( $rule['to'] ); ?>" placeholder="https://new-site.example.com/page" /></td>
									<td>
										<select name="<?php echo esc_attr( WER_REDIRECTS_KEY ); ?>[<?php echo esc_attr( $i ); ?>][type]">
											<?php foreach ( $types as $code => $label ) : ?>
												<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $rule['type'], $code ); ?>><?php echo esc_html( (string) $code ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><button type="button" class="button wer-remove-row" title="<?php esc_attr_e( 'Remove', 'wp-easy-redirect' ); ?>">✕</button></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<p>
						<button type="button" class="button" id="wer-add-row"><?php esc_html_e( '+ Add Redirect Rule', 'wp-easy-redirect' ); ?></button>
					</p>

					<?php submit_button( __( 'Save Redirect Rules', 'wp-easy-redirect' ) ); ?>
				</form>
			</div>

			<!-- ─── Quick Reference ───────────────────────────────────── -->
			<div class="wer-card">
				<h2><?php esc_html_e( 'Quick Reference', 'wp-easy-redirect' ); ?></h2>
				<table class="wer-help-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Exact match', 'wp-easy-redirect' ); ?></th>
							<td><code>/old-page</code></td>
							<td>→ <code>https://new.example.com/new-page</code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Wildcard', 'wp-easy-redirect' ); ?></th>
							<td><code>/blog/*</code></td>
							<td>→ <code>https://new.example.com/news/$1</code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Root only', 'wp-easy-redirect' ); ?></th>
							<td><code>/</code></td>
							<td>→ <code>https://new.example.com</code></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
