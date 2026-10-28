<?php

/**
 * View
 *
 * The base of the options page and its tabs: the
 * parts they share, e.g. a field, a badge, a block
 * of code, and the state of a format.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Admin;

use Takopic\Engines\Libraries;
use Takopic\Settings;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

abstract class View
{
	/**
	 * Prints a translated text, escaped, with HTML in
	 * its placeholders, e.g. a <code> or a counter.
	 *
	 * @param string $format
	 * @param string ...$html Escaped by the caller.
	 * @since 1.0.0
	 */
	protected function printf_html($format, ...$html)
	{
		echo sprintf(esc_html($format), ...$html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The text is escaped here, its placeholders by the caller.
	}

	/**
	 * Renders a block of code, with its copy button
	 * (CopyButton.js, hidden without JS).
	 *
	 * @param string $code
	 * @param string $id Unique on the page.
	 * @since 1.0.0
	 */
	protected function render_code($code, $id)
	{
		?>
		<div class="takopic-code-block">
			<pre class="takopic-code" id="<?php echo esc_attr($id); ?>"><code><?php echo esc_html($code); ?></code></pre>
			<button type="button" class="takopic-copy js-takopic-copy" aria-controls="<?php echo esc_attr($id); ?>"><?php esc_html_e('Copy', 'takopic'); ?></button>
		</div>
		<?php
	}

	/**
	 * Renders a command or an address on one line, with
	 * its copy button after it.
	 *
	 * @param string $command
	 * @param string $class Of the code.
	 * @since 1.0.0
	 */
	protected function render_command($command, $class = '')
	{
		$id = 'takopic-command-' . sanitize_title($command);
		printf(
			'<code class="%1$s" id="%2$s">%3$s</code> <button type="button" class="takopic-copy js-takopic-copy" aria-controls="%2$s">%4$s</button>',
			esc_attr($class),
			esc_attr($id),
			esc_html($command),
			esc_html__('Copy', 'takopic')
		);
	}

	/**
	 * Renders a status badge.
	 *
	 * @param string $variant on, off or muted.
	 * @param string $label
	 * @param string $id To follow the settings and the jobs.
	 * @since 1.0.0
	 */
	protected function render_badge($variant, $label, $id = '')
	{
		if ($id) {
			// It follows the settings and the jobs (Form.js).
			printf('<span class="takopic-badge takopic-badge--%s js-takopic-live" id="%s">%s</span>', esc_attr($variant), esc_attr($id), esc_html($label));
			return;
		}
		printf('<span class="takopic-badge takopic-badge--%s">%s</span>', esc_attr($variant), esc_html($label));
	}

	/**
	 * Renders how to install a library, folded: a
	 * missing one, or a better one recommended (advice),
	 * with why, see templates/install.php.
	 *
	 * @param string $takopic_install The format of the library, or oxipng.
	 * @param string $summary
	 * @param string $text Why to install it, if recommended.
	 * @param bool $advice Recommended, the format working without it.
	 * @since 1.0.0
	 */
	protected function render_install($takopic_install, $summary, $text = '', $advice = false)
	{
		?>
		<details class="takopic-install<?php echo $advice || $text ? ' takopic-install--advice' : ''; ?>">
			<summary class="takopic-install__summary"><?php echo esc_html($summary); ?></summary>
			<?php if ($text) : ?>
				<p class="takopic-install__text"><?php echo esc_html($text); ?></p>
			<?php endif; ?>
			<?php include TAKOPIC_TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'install.php'; ?>
		</details>
		<?php
	}

	/**
	 * Renders why the jobs are not available: the
	 * dependencies of composer install are missing.
	 *
	 * @since 1.0.0
	 */
	protected function render_no_jobs()
	{
		?>
		<p class="takopic-card__notice">
			<?php
			/* translators: 1: composer install, 2: wp takopic run */
			$this->printf_html(__('The background jobs need the dependencies of Takopic: run %1$s in its folder. WP-CLI (%2$s) works without them.', 'takopic'), '<code>composer install</code>', '<code>wp takopic run</code>');
			?>
		</p>
		<?php
	}

	/**
	 * Renders the engine of a format: its library and
	 * version, or GD or Imagick without the library,
	 * then its optional libraries (e.g. pngquant).
	 *
	 * @param array $format
	 * @since 1.0.0
	 */
	protected function render_engine($format)
	{
		$runs = Libraries::can_execute();
		if ($format['installed']) {
			$name = $format['library'];
			$state = __('installed', 'takopic');
		} elseif ($format['engine']) {
			$name = $format['engine'];
			$state = $runs
				/* translators: %s: a library, e.g. cwebp. */
				? sprintf(__('%s not installed', 'takopic'), $format['library'])
				/* translators: %s: a library, e.g. cwebp. */
				: sprintf(__('%s cannot run', 'takopic'), $format['library']);
		} else {
			$name = $format['library'];
			$state = $runs ? __('not installed', 'takopic') : __('cannot run', 'takopic');
		}
		?>
		<span class="takopic-engine takopic-engine--<?php echo $format['engine'] ? 'on' : 'off'; ?>">
			<span class="takopic-engine__name"><?php echo esc_html($name); ?></span>
			<span class="takopic-engine__state"><?php echo esc_html($state); ?></span>
			<?php if ($format['version']) : ?>
				<span class="takopic-engine__version"><?php echo esc_html($format['version']); ?></span>
			<?php endif; ?>
		</span>
		<?php foreach ($format['extras'] as $extra => $installed) : ?>
			<span class="takopic-engine takopic-engine--<?php echo $installed ? 'on' : 'off'; ?>">
				<span class="takopic-engine__name"><?php echo esc_html($extra); ?></span>
				<span class="takopic-engine__state"><?php echo esc_html($installed ? __('installed', 'takopic') : ($runs ? __('not installed', 'takopic') : __('cannot run', 'takopic'))); ?></span>
			</span>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Renders an option field: a toggle for a checkbox,
	 * a label and its control otherwise.
	 *
	 * @param string $name
	 * @param array $field
	 * @since 1.0.0
	 */
	protected function render_field($name, $field)
	{
		$id = self::field_id($name);
		$input = Settings::OPTION . '[' . $name . ']';
		$value = Settings::get($name);
		$setting = Settings::FIELDS[$name];
		$tags = ['strong' => [], 'code' => []];
		$disabled = !empty($field['disabled']) ? ' disabled' : '';
		// A field shown while another one has a value, e.g. the lossy quality (DependentField.js).
		// Off as the page opens: hidden while the scripts load (fields.css), shown without JS.
		$when = '';
		$off = false;
		if (!empty($field['when'])) {
			$when = sprintf(' data-when="%s" data-when-value="%s"', esc_attr(Settings::OPTION . '[' . $field['when'][0] . ']'), esc_attr($field['when'][1]));
			$off = (string)Settings::get($field['when'][0]) !== (string)$field['when'][1];
		}

		// The label names the control without being a <label>: hovering it would hover the control.
		printf(
			'<div class="takopic-field%s%s%s%s"%s><span class="takopic-field__label" id="%s-label">%s</span>',
			$field['type'] === 'checkbox' ? ' takopic-field--toggle' : '',
			$disabled ? ' takopic-field--disabled' : '',
			$off ? ' takopic-field--off' : '',
			$when ? ' js-takopic-dependent' : '',
			$when, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr($id),
			esc_html($field['label'])
		);

		if ($field['type'] === 'checkbox') {
			$this->render_toggle($name);
		} else {
			echo '<div class="takopic-field__control">';
			if ($field['type'] === 'number') {
				printf(
					'<input type="number" class="takopic-field__input" id="%s" name="%s" value="%s" min="%d" max="%d" step="1" aria-labelledby="%s-label"%s>',
					esc_attr($id), esc_attr($input), esc_attr($value), (int)$setting['min'], (int)$setting['max'], esc_attr($id), esc_attr($disabled)
				);
			} elseif ($field['type'] === 'select') {
				printf('<select class="takopic-field__input" id="%s" name="%s" aria-labelledby="%s-label"%s>', esc_attr($id), esc_attr($input), esc_attr($id), esc_attr($disabled));
				foreach ($field['options'] as $option => $label) {
					printf('<option value="%s" %s>%s</option>', esc_attr($option), selected((string)$value, (string)$option, false), esc_html($label));
				}
				echo '</select>';
			} elseif ($field['type'] === 'choice') {
				// Every value in sight, as buttons side by side: radio buttons underneath. A value
				// needing a missing library is greyed, the first other one chosen, as the encoder does.
				$off = $field['disabled_options'] ?? [];
				if (isset($off[$value])) {
					$value = key(array_diff_key($field['options'], $off));
				}
				printf('<div class="takopic-choice" role="radiogroup" aria-labelledby="%s-label">', esc_attr($id));
				foreach ($field['options'] as $option => $label) {
					printf(
						'<label class="takopic-choice__option%s"%s><input type="radio" class="takopic-choice__input" name="%s" value="%s" %s%s><span class="takopic-choice__label">%s</span></label>',
						isset($off[$option]) ? ' takopic-choice__option--disabled' : '',
						/* translators: %s: a library, e.g. pngquant. */
						isset($off[$option]) ? sprintf(' title="%s"', esc_attr(sprintf(__('%s not installed', 'takopic'), $off[$option]))) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here.
						esc_attr($input), esc_attr($option), checked($value, $option, false), esc_attr($disabled || isset($off[$option]) ? ' disabled' : ''), esc_html($label)
					);
				}
				echo '</div>';
			}
			if (!empty($field['unit'])) {
				printf('<span class="takopic-field__unit">%s</span>', esc_html($field['unit']));
			}
			echo '</div>';
		}

		if (empty($field['note'])) {
			printf('<p class="takopic-field__help">%s</p></div>', wp_kses($field['help'], $tags));
			return;
		}
		// A short note, the full help behind a "?" button (HelpToggle.js hides it, shown without JS).
		printf(
			'<p class="takopic-field__help">%s <button type="button" class="takopic-help__toggle js-takopic-help-toggle" aria-expanded="false" aria-controls="%s-help"><span aria-hidden="true">?</span><span class="screen-reader-text">%s</span></button></p><p class="takopic-field__help takopic-help__text" id="%s-help">%s</p></div>',
			wp_kses($field['note'], $tags), esc_attr($id), /* translators: %s: the label of a setting. */ esc_html(sprintf(__('More about: %s', 'takopic'), $field['label'])), esc_attr($id), wp_kses($field['help'], $tags)
		);
	}

	/**
	 * Renders the toggle of a checkbox option, named by
	 * the label of its field. With a label, a bare
	 * switch, for the header of a format card: its label
	 * is read by screen readers, its help shown apart.
	 * An unticked box is not sent: the hidden field
	 * sends 0 instead.
	 *
	 * @param string $name
	 * @param string $label
	 * @since 1.0.0
	 */
	protected function render_toggle($name, $label = '')
	{
		$id = self::field_id($name);
		$input = Settings::OPTION . '[' . $name . ']';
		printf(
			'<input type="hidden" name="%1$s" value="0"><label class="takopic-toggle%2$s"><input type="checkbox" class="takopic-toggle__input" id="%3$s" name="%1$s" value="1"%4$s %5$s><span class="takopic-toggle__track" aria-hidden="true"></span>%6$s</label>',
			esc_attr($input),
			$label ? ' takopic-toggle--switch' : '',
			esc_attr($id),
			$label ? '' : sprintf(' aria-labelledby="%s-label"', esc_attr($id)), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here.
			checked(Settings::get($name), true, false),
			$label ? sprintf('<span class="screen-reader-text">%s</span>', esc_html($label)) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here.
		);
	}

	/**
	 * Returns the id of the control of an option, e.g.
	 * takopic-jpg-quality.
	 *
	 * @param string $name
	 * @return string
	 * @since 1.0.0
	 */
	protected static function field_id($name)
	{
		return 'takopic-' . str_replace('_', '-', $name);
	}

	/**
	 * Returns the state of a format, used for the
	 * dots and badges: on (enabled, and its library
	 * installed or the image editor making it), off
	 * (enabled but nothing can make it) or muted
	 * (disabled).
	 *
	 * @param array $format
	 * @return string
	 * @since 1.0.0
	 */
	public static function state($format)
	{
		if (!$format['enabled']) {
			return 'muted';
		}
		return $format['engine'] ? 'on' : 'off';
	}

	/**
	 * Returns the label of a format state, see state().
	 *
	 * @param string $state
	 * @return string
	 * @since 1.0.0
	 */
	protected static function state_label($state)
	{
		$labels = [
			'on' => __('Active', 'takopic'),
			'off' => __('Library unavailable', 'takopic'),
			'muted' => __('Disabled', 'takopic'),
		];
		return $labels[$state];
	}
}
