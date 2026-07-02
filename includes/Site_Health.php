<?php
/**
 * WordPress Site Health integration.
 *
 * @package StackPress
 */

namespace StackPress;

defined( 'ABSPATH' ) || exit;

/**
 * Surfaces StackPress status inside Tools → Site Health, so the value (and any
 * problems) show up where WordPress already guides site owners.
 */
final class Site_Health {

	/**
	 * Hook into Site Health.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'site_status_tests', array( $this, 'register_tests' ) );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/**
	 * Register StackPress direct tests.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public function register_tests( $tests ) {
		$tests['direct']['stackpress_failures']     = array(
			'label' => __( 'StackPress module errors', 'stackpress' ),
			'test'  => array( $this, 'test_failures' ),
		);
		$tests['direct']['stackpress_object_cache'] = array(
			'label' => __( 'StackPress persistent object cache', 'stackpress' ),
			'test'  => array( $this, 'test_object_cache' ),
		);
		return $tests;
	}

	/**
	 * Test: were any modules auto-disabled after an error?
	 *
	 * @return array
	 */
	public function test_failures() {
		$fails  = get_option( Core::FAILURES_OPTION, array() );
		$result = array(
			'label'       => __( 'No StackPress tools have errored', 'stackpress' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'StackPress', 'stackpress' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'All enabled StackPress tools are loading without errors.', 'stackpress' ) . '</p>',
			'test'        => 'stackpress_failures',
		);

		if ( ! empty( $fails ) && is_array( $fails ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Some StackPress tools were turned off after an error', 'stackpress' );
			$result['badge']['color'] = 'orange';
			$result['description']    = '<p>' . esc_html__( 'StackPress automatically disabled one or more tools that caused an error, so your site kept running. Review them on the StackPress dashboard.', 'stackpress' ) . '</p>';
		}
		return $result;
	}

	/**
	 * Test: is a persistent object cache active, and could one be used?
	 *
	 * @return array
	 */
	public function test_object_cache() {
		$active    = Environment::object_cache_active();
		$available = Environment::has( 'object_cache_backend' );

		$result = array(
			'label'       => __( 'A persistent object cache is in use', 'stackpress' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'StackPress', 'stackpress' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Database query results are cached in memory for faster page builds.', 'stackpress' ) . '</p>',
			'test'        => 'stackpress_object_cache',
		);

		if ( ! $active ) {
			if ( $available ) {
				$result['status']         = 'recommended';
				$result['label']          = __( 'A persistent object cache is available but not enabled', 'stackpress' );
				$result['badge']['color'] = 'orange';
				$result['description']    = '<p>' . esc_html__( 'Your server supports Redis or Memcached. Enable StackPress’s Object cache tool to speed up your site.', 'stackpress' ) . '</p>';
				$result['actions']        = '<p><a href="' . esc_url( admin_url( 'admin.php?page=stackpress-object-cache' ) ) . '">' . esc_html__( 'Open StackPress object cache', 'stackpress' ) . '</a></p>';
			} else {
				$result['status']      = 'good';
				$result['label']       = __( 'No persistent object cache (your host does not offer one)', 'stackpress' );
				$result['description'] = '<p>' . esc_html__( 'This is normal on many hosts and is not a problem.', 'stackpress' ) . '</p>';
			}
		}
		return $result;
	}

	/**
	 * Add an StackPress section to Site Health → Info (handy for support).
	 *
	 * @param array $info Existing debug info.
	 * @return array
	 */
	public function debug_information( $info ) {
		$core    = Core::instance();
		$active  = $core->get_active_modules();
		$info['stackpress'] = array(
			'label'  => 'StackPress',
			'fields' => array(
				'version'        => array(
					'label' => __( 'Version', 'stackpress' ),
					'value' => STACKPRESS_VERSION,
				),
				'active_modules' => array(
					'label' => __( 'Active tools', 'stackpress' ),
					'value' => count( $active ),
				),
				'active_list'    => array(
					'label' => __( 'Active tool IDs', 'stackpress' ),
					'value' => empty( $active ) ? '—' : implode( ', ', $active ),
				),
			),
		);
		return $info;
	}
}
