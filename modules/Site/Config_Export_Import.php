<?php
/**
 * Configuration Export / Import module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Site;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Export your StackPress configuration (active modules + all module settings) to a
 * JSON file, and import it on another site — ideal for agencies deploying a
 * standard setup across client sites.
 */
final class Config_Export_Import extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'config_export_import';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Config import / export', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Export your StackPress setup to JSON and import it on another site.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'site';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'server';
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 18,
			'front_js_kb'   => 0,
			'front_css_kb'  => 0,
			'db_queries'    => 0,
			'external_http' => 0,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_stackpress_export_config', array( $this, 'handle_export' ) );
		add_action( 'admin_post_stackpress_import_config', array( $this, 'handle_import' ) );
	}

	/**
	 * Register the submenu page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'stackpress',
			__( 'Import / Export', 'stackpress' ),
			__( 'Import / Export', 'stackpress' ),
			'manage_options',
			'stackpress-config',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the import/export page.
	 *
	 * @return void
	 */
	public function render_page() {
		$notice = isset( $_GET['stackpress_imported'] ) ? sanitize_text_field( wp_unslash( $_GET['stackpress_imported'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag.
		echo '<div class="wrap"><h1>' . esc_html__( 'StackPress import / export', 'stackpress' ) . '</h1>';

		if ( 'ok' === $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configuration imported successfully.', 'stackpress' ) . '</p></div>';
		} elseif ( 'error' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not import that file. Please upload a valid StackPress export.', 'stackpress' ) . '</p></div>';
		}

		// Export.
		echo '<h2>' . esc_html__( 'Export', 'stackpress' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'stackpress_export_config' );
		echo '<input type="hidden" name="action" value="stackpress_export_config" />';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Download configuration', 'stackpress' ) . '</button></p>';
		echo '</form>';

		// Import.
		echo '<h2>' . esc_html__( 'Import', 'stackpress' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'stackpress_import_config' );
		echo '<input type="hidden" name="action" value="stackpress_import_config" />';
		echo '<p><input type="file" name="stackpress_config_file" accept="application/json,.json" required /></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Import configuration', 'stackpress' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * Stream the configuration as a JSON download.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_export_config' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}

		global $wpdb;
		$data = array(
			'plugin'  => 'stackpress',
			'version' => STACKPRESS_VERSION,
			'active'  => get_option( \StackPress\Core::ACTIVE_OPTION, array() ),
			'settings' => array(),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'stackpress\\_settings\\_%'" );
		foreach ( $rows as $row ) {
			$data['settings'][ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=stackpress-config-' . gmdate( 'Ymd' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import a configuration file.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'stackpress_import_config' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}

		$redirect = admin_url( 'admin.php?page=stackpress-config' );

		if ( empty( $_FILES['stackpress_config_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'stackpress_imported', 'error', $redirect ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- temp path validated via is_uploaded_file below.
		$tmp_name = $_FILES['stackpress_config_file']['tmp_name'];
		if ( ! is_uploaded_file( $tmp_name ) ) {
			wp_safe_redirect( add_query_arg( 'stackpress_imported', 'error', $redirect ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the verified uploaded temp file.
		$raw  = file_get_contents( $tmp_name );
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'stackpress' !== $data['plugin'] ) {
			wp_safe_redirect( add_query_arg( 'stackpress_imported', 'error', $redirect ) );
			exit;
		}

		if ( isset( $data['active'] ) && is_array( $data['active'] ) ) {
			update_option( \StackPress\Core::ACTIVE_OPTION, array_map( 'sanitize_key', $data['active'] ) );
		}
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$registry = \StackPress\Core::instance()->registry();
			foreach ( $data['settings'] as $name => $value ) {
				$name = sanitize_key( (string) $name );
				if ( 0 !== strpos( $name, 'stackpress_settings_' ) || ! is_array( $value ) ) {
					continue;
				}
				// Route each imported value through the owning module's schema
				// sanitiser rather than trusting the file contents.
				$module_id = substr( $name, strlen( 'stackpress_settings_' ) );
				$module    = $registry->get_instance( $module_id );
				if ( $module ) {
					$module->save_settings( $value );
				}
			}
		}

		wp_safe_redirect( add_query_arg( 'stackpress_imported', 'ok', $redirect ) );
		exit;
	}
}
