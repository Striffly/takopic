<?php

/**
 * SettingsTab
 *
 * The settings tab of the options page: the
 * uploads, then a card per format.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Admin\Tabs;

use Takopic\Admin\View;
use Takopic\Encoders\Formats;
use Takopic\Engines\Libraries;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class SettingsTab extends View
{
	/**
	 * Renders the settings: the image size, then one
	 * compact card per format, two per row on wide
	 * screens: its name, state and switch, the library
	 * making it, its quality fields, and how to install
	 * a missing library, folded.
	 *
	 * @param array $general
	 * @param array $formats
	 * @since 1.0.0
	 */
	public function render($general, $formats)
	{
		?>
		<div class="takopic-card" id="takopic-settings-general">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-upload" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php echo esc_html($general['title']); ?></h2>
					<p class="takopic-card__description"><?php echo esc_html($general['description']); ?></p>
				</div>
			</header>
			<div class="takopic-card__body takopic-fields">
				<?php foreach ($general['fields'] as $name => $field) : ?>
					<?php $this->render_field($name, $field); ?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="takopic-formats">
			<?php foreach ($formats as $id => $format) : ?>
				<?php
				$state = self::state($format);
				$switch = $id . '_enable';
				$fields = $format['fields'];
				unset($fields[$switch]);
				?>
				<?php // Its state follows the settings as they are saved (Form.js): its class only, its fields kept. ?>
				<section class="takopic-format takopic-format--<?php echo esc_attr($state); ?> js-takopic-live" id="takopic-settings-<?php echo esc_attr($id); ?>" data-live="class">
					<header class="takopic-format__header">
						<div class="takopic-format__top">
							<h2 class="takopic-format__title"><?php echo esc_html($format['title']); ?></h2>
							<?php $this->render_badge($state, self::state_label($state), 'takopic-settings-' . $id . '-state'); ?>
							<?php $this->render_toggle($switch, $format['fields'][$switch]['label']); ?>
						</div>
						<div class="takopic-format__engines">
							<?php $this->render_engine($format); ?>
						</div>
					</header>
					<p class="takopic-format__description"><?php echo wp_kses($format['fields'][$switch]['help'], ['strong' => [], 'code' => []]); ?></p>
					<?php if (!Libraries::can_execute()) : ?>
						<?php // Installing would not help: the overview says why. ?>
					<?php elseif (!$format['installed'] && $format['engine']) : ?>
						<?php
						/* translators: %s: a library, e.g. cwebp. */
						$this->render_install($id, sprintf(__('Recommended: install %s', 'takopic'), $format['library']), sprintf($format['recommendation'], $format['engine']));
						?>
					<?php elseif (!$format['installed']) : ?>
						<?php
						/* translators: %s: a library, e.g. cwebp. */
						$this->render_install($id, sprintf(__('How to install %s', 'takopic'), $format['library']));
						?>
					<?php elseif ($format['upgrade']) : ?>
						<?php
						/* translators: %s: a library, e.g. cwebp. */
						$this->render_install($format['preferred'], sprintf(__('Recommended: install %s', 'takopic'), $format['preferred']), $format['preference']);
						?>
					<?php endif; ?>
					<details class="takopic-format__settings js-takopic-fold">
						<summary class="takopic-format__summary"><?php esc_html_e('Settings', 'takopic'); ?></summary>
						<div class="takopic-format__fields takopic-fields">
							<?php foreach ($fields as $name => $field) : ?>
								<?php $this->render_field($name, $field + Formats::availability($name)); ?>
							<?php endforeach; ?>
							<?php foreach ($format['extras'] as $name => $installed) : ?>
								<?php if ($format['installed'] && !$installed) : ?>
									<?php
									/* translators: 1: a library, e.g. pngquant, 2: what it is for, e.g. lossy compression. */
									$this->render_install($id, sprintf(__('How to install %1$s, for the %2$s', 'takopic'), $name, $format['uses'][$name]), '', true);
									?>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</details>
				</section>
			<?php endforeach; ?>
		</div>

		<div class="takopic-page__save takopic-save">
			<?php submit_button(__('Save changes', 'takopic'), 'primary', 'submit', false, ['id' => 'takopic-save-settings']); ?>
		</div>
		<?php
	}
}
