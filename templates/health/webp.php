<?php

/**
 * webp
 *
 * WebP installation instructions, shown
 * when cwebp is not installed.
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
	WebP
	===================== -->
<ul class="takopic-install__list">
	<li>
		<span class="takopic-install__os">Linux</span>
		<?php $this->render_command('sudo apt-get install webp'); ?>
		<a href="https://www.interserver.net/tips/kb/install-and-serve-webp-images-on-ubuntu/" target="_blank" rel="noopener"><?php esc_html_e('More information', 'takopic'); ?></a>
	</li>
	<li>
		<span class="takopic-install__os">Mac</span>
		<?php $this->render_command('brew install webp'); ?>
		<a href="https://formulae.brew.sh/formula/webp" target="_blank" rel="noopener"><?php esc_html_e('More information', 'takopic'); ?></a>
	</li>
	<li>
		<span class="takopic-install__os">Windows</span>
		<a href="https://developers.google.com/speed/webp/download" target="_blank" rel="noopener"><?php esc_html_e('Download for Windows', 'takopic'); ?></a>
	</li>
</ul>
