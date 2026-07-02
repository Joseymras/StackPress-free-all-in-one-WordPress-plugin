<?php
/**
 * Generic shortcode-backed Elementor widget.
 *
 * This file is only ever loaded from inside the `elementor/widgets/register`
 * hook (see Elementor_Widgets::register_widgets), so \Elementor\Widget_Base is
 * guaranteed to exist at that point.
 *
 * @package StackPress
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( '\Elementor\Widget_Base' ) && ! class_exists( 'StackPress_Elementor_Shortcode_Widget' ) ) {

	/**
	 * Renders an StackPress shortcode as an Elementor widget.
	 */
	class StackPress_Elementor_Shortcode_Widget extends \Elementor\Widget_Base { // phpcs:ignore

		/**
		 * StackPress widget name.
		 *
		 * @var string
		 */
		private $stackpress_name;

		/**
		 * StackPress widget title.
		 *
		 * @var string
		 */
		private $stackpress_title;

		/**
		 * Shortcode to render.
		 *
		 * @var string
		 */
		private $stackpress_shortcode;

		/**
		 * Constructor.
		 *
		 * @param array $data Widget data.
		 * @param mixed $args Widget args (carries our stackpress_* keys).
		 */
		public function __construct( $data = array(), $args = null ) {
			$this->stackpress_name      = isset( $args['stackpress_name'] ) ? $args['stackpress_name'] : 'stackpress_widget';
			$this->stackpress_title     = isset( $args['stackpress_title'] ) ? $args['stackpress_title'] : 'StackPress';
			$this->stackpress_shortcode = isset( $args['stackpress_shortcode'] ) ? $args['stackpress_shortcode'] : '';
			parent::__construct( $data, $args );
		}

		/**
		 * Widget machine name.
		 *
		 * @return string
		 */
		public function get_name() {
			return $this->stackpress_name;
		}

		/**
		 * Widget display title.
		 *
		 * @return string
		 */
		public function get_title() {
			return $this->stackpress_title;
		}

		/**
		 * Widget icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-shortcode';
		}

		/**
		 * Widget categories.
		 *
		 * @return string[]
		 */
		public function get_categories() {
			return array( 'general' );
		}

		/**
		 * Render the widget on the front end.
		 *
		 * @return void
		 */
		protected function render() {
			if ( '' !== $this->stackpress_shortcode ) {
				echo do_shortcode( '[' . $this->stackpress_shortcode . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is escaped within each module.
			}
		}
	}
}
