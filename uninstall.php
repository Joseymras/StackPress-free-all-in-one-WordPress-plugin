<?php
/**
 * StackPress uninstall routine.
 *
 * Removes all StackPress options so an uninstall leaves no orphaned data
 * (WordPress.org best practice). Module-created tables/cron are cleaned up by
 * each module on disable; this is the final sweep of the options table.
 *
 * @package StackPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Core options.
delete_option( 'stackpress_active_modules' );
delete_option( 'stackpress_setup_complete' );
delete_option( 'stackpress_db_last_cleanup' );
delete_option( 'stackpress_activity_log' );
delete_option( 'stackpress_error_log' );
delete_option( 'stackpress_shortcodes' );
delete_option( 'stackpress_reviews' );
delete_option( 'stackpress_change_log' );
delete_option( 'stackpress_module_failures' );
delete_option( 'stackpress_htaccess_backup' );
delete_option( 'stackpress_styler' );
delete_option( 'stackpress_404_log' );
delete_option( 'stackpress_404_redirects' );
delete_option( 'stackpress_last_backup' );
delete_option( 'stackpress_cloud_last' );

// Scheduled events created by modules.
wp_clear_scheduled_hook( 'stackpress_db_cleanup' );
wp_clear_scheduled_hook( 'stackpress_update_check' );
wp_clear_scheduled_hook( 'stackpress_scheduled_backup' );
wp_clear_scheduled_hook( 'stackpress_monthly_report' );
wp_clear_scheduled_hook( 'stackpress_cache_preload' );

// Remove our object-cache drop-in (only if it carries our signature).
$stackpress_dropin = WP_CONTENT_DIR . '/object-cache.php';
if ( file_exists( $stackpress_dropin ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$stackpress_head = (string) file_get_contents( $stackpress_dropin );
	if ( false !== strpos( $stackpress_head, 'STACKPRESS_OBJECT_CACHE_DROPIN' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $stackpress_dropin );
	}
}

// Cache directories (page cache + minified CSS/JS).
$stackpress_uploads = wp_get_upload_dir();
foreach ( array( 'stackpress-cache', 'stackpress-min' ) as $stackpress_cache_dir ) {
	$stackpress_path = trailingslashit( $stackpress_uploads['basedir'] ) . $stackpress_cache_dir;
	if ( is_dir( $stackpress_path ) ) {
		$stackpress_files = glob( $stackpress_path . '/*' );
		if ( is_array( $stackpress_files ) ) {
			foreach ( $stackpress_files as $stackpress_file ) {
				if ( is_file( $stackpress_file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- removing our own cache file on uninstall.
					@unlink( $stackpress_file );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing our own empty cache dir on uninstall.
		@rmdir( $stackpress_path );
	}
}

// All per-module settings (stackpress_settings_*).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'stackpress\\_settings\\_%'"
);

// Transients created by modules (e.g. login lockouts).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '%\\_transient\\_stackpress\\_%' OR option_name LIKE '%\\_transient\\_timeout\\_stackpress\\_%'"
);

// Post meta written by SEO module.
delete_post_meta_by_key( '_stackpress_meta_title' );
delete_post_meta_by_key( '_stackpress_meta_description' );
delete_post_meta_by_key( '_stackpress_views' );

// Stored contact-form entries and newsletter subscribers (custom post types).
foreach ( array( 'stackpress_entry', 'stackpress_subscriber', 'stackpress_form_log' ) as $stackpress_cpt ) {
	$stackpress_posts = get_posts(
		array(
			'post_type'   => $stackpress_cpt,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $stackpress_posts as $stackpress_post_id ) {
		wp_delete_post( $stackpress_post_id, true );
	}
}
