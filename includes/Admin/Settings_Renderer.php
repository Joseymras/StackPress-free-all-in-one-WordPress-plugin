<?php
/**
 * Renders a module settings form from its schema.
 *
 * @package StackPress
 */

namespace StackPress\Admin;

use StackPress\Modules\Abstract_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a module's settings_schema() into an accessible, escaped HTML form.
 */
final class Settings_Renderer {

	/**
	 * Render the settings form for a module.
	 *
	 * @param Abstract_Module $module Module instance.
	 * @return string HTML.
	 */
	public static function render( Abstract_Module $module ) {
		$schema = $module->settings_schema();

		if ( empty( $schema ) ) {
			return '<p class="stackpress-no-settings">' . esc_html__( 'This module has no settings — it just works once enabled.', 'stackpress' ) . '</p>';
		}

		$values = $module->get_settings();
		ob_start();
		?>
		<form class="stackpress-settings-form" data-module="<?php echo esc_attr( $module->id() ); ?>">
			<?php foreach ( $schema as $field ) : ?>
				<?php echo self::field( $field, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::field() escapes internally. ?>
			<?php endforeach; ?>
			<div class="stackpress-settings-actions">
				<button type="button" class="button stackpress-settings-cancel"><?php esc_html_e( 'Cancel', 'stackpress' ); ?></button>
				<button type="submit" class="button button-primary stackpress-settings-save"><?php esc_html_e( 'Save changes', 'stackpress' ); ?></button>
				<span class="stackpress-settings-status" aria-live="polite"></span>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a module's settings as a standard POST form for a dedicated admin
	 * page (so tools that have their own page can still expose their settings).
	 * Saves via the shared admin_post_stackpress_save_settings handler.
	 *
	 * @param Abstract_Module $module Module instance.
	 * @return string HTML (empty if the module has no settings).
	 */
	public static function page_form( Abstract_Module $module ) {
		$schema = $module->settings_schema();
		if ( empty( $schema ) ) {
			return '';
		}
		$values = $module->get_settings();
		ob_start();
		?>
		<style>
		.stackpress-page-form{max-width:640px}
		.stackpress-page-form .stackpress-field{margin:0 0 16px}
		.stackpress-page-form .stackpress-field-label{display:block;font-weight:600;margin-bottom:4px}
		.stackpress-page-form input[type=text],.stackpress-page-form input[type=url],.stackpress-page-form input[type=password],.stackpress-page-form input[type=number],.stackpress-page-form select,.stackpress-page-form textarea{width:100%;max-width:640px}
		.stackpress-page-form .stackpress-field-help{color:#6b7280;font-size:12.5px;margin:4px 0 0}
		.stackpress-page-form .stackpress-field-guide{margin:3px 0 0;font-size:12.5px}
		.stackpress-page-form .stackpress-field-toggle{display:flex;align-items:center;gap:8px;font-weight:600}
		</style>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stackpress-page-form">
			<?php wp_nonce_field( 'stackpress_save_settings_' . $module->id() ); ?>
			<input type="hidden" name="action" value="stackpress_save_settings" />
			<input type="hidden" name="module" value="<?php echo esc_attr( $module->id() ); ?>" />
			<?php foreach ( $schema as $field ) : ?>
				<?php echo self::field( $field, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::field() escapes internally. ?>
			<?php endforeach; ?>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'stackpress' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single field.
	 *
	 * @param array $field  Field definition.
	 * @param array $values Current values.
	 * @return string
	 */
	private static function field( array $field, array $values ) {
		$key   = isset( $field['key'] ) ? $field['key'] : '';
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';
		$label = isset( $field['label'] ) ? $field['label'] : '';
		$help  = isset( $field['help'] ) ? $field['help'] : '';
		$value = array_key_exists( $key, $values ) ? $values[ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' );
		$name  = 'settings[' . $key . ']';
		$id    = 'stackpress_field_' . $key;

		ob_start();
		echo '<div class="stackpress-field stackpress-field-' . esc_attr( $type ) . '">';

		if ( 'toggle' === $type ) {
			echo '<label class="stackpress-field-toggle" for="' . esc_attr( $id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . ' />';
			echo '<span class="stackpress-switch"></span>';
			echo '<span class="stackpress-field-label">' . esc_html( $label ) . '</span>';
			echo '</label>';
		} else {
			echo '<label class="stackpress-field-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';

			switch ( $type ) {
				case 'textarea':
					echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( (string) $value ) . '</textarea>';
					break;

				case 'select':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( (array) ( isset( $field['options'] ) ? $field['options'] : array() ) as $opt_val => $opt_label ) {
						echo '<option value="' . esc_attr( $opt_val ) . '" ' . selected( (string) $value, (string) $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
					}
					echo '</select>';
					break;

				case 'radio':
					echo '<div class="stackpress-radio-group">';
					foreach ( (array) ( isset( $field['options'] ) ? $field['options'] : array() ) as $opt_val => $opt_label ) {
						$rid = $id . '_' . $opt_val;
						echo '<label class="stackpress-radio" for="' . esc_attr( $rid ) . '">';
						echo '<input type="radio" id="' . esc_attr( $rid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $opt_val ) . '" ' . checked( (string) $value, (string) $opt_val, false ) . ' />';
						echo '<span>' . esc_html( $opt_label ) . '</span>';
						echo '</label>';
					}
					echo '</div>';
					break;

				case 'number':
					$min  = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
					$max  = isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
					$step = isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '';
					echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $min . $max . $step . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above.
					break;

				case 'color':
					echo '<input type="text" class="stackpress-color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="#000000" />';
					break;

				case 'url':
					echo '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
					break;

				case 'password':
					echo '<input type="password" autocomplete="new-password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
					break;

				default:
					echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
					break;
			}
		}

		if ( $help ) {
			echo '<p class="stackpress-field-help">' . esc_html( $help ) . '</p>';
		}

		// Optional "how to get this" guide link rendered under the field.
		if ( ! empty( $field['guide']['url'] ) ) {
			$g_label = ! empty( $field['guide']['label'] ) ? $field['guide']['label'] : __( 'Where do I find this?', 'stackpress' );
			echo '<p class="stackpress-field-guide"><a href="' . esc_url( $field['guide']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $g_label ) . ' &#8599;</a></p>';
		}

		echo '</div>';
		return ob_get_clean();
	}
}
