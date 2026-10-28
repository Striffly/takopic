<?php

/**
 * install
 *
 * How to install the library of a format, by system:
 * its command, and where to learn more. Rendered by
 * View::render_install(), with $takopic_install
 * the format (jpg, png, webp, avif), or oxipng shown
 * while optipng compresses the PNG images.
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

$takopic_more = __('More information', 'takopic');
$takopic_windows = __('Download for Windows', 'takopic');
$takopic_steps = [
	'jpg' => [
		['os' => 'Linux', 'command' => 'sudo apt-get install jpegoptim', 'link' => ['https://vitux.com/optimize-jpeg-jpg-images-in-ubuntu-with-jpegoptim/', $takopic_more]],
		['os' => 'Mac', 'command' => 'brew install jpegoptim', 'link' => ['https://formulae.brew.sh/formula/jpegoptim', $takopic_more]],
		['os' => 'Windows', 'link' => ['https://github.com/XhmikosR/jpegoptim-windows', $takopic_windows]],
	],
	'png' => [
		['os' => 'Linux', 'command' => 'sudo apt install optipng pngquant', 'link' => ['https://packages.debian.org/search?keywords=pngquant', $takopic_more]],
		[
			'os' => __('Faster', 'takopic'),
			'command' => 'sudo apt install oxipng',
			'html' => sprintf(
				/* translators: %s: the URL of the releases of oxipng. */
				__('replaces optipng, on recent systems, or from its <a href="%s" target="_blank" rel="noopener">releases</a>', 'takopic'),
				'https://github.com/oxipng/oxipng/releases'
			),
		],
		['os' => 'Mac', 'command' => 'brew install oxipng pngquant', 'link' => ['https://formulae.brew.sh/formula/oxipng', $takopic_more]],
	],
	'oxipng' => [
		['os' => 'Linux', 'command' => 'sudo apt install oxipng', 'link' => ['https://github.com/oxipng/oxipng/releases', $takopic_more]],
		['os' => 'Mac', 'command' => 'brew install oxipng', 'link' => ['https://formulae.brew.sh/formula/oxipng', $takopic_more]],
	],
	'webp' => [
		['os' => 'Linux', 'command' => 'sudo apt-get install webp', 'link' => ['https://www.interserver.net/tips/kb/install-and-serve-webp-images-on-ubuntu/', $takopic_more]],
		['os' => 'Mac', 'command' => 'brew install webp', 'link' => ['https://formulae.brew.sh/formula/webp', $takopic_more]],
		['os' => 'Windows', 'link' => ['https://developers.google.com/speed/webp/download', $takopic_windows]],
	],
	'avif' => [
		['os' => 'Linux', 'text' => __('Depending on your distribution, you may need to build it from source.', 'takopic'), 'link' => ['https://aomediacodec.github.io/av1-avif/', $takopic_more]],
		['os' => 'Mac', 'command' => 'brew install libavif', 'link' => ['https://formulae.brew.sh/formula/libavif', $takopic_more]],
		['os' => 'Windows', 'link' => ['https://github.com/AOMediaCodec/libavif/releases', $takopic_windows]],
	],
];

?>
<ul class="takopic-install__list">
	<?php foreach ($takopic_steps[$takopic_install] as $takopic_step) : ?>
		<li>
			<span class="takopic-install__os"><?php echo esc_html($takopic_step['os']); ?></span>
			<?php if (isset($takopic_step['command'])) : ?>
				<?php $this->render_command($takopic_step['command']); ?>
			<?php endif; ?>
			<?php if (isset($takopic_step['text'])) : ?>
				<?php echo esc_html($takopic_step['text']); ?>
			<?php endif; ?>
			<?php if (isset($takopic_step['html'])) : ?>
				<span><?php echo wp_kses($takopic_step['html'], ['a' => ['href' => true, 'target' => true, 'rel' => true]]); ?></span>
			<?php endif; ?>
			<?php if (isset($takopic_step['link'])) : ?>
				<a href="<?php echo esc_url($takopic_step['link'][0]); ?>" target="_blank" rel="noopener"><?php echo esc_html($takopic_step['link'][1]); ?></a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
