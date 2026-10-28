<?php

/**
 * DeliveryTab
 *
 * The delivery tab of the options page: serving
 * the WebP and AVIF files, its test, the rules of
 * the server and the helper of the themes.
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
use Takopic\Delivery\Htaccess;
use Takopic\Encoders\Formats;
use Takopic\Media\Status;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class DeliveryTab extends View
{
	/**
	 * Renders the delivery of the WebP and AVIF files,
	 * in three steps side by side: generate them (how
	 * many images have them), serve them (in the HTML or
	 * from the server) and test what the server sends.
	 * The results of the test come below, then the rules
	 * of the server and the theme helper, folded.
	 *
	 * @param array $display
	 * @param array $formats
	 * @param array $counts
	 * @since 1.0.0
	 */
	public function render($display, $formats, $counts)
	{
		$server = Htaccess::server();
		$names = ['apache' => 'Apache', 'nginx' => 'nginx', 'other' => __('Your server', 'takopic')];
		$made = Status::with_versions();
		$total = $counts['total'];
		$percent = $total ? min(100, (int)round($made / $total * 100)) : 0;
		$rewrite = $display['fields']['serve_rewrite'];
		if ($server === 'apache') {
			// Takopic writes the rules: the box says so.
			/* translators: %s: uploads/.htaccess */
			$rewrite['note'] = sprintf(__('Apache: ticking it writes the rules below in %s, unticking it removes them.', 'takopic'), '<code>uploads/.htaccess</code>');
		} else {
			// Takopic cannot write the rules: the option tells they are in place.
			/* translators: %s: the server, e.g. nginx. */
			$rewrite['note'] = sprintf(__('%s: add the rules below to its configuration, then tick this box.', 'takopic'), esc_html($names[$server]));
			/* translators: 1: the server, e.g. nginx, 2: .htaccess. */
			$rewrite['help'] = sprintf(__('%1$s does not read %2$s files: add the rules below to its configuration, then tick this box so that Takopic counts the bytes saved. The HTML does not change, and every image is served, CSS backgrounds and custom themes included.', 'takopic'), esc_html($names[$server]), '<code>.htaccess</code>');
		}
		?>
		<ol class="takopic-steps">
			<li class="takopic-step">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">1</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php esc_html_e('Generate', 'takopic'); ?></h2>
						<p class="takopic-step__description"><?php esc_html_e('The WebP and AVIF versions are made while the images are optimised.', 'takopic'); ?></p>
					</div>
				</header>
				<div class="takopic-step__body takopic-generate js-takopic-live" id="takopic-delivery-generate">
					<p class="takopic-generate__value">
						<strong><?php echo esc_html(number_format_i18n($made)); ?></strong>
						<?php
						/* translators: %s: the number of images. */
						echo esc_html(sprintf(_n('of %s image has its WebP or AVIF version', 'of %s images have their WebP or AVIF version', $total, 'takopic'), number_format_i18n($total)));
						?>
					</p>
					<span class="takopic-meter" role="img" aria-label="<?php echo esc_attr($percent . ' %'); ?>"><span class="takopic-meter__value" style="width: <?php echo esc_attr($percent); ?>%"></span></span>
					<ul class="takopic-generate__formats">
						<?php foreach (array_keys(Formats::variants()) as $id) : ?>
							<?php $state = self::state($formats[$id]); ?>
							<li>
								<a class="takopic-chip js-takopic-tab-link" href="#settings-<?php echo esc_attr($id); ?>" data-tab="settings" data-target="takopic-settings-<?php echo esc_attr($id); ?>">
									<span class="takopic-dot takopic-dot--<?php echo esc_attr($state); ?>" aria-hidden="true"></span>
									<span class="takopic-chip__name"><?php echo esc_html($formats[$id]['title']); ?></span>
									<span class="takopic-chip__state"><?php echo esc_html(self::state_label($state)); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ($made < $total) : ?>
						<a class="button takopic-generate__button js-takopic-tab-link" href="#optimise" data-tab="optimise"><?php esc_html_e('Optimise the media library', 'takopic'); ?></a>
					<?php endif; ?>
				</div>
			</li>

			<li class="takopic-step takopic-step--wide">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">2</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php echo esc_html($display['title']); ?></h2>
						<p class="takopic-step__description"><?php echo esc_html($display['description']); ?></p>
					</div>
				</header>
				<div class="takopic-step__body takopic-methods">
					<div class="takopic-method">
						<?php $this->render_field('serve_picture', $display['fields']['serve_picture']); ?>
					</div>
					<div class="takopic-method">
						<?php $this->render_field('serve_rewrite', $rewrite); ?>
					</div>
				</div>
				<footer class="takopic-step__actions takopic-save">
					<?php submit_button(__('Save changes', 'takopic'), 'primary', 'submit', false, ['id' => 'takopic-save-delivery']); ?>
				</footer>
			</li>

			<li class="takopic-step takopic-test">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">3</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php esc_html_e('Test', 'takopic'); ?></h2>
						<p class="takopic-step__description"><?php esc_html_e('Requests an optimised image as a browser would.', 'takopic'); ?></p>
					</div>
				</header>
				<div class="takopic-step__body">
					<ul class="takopic-test__list">
						<li><?php esc_html_e('Are the WebP and AVIF files served with their type?', 'takopic'); ?></li>
						<li><?php esc_html_e('Does the server answer the image with them?', 'takopic'); ?></li>
						<li><?php esc_html_e('Are the originals kept private?', 'takopic'); ?></li>
					</ul>
					<button type="button" class="button button-primary takopic-test__button js-takopic-delivery-test"><?php esc_html_e('Run the test', 'takopic'); ?></button>
					<p class="takopic-test__summary js-takopic-test-summary" aria-live="polite" hidden></p>
				</div>
			</li>
		</ol>

		<div class="takopic-card takopic-test__result js-takopic-test-result" hidden>
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Test results', 'takopic'); ?></h2>
					<p class="takopic-card__description takopic-test__sample js-takopic-test-sample"></p>
				</div>
			</header>
			<div class="takopic-card__body">
				<ul class="takopic-checks js-takopic-test-checks"></ul>
				<template class="js-takopic-check-template">
					<li class="takopic-checks__item js-takopic-check">
						<span class="takopic-checks__icon dashicons" aria-hidden="true"></span>
						<span class="takopic-checks__label js-takopic-check-label"></span>
						<span class="takopic-checks__detail js-takopic-check-detail"></span>
						<span class="takopic-checks__advice js-takopic-check-advice"></span>
					</li>
				</template>
			</div>
		</div>

		<h2 class="takopic-heading"><?php esc_html_e('Rules and code', 'takopic'); ?></h2>
		<?php if ($server !== 'nginx') : ?>
			<details class="takopic-fold">
				<summary class="takopic-fold__summary">
					<span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for Apache and LiteSpeed', 'takopic'); ?></span>
					<?php if ($server === 'apache') : ?>
						<?php $written = Htaccess::rules_written(); ?>
						<?php $this->render_badge($written ? 'on' : 'muted', $written ? __('Written in uploads/.htaccess', 'takopic') : __('Not written', 'takopic'), 'takopic-delivery-rules-state'); ?>
					<?php endif; ?>
				</summary>
				<div class="takopic-fold__content">
					<p class="takopic-fold__text">
						<?php
						$this->printf_html(
							$server === 'apache'
								/* translators: %s: uploads/.htaccess */
								? __('Takopic writes them in %s when the server option is on, and removes them when it is off.', 'takopic')
								/* translators: %s: uploads/.htaccess */
								: __('In %s, written by Takopic when the server option is on.', 'takopic'),
							'<code>uploads/.htaccess</code>'
						);
						?>
					</p>
					<?php $this->render_code(implode("\n", Htaccess::apache_rules()), 'takopic-code-apache'); ?>
				</div>
			</details>
		<?php endif; ?>
		<?php if ($server !== 'apache') : ?>
			<details class="takopic-fold">
				<summary class="takopic-fold__summary"><span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for nginx', 'takopic'); ?></span></summary>
				<div class="takopic-fold__content">
					<p class="takopic-fold__text"><?php esc_html_e('Add them to the configuration of the site, reload nginx, then tick the server option.', 'takopic'); ?></p>
					<?php $this->render_code(Htaccess::nginx_rules(), 'takopic-code-nginx'); ?>
				</div>
			</details>
		<?php endif; ?>
		<?php $this->render_theme_help(); ?>
		<?php
	}

	/**
	 * Renders how to use takopic_image() in a custom
	 * theme, whose images the display option does not
	 * change.
	 *
	 * @since 1.0.0
	 */
	private function render_theme_help()
	{
		$code = "<?php\nif (function_exists('takopic_image')) {\n\techo takopic_image(\$image_id, 'my-class');\n} else {\n\techo wp_get_attachment_image(\$image_id, 'full');\n}";
		?>
		<details class="takopic-fold">
			<summary class="takopic-fold__summary">
				<span class="takopic-fold__title">
					<?php
					/* translators: %s: takopic_image() */
					$this->printf_html(__('Custom themes: the %s helper', 'takopic'), '<code>takopic_image()</code>');
					?>
				</span>
			</summary>
			<div class="takopic-fold__content">
				<p class="takopic-fold__text">
					<?php
					/* translators: 1: <picture>, 2: takopic_image() */
					$this->printf_html(__('Images output with your own markup (e.g. from an ACF field) are not wrapped in a %1$s by the HTML option: serve them from the server, or use the %2$s helper, which returns their %1$s whatever the options.', 'takopic'), '<code>&lt;picture&gt;</code>', '<code>takopic_image()</code>');
					?>
				</p>
				<?php $this->render_code($code, 'takopic-code-theme'); ?>
				<p class="takopic-fold__text">
					<?php
					$this->printf_html(
						/* translators: 1: <picture>, 2: true, 3: data-src, 4: data-srcset, 5: function_exists() */
						__('The second argument is the class of the %1$s element. Pass %2$s as third argument to output %3$s and %4$s for a JavaScript lazy loader. Keep the %5$s check: without it, the pages break while Takopic is deactivated.', 'takopic'),
						'<code>&lt;picture&gt;</code>',
						'<code>true</code>',
						'<code>data-src</code>',
						'<code>data-srcset</code>',
						'<code>function_exists()</code>'
					);
					?>
				</p>
			</div>
		</details>
		<?php
	}
}
