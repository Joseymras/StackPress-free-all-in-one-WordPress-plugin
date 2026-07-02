<?php
/**
 * Autosave Interval module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Admin;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Adjusts how often the editor autosaves. A longer interval reduces background
 * AJAX requests on busy or low-powered servers.
 */
final class Autosave_Interval extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'autosave_interval';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Autosave interval', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Change how often the editor autosaves to reduce background requests.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'admin';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'bolt';
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 10,
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
				'key'     => 'seconds',
				'label'   => __( 'Autosave interval (seconds)', 'stackpress' ),
				'type'    => 'number',
				'default' => 120,
				'min'     => 30,
				'max'     => 600,
				'step'    => 10,
				'help'    => __( 'WordPress default is 60 seconds.', 'stackpress' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_filter( 'autosave_interval', array( $this, 'interval' ) );
	}

	/**
	 * Provide the configured interval.
	 *
	 * @param int $seconds Default seconds.
	 * @return int
	 */
	public function interval( $seconds ) {
		return max( 30, (int) $this->get_setting( 'seconds', 120 ) );
	}
}
