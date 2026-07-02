<?php
/**
 * Plugin Name:       StackPress
 * Plugin URI:        https://github.com/IamRamgarhia/StackPress-free-all-in-one-WordPress-plugin
 * Description:       One plugin. Every tool. Always free. 170+ modular tools for security, performance, SEO, forms, WooCommerce, and site management — turn on only what you need. By Josey Mras.
 * Version:           1.5.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Josey Mras
 * Author URI:        https://github.com/IamRamgarhia
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stackpress
 * Domain Path:       /languages
 *
 * @package StackPress
 * @author  Josey Mras <joseymras88@gmail.com>
 * @link    https://github.com/IamRamgarhia/StackPress-free-all-in-one-WordPress-plugin
 */

namespace StackPress;

defined( 'ABSPATH' ) || exit;

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 */
define( 'STACKPRESS_VERSION', '1.5.4' );
define( 'STACKPRESS_FILE', __FILE__ );
define( 'STACKPRESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'STACKPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'STACKPRESS_BASENAME', plugin_basename( __FILE__ ) );

/*
 * ---------------------------------------------------------------------------
 * Autoloader
 *
 * Maps the StackPress\ namespace to /includes and /modules. We intentionally avoid
 * Composer's vendor autoloader so the directory reviewers see only first-party,
 * human-readable code (WordPress.org guideline: no bundled libraries we don't
 * need, no obfuscation).
 * ---------------------------------------------------------------------------
 */
spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'StackPress\\' ) !== 0 ) {
			return;
		}

		// Strip the root namespace.
		$relative = substr( $class, strlen( 'StackPress\\' ) );

		// Modules live under /modules; everything else under /includes.
		if ( strpos( $relative, 'Modules\\' ) === 0 ) {
			$relative = substr( $relative, strlen( 'Modules\\' ) );
			$base     = STACKPRESS_PATH . 'modules/';
		} else {
			$base = STACKPRESS_PATH . 'includes/';
		}

		$path = $base . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/*
 * ---------------------------------------------------------------------------
 * Lifecycle hooks
 * ---------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( '\StackPress\Core', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( '\StackPress\Core', 'on_deactivate' ) );

/*
 * ---------------------------------------------------------------------------
 * Boot
 *
 * Priority 1 on plugins_loaded so security/performance modules can hook early.
 * ---------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function () {
		Core::instance()->boot();
	},
	1
);
