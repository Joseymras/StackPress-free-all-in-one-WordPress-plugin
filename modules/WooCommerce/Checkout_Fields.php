<?php
/**
 * WooCommerce Checkout Fields module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\WooCommerce;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Removes or makes optional the commonly-unwanted default checkout fields
 * (company, address line 2, phone) without code. Replaces the basic case of
 * WooCommerce Checkout Field Editor.
 */
final class Checkout_Fields extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'wc_checkout_fields';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Checkout field editor', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Remove or make optional the company, address-2, and phone checkout fields.', 'stackpress' );
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
		return 'forms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function replaces() {
		return 'premium checkout-field editors';
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
			'php_memory_kb' => 35,
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
		$options = array(
			'keep'     => __( 'Keep as is', 'stackpress' ),
			'optional' => __( 'Make optional', 'stackpress' ),
			'remove'   => __( 'Remove', 'stackpress' ),
		);
		return array(
			array(
				'key'     => 'company',
				'label'   => __( 'Company field', 'stackpress' ),
				'type'    => 'select',
				'default' => 'keep',
				'options' => $options,
			),
			array(
				'key'     => 'address_2',
				'label'   => __( 'Address line 2 field', 'stackpress' ),
				'type'    => 'select',
				'default' => 'keep',
				'options' => $options,
			),
			array(
				'key'     => 'phone',
				'label'   => __( 'Phone field', 'stackpress' ),
				'type'    => 'select',
				'default' => 'keep',
				'options' => $options,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'edit_fields' ) );
	}

	/**
	 * Apply the configured changes to billing fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function edit_fields( $fields ) {
		$map = array(
			'company'   => 'billing_company',
			'address_2' => 'billing_address_2',
			'phone'     => 'billing_phone',
		);

		foreach ( $map as $setting => $field_key ) {
			$action = $this->get_setting( $setting, 'keep' );
			if ( 'remove' === $action ) {
				unset( $fields['billing'][ $field_key ] );
			} elseif ( 'optional' === $action && isset( $fields['billing'][ $field_key ] ) ) {
				$fields['billing'][ $field_key ]['required'] = false;
			}
		}

		return $fields;
	}
}
