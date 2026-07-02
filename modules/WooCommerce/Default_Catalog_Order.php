<?php
/**
 * WooCommerce Default Catalog Order module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\WooCommerce;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Sets the default product sort order for shop and category pages.
 */
final class Default_Catalog_Order extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'wc_default_catalog_order';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Default catalog sorting', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Choose how products are sorted by default in the shop.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'shopping-cart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies() {
		return array( 'woocommerce' );
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
				'key'     => 'orderby',
				'label'   => __( 'Default sorting', 'stackpress' ),
				'type'    => 'select',
				'default' => 'menu_order',
				'options' => array(
					'menu_order' => __( 'Default (custom order)', 'stackpress' ),
					'popularity' => __( 'Most popular', 'stackpress' ),
					'rating'     => __( 'Average rating', 'stackpress' ),
					'date'       => __( 'Newest first', 'stackpress' ),
					'price'      => __( 'Price: low to high', 'stackpress' ),
					'price-desc' => __( 'Price: high to low', 'stackpress' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_filter( 'woocommerce_default_catalog_orderby', array( $this, 'orderby' ) );
	}

	/**
	 * Provide the configured default ordering.
	 *
	 * @param string $default Current default.
	 * @return string
	 */
	public function orderby( $default ) {
		$value = (string) $this->get_setting( 'orderby', 'menu_order' );
		return $value ? $value : $default;
	}
}
