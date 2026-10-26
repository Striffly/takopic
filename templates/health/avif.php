<?php

/**
 * avif
 *
 * AVIF installation instructions, shown
 * when avifenc is not installed.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Templates
 * @repo        https://github.com/Striffly/takopic
 *
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

?>

<!-- =====================
	AVIF
	===================== -->
<ul class="takopic-install__list">
	<li>
		<span class="takopic-install__os">Linux</span>
		<?php esc_html_e('Depending on your distribution, you may need to build it from source.', 'takopic'); ?>
		<a href="https://aomediacodec.github.io/av1-avif/" target="_blank" rel="noopener"><?php esc_html_e('More information', 'takopic'); ?></a>
	</li>
	<li>
		<span class="takopic-install__os">Mac</span>
		<?php $this->render_command('brew install libavif'); ?>
		<a href="https://formulae.brew.sh/formula/libavif" target="_blank" rel="noopener"><?php esc_html_e('More information', 'takopic'); ?></a>
	</li>
	<li>
		<span class="takopic-install__os">Windows</span>
		<a href="https://github.com/AOMediaCodec/libavif/releases" target="_blank" rel="noopener"><?php esc_html_e('Download for Windows', 'takopic'); ?></a>
	</li>
</ul>
