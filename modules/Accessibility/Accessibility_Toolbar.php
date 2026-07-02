<?php
/**
 * Accessibility Toolbar module.
 *
 * @package StackPress
 */

namespace StackPress\Modules\Accessibility;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * A floating accessibility widget letting visitors adjust font size, contrast,
 * and readability. Preferences persist in the browser. Replaces costly
 * accessibility-overlay subscriptions for the basic toolbar case.
 */
final class Accessibility_Toolbar extends Abstract_Module {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'accessibility_toolbar';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Accessibility toolbar', 'stackpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description() {
		return __( 'A floating widget for font size, high contrast, and link highlighting.', 'stackpress' );
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
		return 'accessible';
	}

	/**
	 * {@inheritDoc}
	 */
	public function replaces() {
		return 'premium accessibility tools';
	}

	/**
	 * {@inheritDoc}
	 */
	public function performance_profile() {
		return array(
			'php_memory_kb' => 35,
			'front_js_kb'   => 1.2,
			'front_css_kb'  => 0.8,
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
				'key'     => 'position',
				'label'   => __( 'Button position', 'stackpress' ),
				'type'    => 'select',
				'default' => 'right',
				'options' => array(
					'right' => __( 'Bottom right', 'stackpress' ),
					'left'  => __( 'Bottom left', 'stackpress' ),
				),
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
	 * Render the toolbar, its styles, and behaviour.
	 *
	 * @return void
	 */
	public function render() {
		$pos = ( 'left' === $this->get_setting( 'position', 'right' ) ) ? 'left' : 'right';
		?>
		<style>
		#stackpress-a11y-btn{position:fixed;bottom:24px;<?php echo esc_attr( $pos ); ?>:24px;width:48px;height:48px;border-radius:50%;background:#1b2a4a;color:#fff;border:0;cursor:pointer;z-index:99998;font-size:22px;line-height:1}
		#stackpress-a11y-panel{position:fixed;bottom:80px;<?php echo esc_attr( $pos ); ?>:24px;background:#fff;color:#1b2a4a;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.2);padding:14px;z-index:99998;display:none;min-width:200px}
		#stackpress-a11y-panel button{display:block;width:100%;text-align:left;background:#f6f7f9;border:1px solid #e4e7ec;border-radius:6px;padding:8px 10px;margin:5px 0;cursor:pointer;font-size:13px}
		body.stackpress-a11y-contrast{filter:contrast(1.4)}
		body.stackpress-a11y-gray{filter:grayscale(1)}
		body.stackpress-a11y-links a{text-decoration:underline!important;color:#0000ee!important}
		body.stackpress-a11y-readable *{font-family:Arial,Helvetica,sans-serif!important}
		</style>
		<button id="stackpress-a11y-btn" aria-label="<?php esc_attr_e( 'Accessibility options', 'stackpress' ); ?>" aria-expanded="false">&#9855;</button>
		<div id="stackpress-a11y-panel" role="dialog" aria-label="<?php esc_attr_e( 'Accessibility options', 'stackpress' ); ?>">
			<button data-act="font-up"><?php esc_html_e( 'Increase text size', 'stackpress' ); ?></button>
			<button data-act="font-down"><?php esc_html_e( 'Decrease text size', 'stackpress' ); ?></button>
			<button data-act="contrast"><?php esc_html_e( 'High contrast', 'stackpress' ); ?></button>
			<button data-act="gray"><?php esc_html_e( 'Grayscale', 'stackpress' ); ?></button>
			<button data-act="links"><?php esc_html_e( 'Highlight links', 'stackpress' ); ?></button>
			<button data-act="readable"><?php esc_html_e( 'Readable font', 'stackpress' ); ?></button>
			<button data-act="reset"><?php esc_html_e( 'Reset', 'stackpress' ); ?></button>
		</div>
		<script>
		(function(){
		var btn=document.getElementById('stackpress-a11y-btn'),panel=document.getElementById('stackpress-a11y-panel'),body=document.body;
		var KEY='stackpress_a11y',state={font:0,contrast:false,gray:false,links:false,readable:false};
		try{var s=localStorage.getItem(KEY);if(s){state=JSON.parse(s);}}catch(e){}
		function apply(){
			body.style.fontSize=state.font?((100+state.font*10)+'%'):'';
			body.classList.toggle('stackpress-a11y-contrast',!!state.contrast);
			body.classList.toggle('stackpress-a11y-gray',!!state.gray);
			body.classList.toggle('stackpress-a11y-links',!!state.links);
			body.classList.toggle('stackpress-a11y-readable',!!state.readable);
			try{localStorage.setItem(KEY,JSON.stringify(state));}catch(e){}
		}
		apply();
		btn.addEventListener('click',function(){var open=panel.style.display==='block';panel.style.display=open?'none':'block';btn.setAttribute('aria-expanded',(!open).toString());});
		panel.addEventListener('click',function(e){var a=e.target&&e.target.getAttribute('data-act');if(!a)return;
			if(a==='font-up'){state.font=Math.min(5,state.font+1);}
			else if(a==='font-down'){state.font=Math.max(-2,state.font-1);}
			else if(a==='reset'){state={font:0,contrast:false,gray:false,links:false,readable:false};}
			else{state[a]=!state[a];}
			apply();});
		})();
		</script>
		<?php
	}
}
