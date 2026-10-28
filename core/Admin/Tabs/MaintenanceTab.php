<?php

/**
 * MaintenanceTab
 *
 * The maintenance tab of the options page:
 * removing the WebP and AVIF files, restoring the
 * original images.
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
use Takopic\Media\Cleaner;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class MaintenanceTab extends View
{
	/**
	 * Renders the maintenance: removing the WebP and
	 * AVIF files, restoring the original images. Each
	 * asks to tick a box first.
	 *
	 * @param bool $jobs
	 * @since 1.0.0
	 */
	public function render($jobs)
	{
		?>
		<div class="takopic-card">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-trash" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Remove WebP and AVIF', 'takopic'); ?></h2>
					<p class="takopic-card__description">
						<?php
						/* translators: %s: the number of images. */
						$this->printf_html(__('Deletes the WebP and AVIF files of the %s images processed. The JPG and PNG files stay compressed, and the originals kept.', 'takopic'), '<strong class="js-takopic-count" data-count="processed">' . esc_html(number_format_i18n(count(Cleaner::ids()))) . '</strong>');
						?>
					</p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<div class="takopic-confirm">
					<label class="takopic-confirm__label"><input type="checkbox" class="takopic-confirm__input js-takopic-job-confirm" data-confirm-for="remove_formats"> <?php esc_html_e('I understand the WebP and AVIF files will be deleted, until the images are optimised again.', 'takopic'); ?></label>
					<button type="button" class="button takopic-confirm__button js-takopic-job-start" data-job="remove_formats" disabled><?php esc_html_e('Remove WebP and AVIF', 'takopic'); ?></button>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
		</div>

		<div class="takopic-card takopic-card--danger">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-backup" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Restore original images', 'takopic'); ?></h2>
					<p class="takopic-card__description"><?php esc_html_e('Puts the original images back and deletes every file made by Takopic (WebP, AVIF, backups). Do it before deleting Takopic: deleting it keeps the files as they are.', 'takopic'); ?></p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<div class="takopic-confirm">
					<label class="takopic-confirm__label"><input type="checkbox" class="takopic-confirm__input js-takopic-job-confirm" data-confirm-for="restore"> <?php esc_html_e('I understand the images will no longer be optimised, and their WebP and AVIF files will be deleted.', 'takopic'); ?></label>
					<button type="button" class="button takopic-confirm__button takopic-button--danger js-takopic-job-start" data-job="restore" disabled><?php esc_html_e('Restore original images', 'takopic'); ?></button>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
