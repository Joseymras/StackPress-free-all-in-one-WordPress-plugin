<?php
/**
 * Object Cache module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Performance;

use StackPress\Modules\Abstract_Module;
use StackPress\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * Persistent object cache backed by Redis or Memcached. When a backend is
 * available it installs a fail-safe object-cache.php drop-in (which falls back
 * to a normal non-persistent cache if the server ever goes away, so the site
 * never breaks). Shown only when the server actually offers Redis/Memcached.
 */
final class Object_Cache extends Abstract_Module {

	/**
	 * Signature that marks our drop-in (so we never delete another plugin's).
	 */
	const SIGNATURE = 'STACKPRESS_OBJECT_CACHE_DROPIN';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'object_cache';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Object cache (Redis / Memcached)', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Cache database queries in Redis or Memcached so pages build far faster. Auto-detected on your server.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'performance';
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
	public function replaces() {
		return 'premium object-cache plugins';
	}

	/**
	 * {@inheritDoc}
	 */
	public function requirements() {
		return array( 'object_cache_backend' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 60,
			'front_js_kb'   => 0,
			'front_css_kb'  => 0,
			'db_queries'    => -8,
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
		add_action( 'admin_post_stackpress_oc_install', array( $this, 'handle_install' ) );
		add_action( 'admin_post_stackpress_oc_remove', array( $this, 'handle_remove' ) );
		add_action( 'admin_post_stackpress_oc_flush', array( $this, 'handle_flush' ) );
		// Remove our drop-in when the module is switched off.
		add_action( 'stackpress_module_disabled_' . $this->id(), array( $this, 'remove_dropin' ) );
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'stackpress',
			__( 'Object cache', 'stackpress' ),
			__( 'Object cache', 'stackpress' ),
			'manage_options',
			'stackpress-object-cache',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Absolute path of the bundled drop-in source.
	 *
	 * @return string
	 */
	private function source() {
		return STACKPRESS_PATH . 'modules/Performance/dropins/object-cache.php';
	}

	/**
	 * Absolute path of the installed drop-in.
	 *
	 * @return string
	 */
	private function target() {
		return WP_CONTENT_DIR . '/object-cache.php';
	}

	/**
	 * Is OUR drop-in currently installed?
	 *
	 * @return bool
	 */
	private function ours_installed() {
		$target = $this->target();
		if ( ! file_exists( $target ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the drop-in we may own.
		$head = (string) file_get_contents( $target );
		return false !== strpos( $head, self::SIGNATURE );
	}

	/**
	 * Render the object-cache control page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}

		$backend_ok = Environment::has( 'object_cache_backend' );
		$dropin      = Environment::object_cache_dropin();
		$ours        = $this->ours_installed();
		$active       = Environment::object_cache_active();
		$blocked      = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;

		echo '<div class="wrap"><h1>' . esc_html__( 'Object cache', 'stackpress' ) . '</h1>';
		echo '<p>' . esc_html__( 'A persistent object cache stores the results of expensive database queries in memory (Redis or Memcached), so WordPress rebuilds pages much faster.', 'stackpress' ) . '</p>';

		// Status line.
		echo '<table class="widefat striped" style="max-width:680px;margin:14px 0;"><tbody>';
		$this->status_row( __( 'Redis / Memcached on server', 'stackpress' ), $backend_ok );
		$this->status_row( __( 'Drop-in installed', 'stackpress' ), $dropin, $dropin && ! $ours ? __( 'A different object-cache.php is installed (another plugin). StackPress will not touch it.', 'stackpress' ) : '' );
		$this->status_row( __( 'Persistent object cache active', 'stackpress' ), $active );
		echo '</tbody></table>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['msg'] ) ) {
			$messages = array(
				'installed' => __( 'Object cache drop-in installed. Your site is now using persistent caching.', 'stackpress' ),
				'removed'   => __( 'Object cache drop-in removed.', 'stackpress' ),
				'flushed'   => __( 'Object cache flushed.', 'stackpress' ),
				'failed'    => __( 'Could not write the drop-in. Check that wp-content is writable, or that file edits are not disabled.', 'stackpress' ),
			);
			$key = sanitize_key( wp_unslash( $_GET['msg'] ) );
			if ( isset( $messages[ $key ] ) ) {
				$class = 'failed' === $key ? 'notice-error' : 'notice-success';
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $backend_ok ) {
			$map = Environment::map();
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $map['object_cache_backend']['hint'] ) . '</p></div></div>';
			return;
		}

		if ( $blocked ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'File modifications are disabled on this site (DISALLOW_FILE_MODS), so the drop-in cannot be installed automatically.', 'stackpress' ) . '</p></div></div>';
			return;
		}

		echo '<p>';
		if ( ! $dropin ) {
			echo $this->action_button( 'stackpress_oc_install', __( 'Enable persistent object cache', 'stackpress' ), 'button button-primary button-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( $ours ) {
			echo $this->action_button( 'stackpress_oc_flush', __( 'Flush cache', 'stackpress' ), 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo ' ';
			echo $this->action_button( 'stackpress_oc_remove', __( 'Remove drop-in', 'stackpress' ), 'button button-secondary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</p>';

		echo '<p style="color:#50575e;font-size:13px;max-width:680px;">' . esc_html__( 'Tip: set WP_REDIS_HOST / WP_REDIS_PORT (or WP_CACHE_HOST / WP_CACHE_PORT for Memcached) in wp-config.php if your cache server is not on 127.0.0.1. If the cache server ever stops responding, StackPress automatically falls back to normal caching — your site stays online.', 'stackpress' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render one status row.
	 *
	 * @param string $label Row label.
	 * @param bool   $ok    State.
	 * @param string $note  Optional note.
	 * @return void
	 */
	private function status_row( $label, $ok, $note = '' ) {
		echo '<tr><td style="width:60%;">' . esc_html( $label ) . ( $note ? ' <span style="color:#a36a00;">— ' . esc_html( $note ) . '</span>' : '' ) . '</td>';
		echo '<td>' . ( $ok
			? '<span style="color:#3b6d11;font-weight:600;">' . esc_html__( 'Yes', 'stackpress' ) . '</span>'
			: '<span style="color:#a32d2d;font-weight:600;">' . esc_html__( 'No', 'stackpress' ) . '</span>' );
		echo '</td></tr>';
	}

	/**
	 * Build a nonce-protected action button.
	 *
	 * @param string $action Action name.
	 * @param string $label  Button label.
	 * @param string $class  Button class.
	 * @return string
	 */
	private function action_button( $action, $label, $class ) {
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0;">';
		$html .= wp_nonce_field( $action, '_wpnonce', true, false );
		$html .= '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		$html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
		$html .= '</form>';
		return $html;
	}

	/**
	 * Install the drop-in.
	 *
	 * @return void
	 */
	public function handle_install() {
		$this->guard( 'stackpress_oc_install' );
		$msg = 'failed';
		if ( ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) && ! Environment::object_cache_dropin() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading our bundled drop-in.
			$src = file_get_contents( $this->source() );
			if ( false !== $src ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== @file_put_contents( $this->target(), $src, LOCK_EX ) ) {
					$msg = 'installed';
				}
			}
		}
		$this->redirect( $msg );
	}

	/**
	 * Remove our drop-in (only if it is ours).
	 *
	 * @return void
	 */
	public function handle_remove() {
		$this->guard( 'stackpress_oc_remove' );
		$this->remove_dropin();
		$this->redirect( 'removed' );
	}

	/**
	 * Flush the object cache.
	 *
	 * @return void
	 */
	public function handle_flush() {
		$this->guard( 'stackpress_oc_flush' );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		$this->redirect( 'flushed' );
	}

	/**
	 * Delete our drop-in if present (safe: never touches a third-party one).
	 *
	 * @return void
	 */
	public function remove_dropin() {
		if ( $this->ours_installed() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $this->target() );
		}
	}

	/**
	 * Capability + nonce guard.
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $action ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackpress' ) );
		}
	}

	/**
	 * Redirect back to the page with a message.
	 *
	 * @param string $msg Message key.
	 * @return void
	 */
	private function redirect( $msg ) {
		wp_safe_redirect( admin_url( 'admin.php?page=stackpress-object-cache&msg=' . $msg ) );
		exit;
	}
}
