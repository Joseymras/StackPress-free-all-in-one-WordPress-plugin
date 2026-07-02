<?php
/**
 * SEO & AI Visibility Checker module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\SEO;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a free, on-site audit of common SEO issues and AI-visibility signals and
 * lists pass / warn / fail results with fixes — no external service needed.
 */
final class SEO_Checker extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'seo_checker';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'SEO & AI visibility checker', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Audit your site for SEO issues and AI-crawler visibility, with fixes.', 'stackpress' );
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
	public function performance_profile() {
		return array(
			'php_memory_kb' => 40,
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
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_page' ) );
		}
	}

	/**
	 * Register the audit page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'stackpress',
			__( 'SEO checker', 'stackpress' ),
			__( 'SEO checker', 'stackpress' ),
			'manage_options',
			'stackpress-seo-checker',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Build the list of checks with results.
	 *
	 * @return array[] Each: label, status (pass|warn|fail), fix.
	 */
	private function run_checks() {
		$core    = \StackPress\Core::instance();
		$active  = $core->get_active_modules();
		$checks  = array();

		// HTTPS.
		$https = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );
		$checks[] = array(
			'label'  => __( 'Site uses HTTPS', 'stackpress' ),
			'status' => $https ? 'pass' : 'fail',
			'fix'    => __( 'Install an SSL certificate and enable the Force HTTPS module.', 'stackpress' ),
		);

		// Search engine visibility.
		$public = (bool) get_option( 'blog_public' );
		$checks[] = array(
			'label'  => __( 'Search engines are allowed to index the site', 'stackpress' ),
			'status' => $public ? 'pass' : 'fail',
			'fix'    => __( 'Settings → Reading → uncheck "Discourage search engines".', 'stackpress' ),
		);

		// Meta tags module.
		$checks[] = array(
			'label'  => __( 'Meta description / Open Graph output', 'stackpress' ),
			'status' => in_array( 'meta_tags', $active, true ) ? 'pass' : 'warn',
			'fix'    => __( 'Enable the SEO meta tags module.', 'stackpress' ),
		);

		// Schema.
		$checks[] = array(
			'label'  => __( 'Structured data (schema) output', 'stackpress' ),
			'status' => in_array( 'schema_jsonld', $active, true ) ? 'pass' : 'warn',
			'fix'    => __( 'Enable the Schema / structured data module.', 'stackpress' ),
		);

		// Sitemap (core or our module).
		$has_sitemap = in_array( 'robots_txt', $active, true ) || function_exists( 'wp_sitemaps_get_server' );
		$checks[] = array(
			'label'  => __( 'XML sitemap available', 'stackpress' ),
			'status' => $has_sitemap ? 'pass' : 'warn',
			'fix'    => __( 'WordPress core provides /wp-sitemap.xml; ensure it is not disabled.', 'stackpress' ),
		);

		// AI visibility: llms.txt.
		$checks[] = array(
			'label'  => __( 'llms.txt present for AI assistants', 'stackpress' ),
			'status' => in_array( 'llms_txt', $active, true ) ? 'pass' : 'warn',
			'fix'    => __( 'Enable the llms.txt for AI module.', 'stackpress' ),
		);

		// Site title / tagline.
		$checks[] = array(
			'label'  => __( 'Site title and tagline set', 'stackpress' ),
			'status' => ( get_bloginfo( 'name' ) && get_bloginfo( 'description' ) ) ? 'pass' : 'warn',
			'fix'    => __( 'Settings → General → set a clear title and tagline.', 'stackpress' ),
		);

		// Permalinks.
		$checks[] = array(
			'label'  => __( 'SEO-friendly (pretty) permalinks', 'stackpress' ),
			'status' => ( '' !== get_option( 'permalink_structure' ) ) ? 'pass' : 'fail',
			'fix'    => __( 'Settings → Permalinks → choose Post name.', 'stackpress' ),
		);

		// Images missing alt (sample).
		$missing = $this->count_images_without_alt();
		$checks[] = array(
			'label'  => sprintf( /* translators: %d: count. */ __( 'Images with alt text (%d missing in recent media)', 'stackpress' ), $missing ),
			'status' => ( 0 === $missing ) ? 'pass' : 'warn',
			'fix'    => __( 'Enable Auto image alt text, or add alt text in the Media Library.', 'stackpress' ),
		);

		return $checks;
	}

	/**
	 * Count recent image attachments without alt text.
	 *
	 * @return int
	 */
	private function count_images_without_alt() {
		$images = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => 50,
				'fields'         => 'ids',
				'post_status'    => 'inherit',
			)
		);
		$missing = 0;
		foreach ( (array) $images as $id ) {
			if ( '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
				$missing++;
			}
		}
		return $missing;
	}

	/**
	 * Render the audit page.
	 *
	 * @return void
	 */
	public function render_page() {
		$checks = $this->run_checks();
		$colors = array(
			'pass' => '#3b6d11',
			'warn' => '#854f0b',
			'fail' => '#a32d2d',
		);
		$labels = array(
			'pass' => __( 'Pass', 'stackpress' ),
			'warn' => __( 'Improve', 'stackpress' ),
			'fail' => __( 'Fix', 'stackpress' ),
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'SEO & AI visibility checker', 'stackpress' ) . '</h1>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'stackpress' ) . '</th><th>' . esc_html__( 'Status', 'stackpress' ) . '</th><th>' . esc_html__( 'How to fix', 'stackpress' ) . '</th></tr></thead><tbody>';
		foreach ( $checks as $c ) {
			$color = isset( $colors[ $c['status'] ] ) ? $colors[ $c['status'] ] : '#555';
			echo '<tr>';
			echo '<td>' . esc_html( $c['label'] ) . '</td>';
			echo '<td><span style="color:#fff;background:' . esc_attr( $color ) . ';padding:2px 8px;border-radius:4px;font-size:12px;">' . esc_html( $labels[ $c['status'] ] ) . '</span></td>';
			echo '<td>' . ( 'pass' === $c['status'] ? '—' : esc_html( $c['fix'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}
}
