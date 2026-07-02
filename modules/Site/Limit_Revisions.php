<?php
/**
 * Limit Post Revisions module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Site;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Caps how many revisions WordPress keeps per post, preventing database bloat.
 */
final class Limit_Revisions extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'limit_revisions';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Limit post revisions', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Keep only the most recent revisions per post to reduce database bloat.', 'stackpress' );
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
		return 'database';
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
				'key'     => 'keep',
				'label'   => __( 'Revisions to keep per post', 'stackpress' ),
				'type'    => 'number',
				'default' => 5,
				'min'     => 0,
				'max'     => 100,
				'step'    => 1,
				'help'    => __( '0 disables revisions entirely.', 'stackpress' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_filter( 'wp_revisions_to_keep', array( $this, 'limit' ), 10, 2 );
	}

	/**
	 * Return the configured revision cap.
	 *
	 * @param int      $num  Current limit.
	 * @param \WP_Post $post Post being saved.
	 * @return int
	 */
	public function limit( $num, $post ) {
		return (int) $this->get_setting( 'keep', 5 );
	}
}
