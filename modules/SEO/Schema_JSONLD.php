<?php
/**
 * Schema / JSON-LD structured data module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\SEO;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs Schema.org JSON-LD (Organization/WebSite, Article, BreadcrumbList).
 * This is the feature the research found is paywalled almost everywhere —
 * StackPress ships it free.
 */
final class Schema_JSONLD extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'schema_jsonld';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Schema / structured data', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Add JSON-LD schema for rich results: organization, articles, and breadcrumbs.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'seo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function replaces() {
		return 'premium schema plugins';
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 80,
			'front_js_kb'   => 0,
			'front_css_kb'  => 0,
			'db_queries'    => 1,
			'external_http' => 0,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings_schema() {
		return array(
			array(
				'key'     => 'org_type',
				'label'   => __( 'Site represents', 'stackpress' ),
				'type'    => 'select',
				'default' => 'Organization',
				'options' => array(
					'Organization' => __( 'An organization / business', 'stackpress' ),
					'Person'       => __( 'A person', 'stackpress' ),
				),
			),
			array(
				'key'     => 'org_name',
				'label'   => __( 'Name', 'stackpress' ),
				'type'    => 'text',
				'default' => '',
				'help'    => __( 'Leave blank to use your site title.', 'stackpress' ),
			),
			array(
				'key'     => 'logo',
				'label'   => __( 'Logo URL', 'stackpress' ),
				'type'    => 'url',
				'default' => '',
			),
			array(
				'key'     => 'article_schema',
				'label'   => __( 'Output Article schema on posts', 'stackpress' ),
				'type'    => 'toggle',
				'default' => true,
			),
			array(
				'key'     => 'breadcrumb_schema',
				'label'   => __( 'Output BreadcrumbList schema', 'stackpress' ),
				'type'    => 'toggle',
				'default' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'wp_head', array( $this, 'output' ), 5 );
	}

	/**
	 * Print the JSON-LD graph.
	 *
	 * @return void
	 */
	public function output() {
		$s     = $this->get_settings();
		$graph = array();

		// Organization / Person + WebSite (site-wide).
		$name = $s['org_name'] ? $s['org_name'] : get_bloginfo( 'name' );
		$org  = array(
			'@type' => $s['org_type'] ? $s['org_type'] : 'Organization',
			'@id'   => home_url( '/#identity' ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);
		if ( ! empty( $s['logo'] ) ) {
			$org['logo'] = $s['logo'];
		}
		$graph[] = $org;

		$graph[] = array(
			'@type'     => 'WebSite',
			'@id'       => home_url( '/#website' ),
			'url'       => home_url( '/' ),
			'name'      => get_bloginfo( 'name' ),
			'publisher' => array( '@id' => home_url( '/#identity' ) ),
		);

		// Article schema on single posts.
		if ( ! empty( $s['article_schema'] ) && is_singular( 'post' ) ) {
			$post    = get_post();
			$graph[] = array(
				'@type'         => 'BlogPosting',
				'@id'           => get_permalink() . '#article',
				'headline'      => get_the_title(),
				'datePublished' => get_the_date( 'c' ),
				'dateModified'  => get_the_modified_date( 'c' ),
				'author'        => array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $post->post_author ),
				),
				'publisher'     => array( '@id' => home_url( '/#identity' ) ),
				'mainEntityOfPage' => get_permalink(),
			);
		}

		// Breadcrumb schema on singular content.
		if ( ! empty( $s['breadcrumb_schema'] ) && is_singular() ) {
			$items = array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => __( 'Home', 'stackpress' ),
					'item'     => home_url( '/' ),
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => get_the_title(),
					'item'     => get_permalink(),
				),
			);
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
	}
}
