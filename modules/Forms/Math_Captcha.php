<?php
/**
 * Math Captcha module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Forms;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a simple arithmetic question to the comment form as a no-service CAPTCHA.
 * The expected answer is stored as a salted hash in a hidden field, so it works
 * without sessions or an external provider.
 */
final class Math_Captcha extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'math_captcha';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Math captcha', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Add a simple sum to the comment form to block bots — no external CAPTCHA service.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'forms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'shield';
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
		add_filter( 'comment_form_fields', array( $this, 'add_field' ) );
		add_filter( 'preprocess_comment', array( $this, 'verify' ) );
	}

	/**
	 * Hash an answer with WordPress's salt.
	 *
	 * @param int $answer The numeric answer.
	 * @return string
	 */
	private function hash( $answer ) {
		return wp_hash( 'stackpress_math_' . $answer );
	}

	/**
	 * Add the math field to the comment form.
	 *
	 * @param array $fields Comment fields.
	 * @return array
	 */
	public function add_field( $fields ) {
		if ( is_user_logged_in() ) {
			return $fields;
		}
		$a   = wp_rand( 1, 9 );
		$b   = wp_rand( 1, 9 );
		$sum = $a + $b;

		$field  = '<p class="comment-form-stackpress-math">';
		$field .= '<label for="stackpress_math">' . sprintf( /* translators: 1: first number, 2: second number. */ esc_html__( 'What is %1$d + %2$d?', 'stackpress' ), $a, $b ) . ' <span class="required">*</span></label>';
		$field .= '<input type="text" id="stackpress_math" name="stackpress_math" size="5" required />';
		$field .= '<input type="hidden" name="stackpress_math_h" value="' . esc_attr( $this->hash( $sum ) ) . '" />';
		$field .= '</p>';

		$fields['stackpress_math'] = $field;
		return $fields;
	}

	/**
	 * Verify the answer.
	 *
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public function verify( $commentdata ) {
		if ( is_user_logged_in() ) {
			return $commentdata;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WP comment flow nonce covers the submission; we only read our captcha fields.
		// Fail open if our field wasn't rendered, so comment forms never break.
		if ( empty( $_POST['stackpress_math_h'] ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return $commentdata;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$answer = isset( $_POST['stackpress_math'] ) ? absint( wp_unslash( $_POST['stackpress_math'] ) ) : -1;
		$hash   = sanitize_text_field( wp_unslash( $_POST['stackpress_math_h'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! hash_equals( $hash, $this->hash( $answer ) ) ) {
			wp_die(
				esc_html__( 'Incorrect answer to the math question. Please go back and try again.', 'stackpress' ),
				esc_html__( 'Comment blocked', 'stackpress' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
		return $commentdata;
	}
}
