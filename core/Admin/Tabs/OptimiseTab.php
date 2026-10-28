<?php

/**
 * OptimiseTab
 *
 * The optimise tab of the options page: the jobs
 * optimising the media library.
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
use Takopic\Media\Status;
use Takopic\Settings;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class OptimiseTab extends View
{
	/**
	 * Renders the optimisation of the media library:
	 * the new images, the outdated ones, or all.
	 *
	 * @param array $counts
	 * @param bool $jobs
	 * @since 1.0.0
	 */
	public function render($counts, $jobs)
	{
		?>
		<div class="takopic-card">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-images-alt2" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Optimise the media library', 'takopic'); ?></h2>
					<p class="takopic-card__description"><?php esc_html_e('These jobs run in the background, page closed or not, with the lowest priority on the server.', 'takopic'); ?></p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<?php $done = $counts['total'] > 0 && $counts[Status::OPTIMISED] === $counts['total']; ?>
				<div class="takopic-done js-takopic-all-optimised" role="status"<?php echo $done ? '' : ' hidden'; ?>>
					<span class="takopic-confetti js-takopic-confetti" aria-hidden="true"></span>
					<span class="takopic-done__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<p class="takopic-done__text js-takopic-live" id="takopic-optimise-done">
						<strong><?php esc_html_e('All your images are optimised.', 'takopic'); ?></strong>
						<?php if (Settings::get('optimise_uploads')) : ?>
							<?php esc_html_e('Nothing left to do: the new ones are optimised on upload.', 'takopic'); ?>
						<?php else : ?>
							<?php esc_html_e('The new uploads will wait here, to optimise when you like.', 'takopic'); ?>
						<?php endif; ?>
					</p>
				</div>
				<div class="takopic-actions">
					<div class="takopic-action takopic-action--primary">
						<span class="takopic-action__icon dashicons dashicons-format-image" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('New images', 'takopic'); ?></h3>
						<p class="takopic-action__count js-takopic-count" data-count="new"><?php echo esc_html(number_format_i18n($counts[Status::TO_OPTIMISE] + $counts[Status::ERROR])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Not optimised yet, or their optimisation failed.', 'takopic'); ?></p>
						<button type="button" class="button button-primary takopic-action__button js-takopic-job-start" data-job="new" disabled><?php esc_html_e('Optimise new images', 'takopic'); ?></button>
					</div>
					<div class="takopic-action">
						<span class="takopic-action__icon dashicons dashicons-update" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('Outdated images', 'takopic'); ?></h3>
						<p class="takopic-action__count js-takopic-count" data-count="outdated"><?php echo esc_html(number_format_i18n($counts[Status::OUTDATED])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Optimised before a quality, a format, the maximum size or the thumbnail sizes changed. Optimised again from their originals.', 'takopic'); ?></p>
						<button type="button" class="button takopic-action__button js-takopic-job-start" data-job="outdated" disabled><?php esc_html_e('Re-optimise outdated images', 'takopic'); ?></button>
					</div>
					<div class="takopic-action">
						<span class="takopic-action__icon dashicons dashicons-images-alt" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('All images', 'takopic'); ?></h3>
						<p class="takopic-action__count js-takopic-count" data-count="total"><?php echo esc_html(number_format_i18n($counts['total'])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Every image optimised again from its original, with its thumbnails made again.', 'takopic'); ?></p>
						<button type="button" class="button takopic-action__button js-takopic-job-start" data-job="all" data-confirm="<?php esc_attr_e('Re-optimise all images from their originals, with their thumbnails? This can take a while.', 'takopic'); ?>" disabled><?php esc_html_e('Re-optimise all images', 'takopic'); ?></button>
					</div>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
			<p class="takopic-card__footer js-takopic-live" id="takopic-optimise-footer">
				<?php if (Settings::get('optimise_uploads')) : ?>
					<span class="takopic-uploads takopic-uploads--on"><?php esc_html_e('New uploads are optimised right away.', 'takopic'); ?></span>
				<?php else : ?>
					<span class="takopic-uploads takopic-uploads--off">
						<?php esc_html_e('New uploads wait here, as new images.', 'takopic'); ?>
						<a class="js-takopic-tab-link" href="#settings" data-tab="settings" data-target="takopic-settings-general"><?php esc_html_e('Optimise them on upload', 'takopic'); ?></a>
					</span>
				<?php endif; ?>
				<a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>"><?php esc_html_e('See each image in the Media library', 'takopic'); ?></a>
			</p>
		</div>
		<?php
	}
}
