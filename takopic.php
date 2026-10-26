<?php

/**
 * Plugin Name:     Takopic – Local Image Optimizer | Convert WebP & AVIF
 * Plugin URI:      https://github.com/Striffly/takopic
 * Description:     Compresses JPG and PNG images and converts them to WebP and AVIF on your own server, with cwebp, avifenc, jpegoptim and optipng. Free, unlimited, no API key.
 * Author:          Clément Gayot
 * Author URI:      https://github.com/Striffly
 * Text Domain:     takopic
 * Version:         0.1.4
 * Requires at least: 6.0
 * Requires PHP:    8.2
 * License:         GPLv3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package         Takopic
 * @repo            https://github.com/Striffly/takopic
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// The version of Takopic, set by npm run release with the header above.
define('TAKOPIC_VERSION', '0.1.4');
define('TAKOPIC_FILE', __FILE__);

require_once __DIR__ . '/autoload.php';

// The dependencies, prefixed by Strauss, are made by composer install.
if (!file_exists(__DIR__ . '/vendor-prefixed/autoload.php')) {
	add_action('admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p><strong>Takopic</strong>: %s</p></div>',
			/* translators: %s: composer install */
			sprintf(esc_html__('its dependencies are missing, run %s in the plugin folder.', 'takopic'), '<code>composer install</code>') // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped, with a <code>.
		);
	});
}

// Boots Takopic once the plugins are loaded, so that other plugins can hook in first.
add_action('plugins_loaded', ['Takopic\\Plugin', 'boot']);

register_activation_hook(__FILE__, ['Takopic\\Lifecycle', 'activate']);
register_deactivation_hook(__FILE__, ['Takopic\\Lifecycle', 'deactivate']);

if (defined('WP_CLI') && WP_CLI) {
	WP_CLI::add_command('takopic', Takopic\Cli\Commands::class);
}

// takopic_image(), for the themes.
require __DIR__ . '/functions/functions.php';
