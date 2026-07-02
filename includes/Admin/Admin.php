<?php
/**
 * Admin controller: menu, assets, AJAX, dashboard rendering.
 *
 * @package StackPress
 */

namespace StackPress\Admin;

use StackPress\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the StackPress admin experience.
 */
final class Admin {

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'stackpress';

	/**
	 * Nonce action for AJAX.
	 */
	const NONCE = 'stackpress_admin';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_head', array( $this, 'frame_chrome_css' ) );
		add_action( 'admin_post_stackpress_save_settings', array( $this, 'handle_save_settings_page' ) );
		add_filter( 'plugin_action_links_' . STACKPRESS_BASENAME, array( $this, 'action_links' ) );

		add_action( 'wp_ajax_stackpress_toggle_module', array( $this, 'ajax_toggle_module' ) );
		add_action( 'wp_ajax_stackpress_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_stackpress_get_settings_form', array( $this, 'ajax_get_settings_form' ) );
		add_action( 'wp_ajax_stackpress_bulk_toggle', array( $this, 'ajax_bulk_toggle' ) );
		add_action( 'wp_ajax_stackpress_create_tip_payment', array( $this, 'ajax_create_tip_payment' ) );
		add_action( 'admin_post_stackpress_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_menu', array( $this, 'register_changes_page' ), 20 );
		add_action( 'admin_post_stackpress_clear_log', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_menu', array( $this, 'register_agency_page' ), 21 );
		add_action( 'admin_post_stackpress_enable_agency', array( $this, 'handle_enable_agency' ) );
		add_action( 'admin_menu', array( $this, 'register_setup_page' ), 19 );
		add_action( 'admin_menu', array( $this, 'register_diagnostics_page' ), 22 );
		add_action( 'admin_post_stackpress_setup', array( $this, 'handle_setup' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_dismiss_setup' ) );
		add_action( 'admin_notices', array( $this, 'module_failure_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_clear_failures' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_tip_settings' ) );
	}

	/**
	 * Scan the site and build a list of recommended modules to enable.
	 *
	 * @return array[] Each: id, label, reason.
	 */
	public function recommendations() {
		$core     = Core::instance();
		$active   = $core->get_active_modules();
		$reg      = $core->registry();
		$detected = \StackPress\Environment::detected_plugins();

		// Candidate list (order = priority shown).
		$candidates = array(
			array( 'security_hardening', __( 'Closes common WordPress security gaps.', 'stackpress' ) ),
			array( 'login_protection', __( 'Blocks brute-force login attempts.', 'stackpress' ) ),
			array( 'spam_shield', __( 'Stops comment & registration spam — no CAPTCHA service needed.', 'stackpress' ) ),
			array( 'meta_tags', __( 'Adds SEO meta and social-sharing tags.', 'stackpress' ) ),
			array( 'schema_jsonld', __( 'Adds schema so Google can show rich results.', 'stackpress' ) ),
			array( 'page_cache', __( 'Serves cached pages for much faster loads.', 'stackpress' ) ),
			array( 'minify_css', __( 'Shrinks stylesheets for faster loads.', 'stackpress' ) ),
			array( 'lazy_loading', __( 'Defers off-screen images and iframes for speed.', 'stackpress' ) ),
			array( 'limit_revisions', __( 'Keeps your database lean.', 'stackpress' ) ),
			array( 'backup_restore', __( 'Protects your site with backups.', 'stackpress' ) ),
		);

		if ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			$candidates[] = array( 'force_https', __( 'Your site uses HTTPS — enforce it on every page.', 'stackpress' ) );
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$candidates[] = array( 'optimize_wc_scripts', __( 'WooCommerce detected — stop its scripts loading on non-shop pages.', 'stackpress' ) );
			$candidates[] = array( 'wc_product_labels', __( 'WooCommerce detected — add Sale/New badges to products.', 'stackpress' ) );
		}

		$recommended = array();
		$covered     = array();

		foreach ( $candidates as $cand ) {
			list( $id, $reason ) = $cand;

			// Skip if already on, missing a dependency, or the server can't run it.
			if ( in_array( $id, $active, true ) ) {
				continue;
			}
			$module = $reg->get_instance( $id );
			if ( ! $module || ! $reg->dependencies_met( $id ) || ! $reg->requirements_met( $id ) ) {
				continue;
			}

			$item    = array(
				'id'     => $id,
				'label'  => $module->name(),
				'reason' => $reason,
			);
			$feature = \StackPress\Environment::module_feature( $id );

			// If another active plugin already handles this area, don't recommend it.
			if ( '' !== $feature && isset( $detected[ $feature ] ) ) {
				$item['by']  = $detected[ $feature ];
				$covered[]   = $item;
			} else {
				$recommended[] = $item;
			}
		}

		return array(
			'recommended' => $recommended,
			'covered'     => $covered,
			'detected'    => $detected,
		);
	}

	/**
	 * Register the Recommended setup page.
	 *
	 * @return void
	 */
	public function register_setup_page() {
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Recommended setup', 'stackpress' ),
			__( 'Recommended setup', 'stackpress' ),
			'manage_options',
			'stackpress-setup',
			array( $this, 'render_setup_page' )
		);
	}

	/**
	 * A one-time dashboard prompt to run the scan.
	 *
	 * @return void
	 */
	public function setup_notice() {
		if ( get_option( Core::SETUP_OPTION ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false === strpos( (string) $screen->id, 'stackpress' ) ) {
			return; // Only nudge on StackPress screens.
		}
		$dismiss = wp_nonce_url( add_query_arg( 'stackpress_setup_dismiss', '1' ), 'stackpress_setup_dismiss' );
		echo '<div class="notice notice-info is-dismissible"><p><strong>StackPress</strong> — ' . esc_html__( 'Run the quick site scan to enable the tools your site really needs.', 'stackpress' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=stackpress-setup' ) ) . '" class="button button-primary" style="margin-left:6px;">' . esc_html__( 'Scan my site', 'stackpress' ) . '</a> <a href="' . esc_url( $dismiss ) . '" style="margin-left:10px;color:#646970;text-decoration:underline;">' . esc_html__( 'Dismiss', 'stackpress' ) . '</a></p></div>';
	}

	/**
	 * Save settings posted from a dedicated tool page (Settings_Renderer::page_form).
	 *
	 * @return void
	 */
	public function handle_save_settings_page() {
		$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_save_settings_' . $module_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
		$module = Core::instance()->registry()->get_instance( $module_id );
		if ( $module ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; each field sanitised in save_settings().
			$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
			$module->save_settings( $raw );
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'settings-saved', '1', $back ? $back : admin_url() ) );
		exit;
	}

	/**
	 * Register the Diagnostics page.
	 *
	 * @return void
	 */
	public function register_diagnostics_page() {
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Diagnostics', 'stackpress' ),
			__( 'Diagnostics', 'stackpress' ),
			'manage_options',
			'stackpress-diagnostics',
			array( $this, 'render_diagnostics_page' )
		);
	}

	/**
	 * Render the Diagnostics / system-info page.
	 *
	 * @return void
	 */
	public function render_diagnostics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
		global $wp_version;
		$core   = Core::instance();
		$active = $core->get_active_modules();
		$caps   = \StackPress\Environment::map();

		$rows = array(
			'StackPress version'    => STACKPRESS_VERSION,
			'WordPress'         => $wp_version,
			'PHP'               => PHP_VERSION,
			'Server'            => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '—',
			'Memory limit'      => (string) ini_get( 'memory_limit' ),
			'Max execution'     => (string) ini_get( 'max_execution_time' ) . 's',
			'WooCommerce'       => class_exists( 'WooCommerce' ) ? 'active' : 'no',
			'Multisite'         => is_multisite() ? 'yes' : 'no',
			'Active tools'      => count( $active ) . ' / ' . $core->registry()->count(),
		);
		foreach ( $caps as $key => $cap ) {
			$rows[ 'Capability: ' . $cap['label'] ] = $cap['available'] ? 'available' : 'not available';
		}
		$rows['Active tool IDs'] = empty( $active ) ? '—' : implode( ', ', $active );

		// Plain-text block for copy/paste into support.
		$plain = "StackPress diagnostics\n==================\n";
		foreach ( $rows as $k => $v ) {
			$plain .= $k . ': ' . $v . "\n";
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'StackPress diagnostics', 'stackpress' ) . '</h1>';
		echo '<p>' . esc_html__( 'A snapshot of your environment. Copy this when contacting support.', 'stackpress' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:760px;"><tbody>';
		foreach ( $rows as $k => $v ) {
			echo '<tr><td style="width:34%;font-weight:600;">' . esc_html( $k ) . '</td><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p style="margin-top:16px;"><label><strong>' . esc_html__( 'Copy for support', 'stackpress' ) . '</strong></label></p>';
		echo '<textarea readonly onclick="this.select()" style="width:100%;max-width:760px;height:200px;font-family:monospace;font-size:12px;">' . esc_textarea( $plain ) . '</textarea>';
		echo '</div>';
	}

	/**
	 * Warn if any module was auto-disabled after erroring (Safe-Mode recovery).
	 *
	 * @return void
	 */
	public function module_failure_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$fails = get_option( Core::FAILURES_OPTION, array() );
		if ( empty( $fails ) || ! is_array( $fails ) ) {
			return;
		}
		$reg   = Core::instance()->registry();
		$names = array();
		foreach ( array_keys( $fails ) as $id ) {
			$module  = $reg->get_instance( $id );
			$names[] = $module ? $module->name() : $id;
		}
		$clear = wp_nonce_url( add_query_arg( 'stackpress_clear_failures', '1' ), 'stackpress_clear_failures' );
		echo '<div class="notice notice-error"><p><strong>StackPress</strong> — ' . esc_html(
			sprintf(
				/* translators: %s: comma-separated tool names. */
				__( 'These tools were turned off automatically because they caused an error, so your site kept running: %s. You can re-enable them after checking your setup.', 'stackpress' ),
				implode( ', ', $names )
			)
		) . ' <a href="' . esc_url( $clear ) . '">' . esc_html__( 'Dismiss', 'stackpress' ) . '</a></p></div>';
	}

	/**
	 * Clear the recorded module failures.
	 *
	 * @return void
	 */
	public function maybe_clear_failures() {
		if ( ! isset( $_GET['stackpress_clear_failures'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'stackpress_clear_failures' );
		delete_option( Core::FAILURES_OPTION );
		wp_safe_redirect( remove_query_arg( array( 'stackpress_clear_failures', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Save Paystack tip settings from a simple admin form.
	 *
	 * @return void
	 */
	public function maybe_save_tip_settings() {
		if ( ! isset( $_POST['stackpress_tip_settings_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'stackpress_tip_settings', 'stackpress_tip_settings_nonce' );
		$settings = array(
			'public_key' => isset( $_POST['stackpress_tip_public_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stackpress_tip_public_key'] ) ) : '',
			'secret_key' => isset( $_POST['stackpress_tip_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stackpress_tip_secret_key'] ) ) : '',
		);
		update_option( 'stackpress_tip_settings', $settings );
		wp_safe_redirect( add_query_arg( 'stackpress_tip_saved', '1', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	/**
	 * Permanently dismiss the setup nudge when the user clicks Dismiss.
	 *
	 * @return void
	 */
	public function maybe_dismiss_setup() {
		if ( ! isset( $_GET['stackpress_setup_dismiss'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'stackpress_setup_dismiss' );
		update_option( Core::SETUP_OPTION, true );
		wp_safe_redirect( remove_query_arg( array( 'stackpress_setup_dismiss', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Enable the modules the user selected from the scan.
	 *
	 * @return void
	 */
	public function handle_setup() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_setup' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
		$core    = Core::instance();
		$catalog = $core->registry()->catalog();
		$chosen  = isset( $_POST['enable'] ) && is_array( $_POST['enable'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['enable'] ) ) : array();
		$count   = 0;
		foreach ( $chosen as $id ) {
			if ( isset( $catalog[ $id ] ) && $core->enable_module( $id ) ) {
				$count++;
			}
		}
		update_option( Core::SETUP_OPTION, true );
		$this->log_change( sprintf( /* translators: %d: count. */ __( 'Recommended setup enabled %d modules', 'stackpress' ), $count ) );
		wp_safe_redirect( admin_url( 'admin.php?page=stackpress-setup&done=' . $count ) );
		exit;
	}

	/**
	 * Render the Recommended setup / scan page.
	 *
	 * @return void
	 */
	public function render_setup_page() {
		$data        = $this->recommendations();
		$recommended = $data['recommended'];
		$covered     = $data['covered'];
		$detected    = $data['detected'];

		$this->setup_styles();
		echo '<div class="wrap"><div class="owp-setup">';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['done'] ) ) {
			echo '<div class="notice notice-success" style="margin:0 0 16px;"><p>' . esc_html( sprintf( /* translators: %d: count. */ _n( '%d tool enabled. Your site is ready.', '%d tools enabled. Your site is ready.', (int) $_GET['done'], 'stackpress' ), (int) $_GET['done'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		echo '<div class="owp-card">';

		// Header.
		echo '<div class="owp-head">';
		echo '<h2>' . esc_html__( 'Recommended setup', 'stackpress' ) . '</h2>';
		echo '<p>' . esc_html__( 'StackPress scanned your site — including the other plugins you already run — and chose only the tools that are safe and useful here. Nothing is changed until you click enable.', 'stackpress' ) . '</p>';
		if ( ! empty( $detected ) ) {
			echo '<div class="owp-detected"><span style="color:#9fe8f5;font-size:12.5px;align-self:center;">' . esc_html__( 'Detected on your site:', 'stackpress' ) . '</span>';
			foreach ( $detected as $feature => $plugin ) {
				echo '<span class="owp-chip">' . esc_html( $plugin ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';

		if ( empty( $recommended ) && empty( $covered ) ) {
			echo '<div class="owp-empty">' . esc_html__( 'Great — everything recommended for your site is already enabled. You are all set!', 'stackpress' ) . '</div></div></div></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'stackpress_setup' );
		echo '<input type="hidden" name="action" value="stackpress_setup" />';

		echo '<div class="owp-sec">';

		// Recommended (pre-checked).
		if ( ! empty( $recommended ) ) {
			echo '<h3>' . esc_html__( 'Recommended for your site', 'stackpress' ) . '</h3>';
			echo '<p class="owp-sub">' . esc_html__( 'These are safe to enable and not handled by any plugin you already run.', 'stackpress' ) . '</p>';
			foreach ( $recommended as $r ) {
				echo '<label class="owp-row">';
				echo '<input type="checkbox" name="enable[]" value="' . esc_attr( $r['id'] ) . '" checked />';
				echo '<span><span class="t">' . esc_html( $r['label'] ) . '</span><span class="r">' . esc_html( $r['reason'] ) . '</span></span>';
				echo '</label>';
			}
		}

		// Already handled by another plugin (unchecked).
		if ( ! empty( $covered ) ) {
			echo '<h3>' . esc_html__( 'Already handled by your other plugins', 'stackpress' ) . '</h3>';
			echo '<p class="owp-sub">' . esc_html__( 'We left these OFF so they do not clash. Only enable one if you plan to switch away from the plugin shown.', 'stackpress' ) . '</p>';
			foreach ( $covered as $r ) {
				echo '<label class="owp-row is-covered">';
				echo '<input type="checkbox" name="enable[]" value="' . esc_attr( $r['id'] ) . '" />';
				echo '<span><span class="t">' . esc_html( $r['label'] ) . '</span><span class="r">' . esc_html( $r['reason'] ) . '</span>';
				echo '<span class="owp-by">' . esc_html( sprintf( /* translators: %s: plugin name. */ __( 'Already handled by %s', 'stackpress' ), $r['by'] ) ) . '</span>';
				echo '</span></label>';
			}
		}

		echo '</div>'; // .owp-sec

		echo '<div class="owp-actions"><button type="submit" class="owp-btn">' . esc_html__( 'Enable selected tools', 'stackpress' ) . '</button></div>';
		echo '</form>';
		echo '</div></div></div>'; // card, owp-setup, wrap
	}

	/**
	 * Inline styles for the setup page (kept self-contained, scoped to .owp-setup).
	 *
	 * @return void
	 */
	private function setup_styles() {
		echo '<style>
.owp-setup{max-width:860px}
.owp-setup .owp-card{background:#fff;border:1px solid #e4e7ec;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.06);margin-top:10px}
.owp-setup .owp-head{background:linear-gradient(120deg,#0b2545,#16386b);color:#fff;padding:26px 28px}
.owp-setup .owp-head h2{color:#fff;margin:0 0 6px;font-size:22px;font-weight:700}
.owp-setup .owp-head p{color:#c7d2e2;margin:0;font-size:14px;max-width:620px}
.owp-setup .owp-detected{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.owp-setup .owp-chip{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);color:#fff;border-radius:999px;padding:4px 12px;font-size:12.5px}
.owp-setup .owp-sec{padding:8px 28px 20px}
.owp-setup h3{font-size:15px;margin:22px 0 2px;color:#101828}
.owp-setup .owp-sub{color:#5b6576;font-size:13px;margin:0 0 12px}
.owp-setup .owp-row{display:flex;gap:13px;align-items:flex-start;padding:13px 15px;border:1px solid #eef1f5;border-radius:10px;margin-bottom:9px;cursor:pointer;transition:.12s}
.owp-setup .owp-row:hover{border-color:#cdd6e4;background:#fafcff}
.owp-setup .owp-row.is-covered{background:#fbfbfd;border-style:dashed}
.owp-setup .owp-row input[type=checkbox]{margin:3px 0 0;width:17px;height:17px;flex:0 0 auto}
.owp-setup .owp-row .t{display:block;font-weight:600;color:#101828}
.owp-setup .owp-row .r{display:block;color:#5b6576;font-size:13px;margin-top:2px}
.owp-setup .owp-by{display:inline-block;margin-top:7px;font-size:12px;color:#8a5a00;background:#fff4d6;border:1px solid #f0d98a;border-radius:6px;padding:2px 8px}
.owp-setup .owp-actions{padding:18px 28px;border-top:1px solid #eef1f5;background:#fafbfc}
.owp-setup .owp-btn{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:13px 28px;font-size:15px;font-weight:700;cursor:pointer}
.owp-setup .owp-btn:hover{background:#1d4fd7}
.owp-setup .owp-empty{padding:26px 28px;color:#3b6d11;font-size:15px}
</style>';
	}

	/**
	 * The modules that make up "agency mode".
	 *
	 * @return array<string,string> id => label
	 */
	public function agency_bundle() {
		return array(
			'white_label'          => __( 'White label (rename for clients)', 'stackpress' ),
			'admin_branding'       => __( 'Admin footer branding', 'stackpress' ),
			'monthly_report'       => __( 'Monthly client report', 'stackpress' ),
			'activity_log'         => __( 'Activity log', 'stackpress' ),
			'error_monitor'        => __( 'Error monitor', 'stackpress' ),
			'backup_restore'       => __( 'Backup & restore', 'stackpress' ),
			'cloud_backup'         => __( 'Cloud backup', 'stackpress' ),
			'config_export_import' => __( 'Config import / export', 'stackpress' ),
			'admin_menu_editor'    => __( 'Admin menu editor', 'stackpress' ),
			'hide_admin_notices'   => __( 'Hide admin notices', 'stackpress' ),
			'welcome_widget'       => __( 'Dashboard welcome widget', 'stackpress' ),
		);
	}

	/**
	 * Option storing the StackPress change log.
	 */
	const CHANGE_LOG = 'stackpress_change_log';

	/**
	 * Append an entry to the StackPress change log (what the plugin changed).
	 *
	 * @param string $message Human-readable description.
	 * @return void
	 */
	private function log_change( $message ) {
		$user = wp_get_current_user();
		$log  = get_option( self::CHANGE_LOG, array() );
		$log  = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'time' => time(),
				'user' => $user && $user->exists() ? $user->user_login : '—',
				'msg'  => $message,
			)
		);
		update_option( self::CHANGE_LOG, array_slice( $log, 0, 200 ), false );
	}

	/**
	 * Initialize a Paystack tip payment from the dashboard.
	 *
	 * @return void
	 */
	public function ajax_create_tip_payment() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'stackpress' ) ), 403 );
		}
		check_ajax_referer( 'stackpress_admin', 'nonce' );

		$amount = isset( $_POST['amount'] ) ? absint( wp_unslash( $_POST['amount'] ) ) : 0;
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( $amount < 100 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose at least 100 NGN.', 'stackpress' ) ) );
		}
		if ( ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		$settings = get_option( 'stackpress_tip_settings', array() );
		$public_key = isset( $settings['public_key'] ) ? $settings['public_key'] : ( defined( 'STACKPRESS_PAYSTACK_PUBLIC_KEY' ) ? STACKPRESS_PAYSTACK_PUBLIC_KEY : '' );
		$secret_key = isset( $settings['secret_key'] ) ? $settings['secret_key'] : ( defined( 'STACKPRESS_PAYSTACK_SECRET_KEY' ) ? STACKPRESS_PAYSTACK_SECRET_KEY : '' );

		if ( '' === $public_key || '' === $secret_key ) {
			wp_send_json_error( array( 'message' => __( 'Paystack has not been configured yet.', 'stackpress' ) ) );
		}

		$payload = array(
			'email'        => $email,
			'amount'       => $amount * 100,
			'currency'     => 'NGN',
			'callback_url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&stackpress_tip=success' ),
			'metadata'     => array(
				'source'   => 'stackpress_plugin',
				'user_id'  => get_current_user_id(),
				'page'     => self::PAGE_SLUG,
			),
		);

		$response = wp_remote_post(
			'https://api.paystack.co/transaction/initialize',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['status'] ) || empty( $body['data']['authorization_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Paystack could not start the payment flow.', 'stackpress' ) ) );
		}

		wp_send_json_success(
			array(
				'authorization_url' => $body['data']['authorization_url'],
				'reference'         => $body['data']['reference'],
				'publicKey'         => $public_key,
				'email'             => $email,
			)
		);
	}

	/**
	 * Add the top-level admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M12 12l9-5M12 12v10M12 12L3 7"/></svg>' );

		add_menu_page(
			__( 'StackPress', 'stackpress' ),
			__( 'StackPress', 'stackpress' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' ),
			$icon,
			58
		);
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url      = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'stackpress' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Enqueue dashboard assets (StackPress admin page only).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		// StackPress inlines its admin CSS/JS instead of loading separate files. This
		// guarantees the dashboard always uses the current code, even on hosts/CDNs
		// that aggressively cache (or strip query strings from) static assets.
		$css = $this->asset_contents( 'assets/admin/tabler-icons.min.css' ) . "\n" . $this->asset_contents( 'assets/admin/admin.css' );

		$tip_settings = get_option( 'stackpress_tip_settings', array() );
		$tip_public   = isset( $tip_settings['public_key'] ) ? $tip_settings['public_key'] : ( defined( 'STACKPRESS_PAYSTACK_PUBLIC_KEY' ) ? STACKPRESS_PAYSTACK_PUBLIC_KEY : '' );
		$tip_secret   = isset( $tip_settings['secret_key'] ) ? $tip_settings['secret_key'] : ( defined( 'STACKPRESS_PAYSTACK_SECRET_KEY' ) ? STACKPRESS_PAYSTACK_SECRET_KEY : '' );
		$tip_enabled  = '' !== $tip_public && '' !== $tip_secret;

		wp_register_style( 'stackpress-admin', false, array(), STACKPRESS_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline-only handle.
		wp_enqueue_style( 'stackpress-admin' );
		wp_add_inline_style( 'stackpress-admin', $css );

		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'adminUrl' => admin_url(),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'tip'      => array(
				'enabled'       => $tip_enabled,
				'currency'      => 'NGN',
				'defaultAmount' => 1000,
				'email'         => get_option( 'admin_email' ),
				'heading'       => __( 'Support StackPress', 'stackpress' ),
				'body'          => __( 'Tip the builder directly from this dashboard without leaving the page.', 'stackpress' ),
				'button'        => __( 'Pay with Paystack', 'stackpress' ),
				'disabledText'  => __( 'Set your Paystack keys to enable this tip flow.', 'stackpress' ),
			),
			'i18n'    => array(
				'enabled'      => __( 'Enabled', 'stackpress' ),
				'disabled'     => __( 'Disabled', 'stackpress' ),
				'saving'       => __( 'Saving…', 'stackpress' ),
				'saved'        => __( 'Saved', 'stackpress' ),
				'error'        => __( 'Something went wrong. Please try again.', 'stackpress' ),
				'noMatch'      => __( 'No tools match your search.', 'stackpress' ),
				'noActive'     => __( 'No tools are enabled yet. Open “Recommended setup” to get started.', 'stackpress' ),
				'allSupported' => __( 'Good news — your server supports every StackPress tool. Nothing is blocked here.', 'stackpress' ),
				'enabledLower' => __( 'enabled', 'stackpress' ),
				'configure'    => __( 'Configure', 'stackpress' ),
				'howToUse'     => __( 'How to use', 'stackpress' ),
				'readyToOpen'  => __( 'is enabled and ready', 'stackpress' ),
				'clickOpen'    => __( 'It was added to your dashboard. Click Open to configure it now.', 'stackpress' ),
				'openIt'       => __( 'Open', 'stackpress' ),
				'conflictWarn' => __( 'is already active and handles this too. Running both can conflict — enable this anyway?', 'stackpress' ),
			),
		);

		wp_register_script( 'stackpress-paystack', 'https://js.paystack.co/v1/inline.js', array(), null, true );
		wp_enqueue_script( 'stackpress-paystack' );
		wp_register_script( 'stackpress-admin', false, array(), STACKPRESS_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline-only handle.
		wp_enqueue_script( 'stackpress-admin' );
		wp_add_inline_script( 'stackpress-admin', 'window.StackPressAdmin = ' . wp_json_encode( $config ) . ';', 'before' );
		wp_add_inline_script( 'stackpress-admin', $this->asset_contents( 'assets/admin/admin.js' ) );
	}

	/**
	 * When a StackPress tool page is loaded inside the dashboard modal
	 * (?stackpress_modal=1), strip the surrounding wp-admin chrome so only the
	 * tool's own UI shows.
	 *
	 * @return void
	 */
	public function frame_chrome_css() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page check, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'stackpress' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Detect iframe context on every load (survives form redirects), then hide
		// the surrounding wp-admin chrome so only the tool's UI shows in the modal.
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( 'if(window.top!==window.self){document.documentElement.className+=" stackpress-framed";}' );
		}
		echo '<style>'
			. 'html.stackpress-framed{padding-top:0!important}'
			. 'html.stackpress-framed #adminmenumain,html.stackpress-framed #wpadminbar,html.stackpress-framed #wpfooter,html.stackpress-framed #screen-meta,html.stackpress-framed #screen-meta-links,html.stackpress-framed .update-nag,html.stackpress-framed .notice{display:none!important}'
			. 'html.stackpress-framed,html.stackpress-framed body,html.stackpress-framed #wpwrap,html.stackpress-framed #wpcontent,html.stackpress-framed #wpbody,html.stackpress-framed #wpbody-content{overflow-x:hidden!important;min-width:0!important;box-sizing:border-box!important}'
			. 'html.stackpress-framed #wpcontent,html.stackpress-framed #wpbody-content{margin-left:0!important}'
			. 'html.stackpress-framed #wpcontent{padding:0 16px 16px!important}'
			. 'html.stackpress-framed .wrap{margin:12px 0 0!important;max-width:100%!important}'
			. 'html.stackpress-framed .wrap>h1:first-child{display:none!important}'
			. 'html.stackpress-framed,html.stackpress-framed body{background:#fff!important}'
			. '</style>';
	}

	/**
	 * Read a bundled asset's contents (for inlining).
	 *
	 * @param string $rel_path Path relative to the plugin root.
	 * @return string
	 */
	private function asset_contents( $rel_path ) {
		$file = STACKPRESS_PATH . $rel_path;
		if ( ! file_exists( $file ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading our own bundled asset to inline it.
		return (string) file_get_contents( $file );
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$core       = Core::instance();
		$registry   = $core->registry();
		$modules    = $registry->all_instances();
		$categories = $registry->categories();
		$active     = $core->get_active_modules();

		require STACKPRESS_PATH . 'includes/Admin/views/dashboard.php';
	}

	/*
	 * -----------------------------------------------------------------------
	 * AJAX handlers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Verify nonce + capability for every AJAX request.
	 *
	 * @return void
	 */
	private function verify_request() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stackpress' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'stackpress' ) ), 403 );
		}
	}

	/**
	 * Enable/disable a module.
	 *
	 * @return void
	 */
	public function ajax_toggle_module() {
		$this->verify_request(); // Verifies nonce + capability for all $_POST below.

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked in verify_request().
		$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$enable    = isset( $_POST['enable'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enable'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$core    = Core::instance();
		$catalog = $core->registry()->catalog();

		if ( ! isset( $catalog[ $module_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown module.', 'stackpress' ) ), 404 );
		}

		if ( $enable ) {
			$core->enable_module( $module_id );
		} else {
			$core->disable_module( $module_id );
		}

		if ( $enable ) {
			/* translators: %s: module ID. */
			$logmsg = sprintf( __( 'Enabled module: %s', 'stackpress' ), $module_id );
		} else {
			/* translators: %s: module ID. */
			$logmsg = sprintf( __( 'Disabled module: %s', 'stackpress' ), $module_id );
		}
		$this->log_change( $logmsg );

		wp_send_json_success(
			array(
				'module' => $module_id,
				'active' => $core->is_module_active( $module_id ),
			)
		);
	}

	/**
	 * Enable or disable many modules at once.
	 *
	 * @return void
	 */
	public function ajax_bulk_toggle() {
		$this->verify_request(); // Verifies nonce + capability for all $_POST below.

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked in verify_request().
		$enable  = isset( $_POST['enable'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enable'] ) );
		$raw     = isset( $_POST['modules'] ) ? sanitize_text_field( wp_unslash( $_POST['modules'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$ids     = array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) );
		$core    = Core::instance();
		$catalog = $core->registry()->catalog();
		$changed = 0;

		foreach ( $ids as $module_id ) {
			if ( ! isset( $catalog[ $module_id ] ) ) {
				continue;
			}
			if ( $enable ) {
				if ( $core->enable_module( $module_id ) ) {
					$changed++;
				}
			} elseif ( $core->disable_module( $module_id ) ) {
				$changed++;
			}
		}

		if ( $enable ) {
			/* translators: %d: number of modules. */
			$logmsg = sprintf( __( 'Bulk enabled %d modules', 'stackpress' ), $changed );
		} else {
			/* translators: %d: number of modules. */
			$logmsg = sprintf( __( 'Bulk disabled %d modules', 'stackpress' ), $changed );
		}
		$this->log_change( $logmsg );
		wp_send_json_success( array( 'changed' => $changed ) );
	}

	/**
	 * Clear the page cache directory.
	 *
	 * @return void
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_clear_cache' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'stackpress-cache/';
		if ( is_dir( $dir ) ) {
			foreach ( (array) glob( $dir . '*.html' ) as $file ) {
				if ( 'index.html' !== basename( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $file );
				}
			}
		}
		$this->log_change( __( 'Cleared the page cache', 'stackpress' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Register the "Changes" log submenu.
	 *
	 * @return void
	 */
	public function register_changes_page() {
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Changes', 'stackpress' ),
			__( 'Changes', 'stackpress' ),
			'manage_options',
			'stackpress-changes',
			array( $this, 'render_changes_page' )
		);
	}

	/**
	 * Render the change log: everything StackPress has changed.
	 *
	 * @return void
	 */
	public function render_changes_page() {
		$log = get_option( self::CHANGE_LOG, array() );
		$log = is_array( $log ) ? $log : array();
		echo '<div class="wrap"><h1>' . esc_html__( 'StackPress changes', 'stackpress' ) . '</h1>';
		echo '<p>' . esc_html__( 'Everything StackPress has changed on your site — module toggles, settings updates, cache clears, and bulk actions.', 'stackpress' ) . '</p>';

		if ( isset( $_GET['cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Change log cleared.', 'stackpress' ) . '</p></div>';
		}

		if ( empty( $log ) ) {
			echo '<p>' . esc_html__( 'No changes recorded yet.', 'stackpress' ) . '</p></div>';
			return;
		}

		// Clear-log button.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0;" onsubmit="return confirm(\'' . esc_js( __( 'Delete the entire change log? This cannot be undone.', 'stackpress' ) ) . '\');">';
		wp_nonce_field( 'stackpress_clear_log' );
		echo '<input type="hidden" name="action" value="stackpress_clear_log" />';
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Clear log', 'stackpress' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'stackpress' ) . '</th><th>' . esc_html__( 'User', 'stackpress' ) . '</th><th>' . esc_html__( 'Change', 'stackpress' ) . '</th></tr></thead><tbody>';
		foreach ( $log as $row ) {
			$when = isset( $row['time'] ) ? sprintf( /* translators: %s: time diff. */ __( '%s ago', 'stackpress' ), human_time_diff( (int) $row['time'], time() ) ) : '';
			echo '<tr><td>' . esc_html( $when ) . '</td><td>' . esc_html( isset( $row['user'] ) ? $row['user'] : '' ) . '</td><td>' . esc_html( isset( $row['msg'] ) ? $row['msg'] : '' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Delete the entire change log.
	 *
	 * @return void
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_clear_log' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
		delete_option( self::CHANGE_LOG );
		wp_safe_redirect( admin_url( 'admin.php?page=stackpress-changes&cleared=1' ) );
		exit;
	}

	/**
	 * Return the rendered settings form for a module.
	 *
	 * @return void
	 */
	public function ajax_get_settings_form() {
		$this->verify_request(); // Verifies nonce + capability.

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in verify_request().
		$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$module    = Core::instance()->registry()->get_instance( $module_id );

		if ( ! $module ) {
			wp_send_json_error( array( 'message' => __( 'Unknown module.', 'stackpress' ) ), 404 );
		}

		$html = Settings_Renderer::render( $module );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Save a module's settings.
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		$this->verify_request(); // Verifies nonce + capability.

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in verify_request().
		$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$module    = Core::instance()->registry()->get_instance( $module_id );

		if ( ! $module ) {
			wp_send_json_error( array( 'message' => __( 'Unknown module.', 'stackpress' ) ), 404 );
		}

		// Raw values are sanitised per-field by Abstract_Module::save_settings().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce in verify_request(); values sanitised per-schema in save_settings().
		$raw   = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$saved = $module->save_settings( $raw );

		$this->log_change( sprintf( /* translators: %s: module id. */ __( 'Updated settings: %s', 'stackpress' ), $module_id ) );

		wp_send_json_success( array( 'settings' => $saved ) );
	}

	/**
	 * Register the Agency Mode page.
	 *
	 * @return void
	 */
	public function register_agency_page() {
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Agency Mode', 'stackpress' ),
			__( 'Agency Mode', 'stackpress' ),
			'manage_options',
			'stackpress-agency',
			array( $this, 'render_agency_page' )
		);
	}

	/**
	 * Enable every module in the agency bundle at once.
	 *
	 * @return void
	 */
	public function handle_enable_agency() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_enable_agency' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
		$core   = Core::instance();
		$bundle = array_keys( $this->agency_bundle() );
		$mode   = isset( $_POST['agency'] ) ? sanitize_key( wp_unslash( $_POST['agency'] ) ) : '';
		$module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';

		switch ( $mode ) {
			case 'all_on':
				foreach ( $bundle as $id ) {
					$core->enable_module( $id );
				}
				$this->log_change( __( 'Enabled all Agency Mode tools', 'stackpress' ) );
				break;
			case 'all_off':
				foreach ( $bundle as $id ) {
					$core->disable_module( $id );
				}
				$this->log_change( __( 'Disabled all Agency Mode tools', 'stackpress' ) );
				break;
			case 'one_on':
				if ( in_array( $module, $bundle, true ) ) {
					$core->enable_module( $module );
				}
				break;
			case 'one_off':
				if ( in_array( $module, $bundle, true ) ) {
					$core->disable_module( $module );
				}
				break;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=stackpress-agency&updated=1' ) );
		exit;
	}

	/**
	 * Build a small agency action form (button).
	 *
	 * @param string $mode   Action mode.
	 * @param string $label  Button label.
	 * @param string $module Module id (for one_on/one_off).
	 * @param string $class  Button class.
	 * @return string
	 */
	private function agency_button( $mode, $label, $module = '', $class = 'button' ) {
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0;">';
		$html .= wp_nonce_field( 'stackpress_enable_agency', '_wpnonce', true, false );
		$html .= '<input type="hidden" name="action" value="stackpress_enable_agency" />';
		$html .= '<input type="hidden" name="agency" value="' . esc_attr( $mode ) . '" />';
		if ( '' !== $module ) {
			$html .= '<input type="hidden" name="module" value="' . esc_attr( $module ) . '" />';
		}
		$html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
		$html .= '</form>';
		return $html;
	}

	/**
	 * Render the Agency Mode control panel.
	 *
	 * @return void
	 */
	public function render_agency_page() {
		$core   = Core::instance();
		$active = $core->get_active_modules();
		$pages  = $core->registry()->settings_pages();
		$bundle = $this->agency_bundle();

		$on_count = 0;
		foreach ( array_keys( $bundle ) as $bid ) {
			if ( in_array( $bid, $active, true ) ) {
				$on_count++;
			}
		}
		$total = count( $bundle );

		echo '<div class="wrap"><h1>' . esc_html__( 'Agency Mode', 'stackpress' ) . '</h1>';
		echo '<p>' . esc_html__( 'Everything you need to run StackPress for clients — white-labelling, backups, monthly reports, audit logging, and update control in one place.', 'stackpress' ) . '</p>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Agency Mode updated.', 'stackpress' ) . '</p></div>';
		}

		echo '<p style="font-size:13px;color:#50575e;">' . esc_html( sprintf( /* translators: 1: on, 2: total. */ __( '%1$d of %2$d agency tools enabled.', 'stackpress' ), $on_count, $total ) ) . '</p>';
		echo '<div style="margin:12px 0 24px;display:flex;gap:8px;">';
		echo $this->agency_button( 'all_on', __( 'Enable all', 'stackpress' ), '', 'button button-primary button-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaping.
		echo $this->agency_button( 'all_off', __( 'Disable all', 'stackpress' ), '', 'button button-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;">';
		foreach ( $bundle as $id => $label ) {
			$on = in_array( $id, $active, true );
			echo '<div style="background:#fff;border:1px solid #e4e7ec;border-radius:10px;padding:16px;">';
			echo '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">';
			echo '<strong>' . esc_html( $label ) . '</strong>';
			echo '<span style="font-size:11px;padding:2px 9px;border-radius:10px;' . ( $on ? 'background:#eaf3de;color:#3b6d11;' : 'background:#f1efe8;color:#888;' ) . '">' . ( $on ? esc_html__( 'On', 'stackpress' ) : esc_html__( 'Off', 'stackpress' ) ) . '</span>';
			echo '</div>';
			echo '<div style="display:flex;gap:8px;align-items:center;">';
			if ( $on ) {
				echo $this->agency_button( 'one_off', __( 'Disable', 'stackpress' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( isset( $pages[ $id ] ) ) {
					echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . $pages[ $id ] ) ) . '">' . esc_html__( 'Open', 'stackpress' ) . '</a>';
				}
			} else {
				echo $this->agency_button( 'one_on', __( 'Enable', 'stackpress' ), $id, 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
	}
}
