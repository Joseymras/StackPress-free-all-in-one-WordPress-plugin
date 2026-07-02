<?php
/**
 * Cookie Consent module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Accessibility;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * A dismissible cookie-consent banner that remembers the visitor's choice.
 * Self-hosted, no external service. Replaces CookieYes basics.
 */
final class Cookie_Consent extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'cookie_consent';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Cookie consent banner', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'Show a GDPR/CCPA-style cookie notice and remember the visitor\'s choice.', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function category() {
		return 'accessibility';
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
	public function replaces() {
		return 'premium cookie-consent plugins';
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 30,
			'front_js_kb'   => 0.7,
			'front_css_kb'  => 0.6,
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
				'key'     => 'message',
				'label'   => __( 'Banner message', 'stackpress' ),
				'type'    => 'text',
				'default' => __( 'We use cookies to improve your experience. By continuing, you agree to our use of cookies.', 'stackpress' ),
			),
			array(
				'key'     => 'accept_text',
				'label'   => __( 'Accept button text', 'stackpress' ),
				'type'    => 'text',
				'default' => __( 'Accept', 'stackpress' ),
			),
			array(
				'key'     => 'decline_text',
				'label'   => __( 'Decline button text', 'stackpress' ),
				'type'    => 'text',
				'default' => __( 'Decline', 'stackpress' ),
			),
			array(
				'key'     => 'policy_url',
				'label'   => __( 'Privacy policy URL', 'stackpress' ),
				'type'    => 'url',
				'default' => '',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	/**
	 * Render the consent banner.
	 *
	 * @return void
	 */
	public function render() {
		$s       = $this->get_settings();
		$message = esc_html( $s['message'] );
		$accept  = esc_html( $s['accept_text'] );
		$decline = esc_html( $s['decline_text'] );
		$policy  = esc_url( (string) $s['policy_url'] );
		$link    = $policy ? ' <a href="' . $policy . '" style="color:#0aa2c0;">' . esc_html__( 'Learn more', 'stackpress' ) . '</a>' : '';
		?>
		<div id="stackpress-cookie" style="display:none;position:fixed;left:16px;right:16px;bottom:16px;max-width:680px;margin:0 auto;background:#1b2a4a;color:#fff;padding:16px 20px;border-radius:10px;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,.25);font-size:14px;line-height:1.5;">
			<span><?php echo $message . $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above. ?></span>
			<div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
				<button id="stackpress-cookie-decline" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.5);padding:7px 16px;border-radius:6px;cursor:pointer;"><?php echo $decline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<button id="stackpress-cookie-accept" style="background:#0aa2c0;color:#fff;border:0;padding:7px 18px;border-radius:6px;cursor:pointer;"><?php echo $accept; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
		</div>
		<script>
		(function(){var b=document.getElementById('stackpress-cookie');if(!b)return;
		function get(n){return document.cookie.split('; ').find(function(r){return r.indexOf(n+'=')===0;});}
		if(!get('stackpress_cookie_consent')){b.style.display='block';}
		function set(v){document.cookie='stackpress_cookie_consent='+v+';path=/;max-age=31536000;SameSite=Lax';b.style.display='none';}
		var a=document.getElementById('stackpress-cookie-accept'),d=document.getElementById('stackpress-cookie-decline');
		if(a)a.addEventListener('click',function(){set('accept');});
		if(d)d.addEventListener('click',function(){set('decline');});})();
		</script>
		<?php
	}
}
