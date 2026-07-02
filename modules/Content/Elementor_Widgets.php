<?php
/**
 * Elementor Widgets integration module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Content;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes StackPress features (reviews, FAQ, contact form, breadcrumbs) as native
 * Elementor widgets when Elementor is active. Completely inert if Elementor is
 * not installed, so it can never break a non-Elementor site.
 */
final class Elementor_Widgets extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'elementor_widgets';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Elementor widgets', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Use StackPress reviews, FAQ, contact form, and breadcrumbs as native Elementor widgets.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'content';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'puzzle';
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
		// Only do anything when Elementor is present.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register StackPress widgets with Elementor.
	 *
	 * @param mixed $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		if ( ! class_exists( '\StackPress_Elementor_Shortcode_Widget' ) ) {
			$this->define_widget_class();
		}

		$specs = array(
			array( 'stackpress_reviews', __( 'StackPress Reviews', 'stackpress' ), 'stackpress_reviews' ),
			array( 'stackpress_faq', __( 'StackPress FAQ', 'stackpress' ), 'stackpress_faq' ),
			array( 'stackpress_contact', __( 'StackPress Contact Form', 'stackpress' ), 'stackpress_contact' ),
			array( 'stackpress_breadcrumbs', __( 'StackPress Breadcrumbs', 'stackpress' ), 'stackpress_breadcrumbs' ),
		);

		foreach ( $specs as $spec ) {
			try {
				$widget = new \StackPress_Elementor_Shortcode_Widget(
					array(),
					array(
						'stackpress_name'      => $spec[0],
						'stackpress_title'     => $spec[1],
						'stackpress_shortcode' => $spec[2],
					)
				);
				$widgets_manager->register( $widget );
			} catch ( \Throwable $e ) {
				// Elementor version mismatch — skip this widget rather than fatal.
				continue;
			}
		}
	}

	/**
	 * Load the generic shortcode-backed Elementor widget class. The file is only
	 * required here, inside the elementor/widgets/register hook, so the class
	 * (which extends an Elementor base class) only ever loads when Elementor is
	 * active.
	 *
	 * @return void
	 */
	private function define_widget_class() {
		require_once __DIR__ . '/elementor-widget.php';
	}
}
