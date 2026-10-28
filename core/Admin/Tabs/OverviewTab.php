<?php

/**
 * OverviewTab
 *
 * The overview tab of the options page: the bytes
 * saved, the images by status and the state of
 * each format.
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
use Takopic\Engines\Libraries;
use Takopic\Media\Stats;
use Takopic\Media\Status;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class OverviewTab extends View
{
	/**
	 * Renders the overview: the bytes saved, the images
	 * by status and the state of each format.
	 *
	 * @param array $formats
	 * @param array $counts
	 * @since 1.0.0
	 */
	public function render($formats, $counts)
	{
		$savings = Stats::savings();
		?>
		<?php if (!Libraries::can_execute()) : ?>
			<div class="notice notice-error inline takopic-alert">
				<p>
					<strong><?php esc_html_e('PHP cannot run programs on this server.', 'takopic'); ?></strong>
					<?php
					$this->printf_html(
						/* translators: 1: proc_open(), 2: exec(), 3: disable_functions */
						__('%1$s and %2$s are disabled (%3$s): the libraries cannot run, even installed, so the JPG and PNG images are not compressed. Ask your host to enable %1$s. Meanwhile, GD or Imagick make the WebP and AVIF when they can.', 'takopic'),
						'<code>proc_open()</code>',
						'<code>exec()</code>',
						'<code>disable_functions</code>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<div class="takopic-card takopic-card--hero">
			<div class="takopic-savings js-takopic-savings">
				<div class="takopic-savings__main js-takopic-savings-main"<?php echo $savings['images'] ? '' : ' hidden'; ?>>
					<p class="takopic-savings__value">
						<?php
						/* translators: %s: a size, e.g. 12.5 MB. */
						$this->printf_html(__('%s saved', 'takopic'), '<span class="js-takopic-savings-value" data-savings="saved">' . esc_html($savings['saved']) . '</span>');
						?>
					</p>
					<?php
					// Before the page paints, shows 0 over the size: it rolls up the first time the overview shows (Savings.js).
					wp_print_inline_script_tag(
						"(() => {
							const saved = document.currentScript.previousElementSibling.querySelector('.js-takopic-savings-value');
							if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
								saved.dataset.value = saved.textContent.replace(/\d(?:[\d.,\s]*\d)?/, (number) => (/[.,]\d$/.test(number) ? '0' + number.at(-2) + '0' : '0'));
								saved.classList.add('takopic-savings__rolling');
							}
						})();"
					);
					?>
					<p class="takopic-savings__detail">
						<?php
						/* translators: %s: a reduction, e.g. −48 %. */
						$this->printf_html(__('%s on what the site serves', 'takopic'), '<span class="takopic-savings__reduction js-takopic-savings-value" data-savings="reduction">' . esc_html($savings['reduction']) . '</span>');
						?>
						<span class="takopic-savings__hint js-takopic-savings-hint"<?php echo $savings['hint'] ? '' : ' hidden'; ?>>
							&middot; <a class="js-takopic-tab-link" href="#delivery" data-tab="delivery">
								<?php
								/* translators: %s: a reduction, e.g. −62 %. */
								$this->printf_html(__('%s with WebP and AVIF served', 'takopic'), '<span class="js-takopic-savings-value" data-savings="modern">' . esc_html($savings['modern']) . '</span>');
								?>
							</a>
						</span>
					</p>
					<p class="takopic-disk">
						<span class="takopic-disk__label">
							<span class="dashicons dashicons-database" aria-hidden="true"></span>
							<?php esc_html_e('On the disk', 'takopic'); ?>
						</span>
						<span class="takopic-disk__item">
							<?php
							/* translators: %s: the size of the original images, e.g. 4.2 MB. */
							$this->printf_html(__('%s of originals kept', 'takopic'), '<strong class="js-takopic-savings-value" data-savings="originals">' . esc_html($savings['originals']) . '</strong>');
							?>
						</span>
						<span class="takopic-disk__item">
							<?php
							/* translators: %s: the size of the WebP and AVIF files, e.g. 916.6 KB. */
							$this->printf_html(__('%s of WebP and AVIF', 'takopic'), '<strong class="js-takopic-savings-value" data-savings="formats">' . esc_html($savings['formats']) . '</strong>');
							?>
						</span>
					</p>
				</div>
				<div class="takopic-savings__empty js-takopic-savings-empty"<?php echo $savings['images'] ? ' hidden' : ''; ?>>
					<p class="takopic-savings__value"><?php esc_html_e('Ready to squeeze', 'takopic'); ?></p>
					<p class="takopic-savings__detail"><?php esc_html_e('Takopic optimises your images on your own server: free, unlimited, and they never leave it.', 'takopic'); ?></p>
				</div>
				<?php // Filled while new images wait for their optimisation, not those optimised with older settings. ?>
				<a class="takopic-cta js-takopic-cta js-takopic-tab-link<?php echo $counts[Status::TO_OPTIMISE] ? ' takopic-cta--filled' : ''; ?>" href="#optimise" data-tab="optimise">
					<?php esc_html_e('Optimise the media library', 'takopic'); ?>
					<span class="takopic-cta__arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				</a>
			</div>
			<ul class="takopic-counts">
				<?php foreach (self::count_labels() as $status => $label) : ?>
					<li class="takopic-counts__item takopic-counts__item--<?php echo esc_attr($status); ?>">
						<span class="takopic-counts__value js-takopic-count" data-count="<?php echo esc_attr($status); ?>"><?php echo esc_html(number_format_i18n($counts[$status])); ?></span>
						<span class="takopic-counts__label"><?php echo esc_html($label); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<h2 class="takopic-heading"><?php esc_html_e('Formats', 'takopic'); ?></h2>
		<div class="takopic-libraries js-takopic-live" id="takopic-overview-formats">
			<?php foreach ($formats as $id => $format) : ?>
				<?php $state = self::state($format); ?>
				<a class="takopic-library takopic-library--<?php echo esc_attr($state); ?> js-takopic-tab-link" href="#settings-<?php echo esc_attr($id); ?>" data-tab="settings" data-target="takopic-settings-<?php echo esc_attr($id); ?>">
					<span class="takopic-library__head">
						<span class="takopic-library__format"><?php echo esc_html($format['title']); ?></span>
						<?php $this->render_badge($state, self::state_label($state)); ?>
					</span>
					<?php $this->render_engine($format); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Returns the labels of the counts of the overview,
	 * by status: a number of images, where the media
	 * column labels one image (MediaColumn::labels()).
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function count_labels()
	{
		return [
			Status::OPTIMISED => _x('Optimised', 'images', 'takopic'),
			Status::TO_OPTIMISE => _x('To optimise', 'images', 'takopic'),
			Status::OUTDATED => _x('Older settings', 'images', 'takopic'),
			Status::ERROR => _x('Error', 'images', 'takopic'),
		];
	}
}
