<?php
/**
 * WP-Cron Control module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Site;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Disables the default page-load-triggered WP-Cron so a real server cron can run
 * it instead — steadier performance on busy sites. Shows the command to use.
 */
final class WP_Cron_Control extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'wp_cron_control';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'WP-Cron control', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Disable the visitor-triggered WP-Cron so you can run it from a real server cron.', 'stackpress' );
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
			'php_memory_kb' => 12,
			'front_js_kb'   => 0,
			'front_css_kb'  => 0,
			'db_queries'    => 0,
			'external_http' => 0,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings_schema() {
		return array(
			array(
				'key'     => 'note',
				'label'   => __( 'Server cron command (for your reference)', 'stackpress' ),
				'type'    => 'text',
				'default' => 'wget -q -O - ' . site_url( 'wp-cron.php?doing_wp_cron' ) . ' >/dev/null 2>&1',
				'help'    => __( 'Add this to your hosting cron (e.g. every 15 minutes) after enabling. WP-Cron will not run on page loads while this module is on.', 'stackpress' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		// Prevent WordPress from spawning its pseudo-cron on page loads.
		if ( ! defined( 'DISABLE_WP_CRON' ) ) {
			define( 'DISABLE_WP_CRON', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DISABLE_WP_CRON is a WordPress core constant.
		}
	}
}
