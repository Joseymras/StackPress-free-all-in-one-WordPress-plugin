<?php
/**
 * Contact Form module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Forms;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * A simple, spam-protected contact form via the [stackpress_contact] shortcode.
 * Emails the site owner and (optionally) stores each submission as a private
 * "Form entry" post you can review in wp-admin. Replaces Contact Form 7 basics.
 */
final class Contact_Form extends Abstract_Module {

	/**
	 * Custom post type used to store submissions.
	 */
	const CPT = 'stackpress_entry';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'contact_form';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Contact form', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'A spam-protected contact form via [stackpress_contact]. Emails you and saves each entry.', 'stackpress' );
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
		return 'forms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function replaces() {
		return 'premium form builders';
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 120,
			'front_js_kb'   => 0,
			'front_css_kb'  => 0.4,
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
				'key'     => 'recipient',
				'label'   => __( 'Send submissions to', 'stackpress' ),
				'type'    => 'text',
				'default' => get_option( 'admin_email' ),
				'help'    => __( 'Email address that receives form submissions.', 'stackpress' ),
			),
			array(
				'key'     => 'success_message',
				'label'   => __( 'Success message', 'stackpress' ),
				'type'    => 'text',
				'default' => __( 'Thanks! Your message has been sent.', 'stackpress' ),
			),
			array(
				'key'     => 'store_entries',
				'label'   => __( 'Save submissions in the dashboard', 'stackpress' ),
				'type'    => 'toggle',
				'default' => true,
			),
			array(
				'key'     => 'provider',
				'label'   => __( 'Forward leads to', 'stackpress' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'           => __( 'Do not forward', 'stackpress' ),
					'mailchimp'      => __( 'Mailchimp', 'stackpress' ),
					'klaviyo'        => __( 'Klaviyo', 'stackpress' ),
					'brevo'          => __( 'Brevo', 'stackpress' ),
					'convertkit'     => __( 'ConvertKit', 'stackpress' ),
					'hubspot'        => __( 'HubSpot', 'stackpress' ),
					'activecampaign' => __( 'ActiveCampaign', 'stackpress' ),
					'webhook'        => __( 'Webhook', 'stackpress' ),
				),
				'help'    => __( 'Optionally forward form submissions to your email tool.', 'stackpress' ),
			),
			array(
				'key'     => 'api_key',
				'label'   => __( 'API key or token', 'stackpress' ),
				'type'    => 'text',
				'default' => '',
			),
			array(
				'key'     => 'audience_id',
				'label'   => __( 'List / audience / form ID', 'stackpress' ),
				'type'    => 'text',
				'default' => '',
			),
			array(
				'key'     => 'activecampaign_url',
				'label'   => __( 'ActiveCampaign API URL', 'stackpress' ),
				'type'    => 'url',
				'default' => '',
			),
			array(
				'key'     => 'endpoint_url',
				'label'   => __( 'Webhook endpoint', 'stackpress' ),
				'type'    => 'url',
				'default' => '',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'maybe_handle_submission' ) );
		add_shortcode( 'stackpress_contact', array( $this, 'render_form' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_entry_meta_box' ) );
		}
	}

	/**
	 * Register the private "Form entry" post type.
	 *
	 * @return void
	 */
	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Form entries', 'stackpress' ),
					'singular_name' => __( 'Form entry', 'stackpress' ),
					'menu_name'     => __( 'Form entries', 'stackpress' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'stackpress',
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
				'menu_icon'       => 'dashicons-email',
			)
		);
	}

	/**
	 * Render the contact form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_form( $atts ) {
		$sent = isset( $_GET['stackpress_sent'] ) ? sanitize_text_field( wp_unslash( $_GET['stackpress_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash flag.

		ob_start();

		if ( '1' === $sent ) {
			echo '<div class="stackpress-form-success" style="background:#eaf3de;color:#3b6d11;padding:12px 16px;border-radius:8px;margin-bottom:16px;">' . esc_html( $this->get_setting( 'success_message', __( 'Thanks! Your message has been sent.', 'stackpress' ) ) ) . '</div>';
		} elseif ( 'error' === $sent ) {
			echo '<div class="stackpress-form-error" style="background:#fcebeb;color:#a32d2d;padding:12px 16px;border-radius:8px;margin-bottom:16px;">' . esc_html__( 'Sorry, your message could not be sent. Please check your entries and try again.', 'stackpress' ) . '</div>';
		}
		?>
		<form class="stackpress-contact-form" method="post" action="" style="max-width:560px;">
			<?php wp_nonce_field( 'stackpress_contact', 'stackpress_contact_nonce' ); ?>
			<input type="hidden" name="stackpress_action" value="stackpress_contact_submit" />
			<input type="hidden" name="stackpress_ts" value="<?php echo esc_attr( time() ); ?>" />
			<div aria-hidden="true" style="position:absolute;left:-9999px;">
				<label><?php esc_html_e( 'Leave this field empty', 'stackpress' ); ?>
					<input type="text" name="stackpress_website" tabindex="-1" autocomplete="off" value="" />
				</label>
			</div>
			<p>
				<label for="stackpress_name"><?php esc_html_e( 'Name', 'stackpress' ); ?></label><br />
				<input type="text" id="stackpress_name" name="stackpress_name" required style="width:100%;padding:8px;" />
			</p>
			<p>
				<label for="stackpress_email"><?php esc_html_e( 'Email', 'stackpress' ); ?></label><br />
				<input type="email" id="stackpress_email" name="stackpress_email" required style="width:100%;padding:8px;" />
			</p>
			<p>
				<label for="stackpress_subject"><?php esc_html_e( 'Subject', 'stackpress' ); ?></label><br />
				<input type="text" id="stackpress_subject" name="stackpress_subject" style="width:100%;padding:8px;" />
			</p>
			<p>
				<label for="stackpress_message"><?php esc_html_e( 'Message', 'stackpress' ); ?></label><br />
				<textarea id="stackpress_message" name="stackpress_message" rows="6" required style="width:100%;padding:8px;"></textarea>
			</p>
			<p>
				<button type="submit" style="background:#1b2a4a;color:#fff;border:0;padding:10px 20px;border-radius:8px;cursor:pointer;"><?php esc_html_e( 'Send message', 'stackpress' ); ?></button>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle a posted submission (PRG pattern).
	 *
	 * @return void
	 */
	public function maybe_handle_submission() {
		if ( ! isset( $_POST['stackpress_action'] ) || 'stackpress_contact_submit' !== $_POST['stackpress_action'] ) {
			return;
		}
		if ( ! isset( $_POST['stackpress_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stackpress_contact_nonce'] ) ), 'stackpress_contact' ) ) {
			$this->redirect_back( 'error' );
		}

		// Spam checks: honeypot must be empty, and form must take >= 3s to fill.
		$honeypot = isset( $_POST['stackpress_website'] ) ? sanitize_text_field( wp_unslash( $_POST['stackpress_website'] ) ) : '';
		$ts       = isset( $_POST['stackpress_ts'] ) ? absint( $_POST['stackpress_ts'] ) : 0;
		if ( '' !== $honeypot || ( $ts && ( time() - $ts ) < 3 ) ) {
			// Silently treat as success to avoid tipping off bots.
			$this->redirect_back( '1' );
		}

		$name    = isset( $_POST['stackpress_name'] ) ? sanitize_text_field( wp_unslash( $_POST['stackpress_name'] ) ) : '';
		$email   = isset( $_POST['stackpress_email'] ) ? sanitize_email( wp_unslash( $_POST['stackpress_email'] ) ) : '';
		$subject = isset( $_POST['stackpress_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['stackpress_subject'] ) ) : '';
		$message = isset( $_POST['stackpress_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['stackpress_message'] ) ) : '';

		if ( '' === $name || ! is_email( $email ) || '' === $message ) {
			$this->redirect_back( 'error' );
		}

		$subject = '' !== $subject ? $subject : __( 'New contact form submission', 'stackpress' );

		// Email the site owner.
		$recipient = sanitize_email( (string) $this->get_setting( 'recipient', get_option( 'admin_email' ) ) );
		$recipient = is_email( $recipient ) ? $recipient : get_option( 'admin_email' );
		$body      = sprintf(
			"%s: %s\n%s: %s\n\n%s\n",
			__( 'Name', 'stackpress' ),
			$name,
			__( 'Email', 'stackpress' ),
			$email,
			$message
		);
		$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );
		wp_mail( $recipient, '[' . get_bloginfo( 'name' ) . '] ' . $subject, $body, $headers );

		// Store the entry.
		if ( ! empty( $this->get_setting( 'store_entries', true ) ) ) {
			$entry_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'post_title'  => $subject,
				)
			);
			if ( $entry_id && ! is_wp_error( $entry_id ) ) {
				update_post_meta( $entry_id, '_stackpress_name', $name );
				update_post_meta( $entry_id, '_stackpress_email', $email );
				update_post_meta( $entry_id, '_stackpress_message', $message );
				update_post_meta( $entry_id, '_stackpress_ip', $this->get_ip() );
			}
		}

		$this->sync_to_provider( $name, $email, $subject, $message );
		$this->redirect_back( '1' );
	}

	/**
	 * Forward a contact submission to the configured provider if enabled.
	 *
	 * @param string $name Contact name.
	 * @param string $email Contact email.
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @return void
	 */
	private function sync_to_provider( $name, $email, $subject, $message ) {
		$provider = sanitize_key( (string) $this->get_setting( 'provider', 'none' ) );
		if ( 'none' === $provider ) {
			return;
		}

		$api_key = trim( (string) $this->get_setting( 'api_key', '' ) );
		$audience_id = trim( (string) $this->get_setting( 'audience_id', '' ) );
		$endpoint = trim( (string) $this->get_setting( 'endpoint_url', '' ) );
		$activecampaign_url = trim( (string) $this->get_setting( 'activecampaign_url', '' ) );

		if ( 'webhook' === $provider ) {
			if ( '' === $endpoint ) {
				return;
			}
			wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array( 'name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message ) ),
				)
			);
			return;
		}

		if ( '' === $api_key || '' === $audience_id ) {
			return;
		}

		$payload = array( 'name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message );
		$headers = array();
		$url = '';

		switch ( $provider ) {
			case 'mailchimp':
				$dc = substr( strrchr( $api_key, '-' ), 1 );
				if ( '' === $dc ) {
					return;
				}
				$url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . rawurlencode( $audience_id ) . '/members';
				$headers['Authorization'] = 'Basic ' . base64_encode( 'user:' . $api_key );
				$payload = array( 'email_address' => $email, 'merge_fields' => array( 'FNAME' => $name ), 'status' => 'subscribed' );
				break;
			case 'klaviyo':
				$url = 'https://a.klaviyo.com/api/v2/list/' . rawurlencode( $audience_id ) . '/members';
				$headers['Authorization'] = 'Klaviyo-API-Key ' . $api_key;
				$payload = array( 'email' => $email, 'first_name' => $name, 'confirm_opt_in' => 'false' );
				break;
			case 'brevo':
				$url = 'https://api.brevo.com/v3/contacts';
				$headers['api-key'] = $api_key;
				$payload = array( 'email' => $email, 'attributes' => array( 'FIRSTNAME' => $name ), 'listIds' => array( (int) $audience_id ) );
				break;
			case 'convertkit':
				$url = 'https://api.convertkit.com/v3/forms/' . rawurlencode( $audience_id ) . '/subscribe';
				$payload = array( 'email' => $email, 'api_key' => $api_key, 'first_name' => $name );
				break;
			case 'hubspot':
				$url = 'https://api.hubapi.com/contacts/v1/contact';
				$headers['Authorization'] = 'Bearer ' . $api_key;
				$payload = array(
					'properties' => array(
						array(
							'property' => 'email',
							'value'    => $email,
						),
						array(
							'property' => 'firstname',
							'value'    => $name,
						),
					),
				);
				break;
			case 'activecampaign':
				if ( '' === $activecampaign_url ) {
					return;
				}
				$url = rtrim( $activecampaign_url, '/' ) . '/api/3/contacts';
				$headers['Api-Token'] = $api_key;
				$payload = array(
					'contact' => array(
						'email'  => $email,
						'firstName' => $name,
						'status' => 1,
					),
				);
				break;
			default:
				return;
		}

		wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $payload ),
			)
		);
	}

	/**
	 * Redirect back to the referring page with a status flag.
	 *
	 * @param string $status Status flag ('1' or 'error').
	 * @return void
	 */
	private function redirect_back( $status ) {
		$ref = wp_get_referer();
		$url = $ref ? $ref : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'stackpress_sent', $status, remove_query_arg( 'stackpress_sent', $url ) ) );
		exit;
	}

	/**
	 * Client IP (best effort).
	 *
	 * @return string
	 */
	private function get_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Show submission details in the entry editor.
	 *
	 * @return void
	 */
	public function add_entry_meta_box() {
		add_meta_box(
			'stackpress_entry_details',
			__( 'Submission details', 'stackpress' ),
			array( $this, 'render_entry_meta_box' ),
			self::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Render the entry detail meta box.
	 *
	 * @param \WP_Post $post Entry post.
	 * @return void
	 */
	public function render_entry_meta_box( $post ) {
		$name    = get_post_meta( $post->ID, '_stackpress_name', true );
		$email   = get_post_meta( $post->ID, '_stackpress_email', true );
		$message = get_post_meta( $post->ID, '_stackpress_message', true );
		$ip      = get_post_meta( $post->ID, '_stackpress_ip', true );
		echo '<p><strong>' . esc_html__( 'Name', 'stackpress' ) . ':</strong> ' . esc_html( $name ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Email', 'stackpress' ) . ':</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
		echo '<p><strong>' . esc_html__( 'Message', 'stackpress' ) . ':</strong></p>';
		echo '<div style="white-space:pre-wrap;background:#f6f7f9;padding:12px;border-radius:6px;">' . esc_html( $message ) . '</div>';
		if ( $ip ) {
			echo '<p style="color:#6b7280;margin-top:10px;"><strong>' . esc_html__( 'IP', 'stackpress' ) . ':</strong> ' . esc_html( $ip ) . '</p>';
		}
	}
}
