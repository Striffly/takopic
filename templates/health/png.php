<?php

/**
 * png
 *
 * PNG installation instructions: optipng, or
 * oxipng (faster) which replaces it when
 * installed, and pngquant for the lossy
 * compression.
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
	PNG
	===================== -->
<ul class="takopic-install__list">
	<li>
		<span class="takopic-install__os">Linux</span>
		<code>sudo apt install optipng pngquant</code>
		<a href="https://packages.debian.org/search?keywords=pngquant" target="_blank" rel="noopener"><?php esc_html_e('More information', 'takopic'); ?></a>
	</li>
	<li>
		<span class="takopic-install__os"><?php esc_html_e('Faster', 'takopic'); ?></span>
		<code>sudo apt install oxipng</code>
		<span>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: the URL of the releases of oxipng. */
					__('replaces optipng, on recent systems, or from its <a href="%s" target="_blank" rel="noopener">releases</a>', 'takopic'),
					'https://github.com/oxipng/oxipng/releases'
				),
				['a' => ['href' => true, 'target' => true, 'rel' => true]]
			);
			?>
		</span>
	</li>
	<li>
		<span class="takopic-install__os">Mac</span>
		<code>brew install oxipng pngquant</code>
		<a href="https://formulae.brew.sh/formula/oxipng" target="_blank" rel="noopener"><?php esc_html_e('More information', 'takopic'); ?></a>
	</li>
</ul>
