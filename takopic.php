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
 * Update URI:      https://github.com/Striffly/takopic
 *
 * @package         Takopic
 * @repo            https://github.com/Striffly/takopic
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// The version of Takopic, set by npm run release with the header above.
define('TAKOPIC_VERSION', '0.1.4');

require_once __DIR__ . '/autoload.php';

// The dependencies, prefixed by Strauss, are made by composer install.
if (!file_exists(__DIR__ . '/vendor-prefixed/autoload.php')) {
	add_action('admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Takopic</strong>: its dependencies are missing, run <code>composer install</code> in the plugin folder.</p></div>';
	});
}

// Boot Takopic (Once).
Takopic\Plugin::boot();

register_activation_hook(__FILE__, ['Takopic\\Lifecycle', 'activate']);
register_deactivation_hook(__FILE__, ['Takopic\\Lifecycle', 'deactivate']);

if (defined('WP_CLI') && WP_CLI) {
	WP_CLI::add_command('takopic', Takopic\Cli\Commands::class);
}

// Require Functions.
if (file_exists(__DIR__ . '/functions/functions.php')) {
	require(__DIR__ . '/functions/functions.php');
}

if (!function_exists('takopic_add_settings_link')) {

	/**
	 * Add Settings Link.
	 *
	 * @param $links
	 * @return mixed
	 * @since 0.1.0
	 * @date 24/11/2021
	 */
	function takopic_add_settings_link($links)
	{
		$links[] = '<a href="' .
			Takopic\Admin\SettingsPage::page_url() .
			'">' . __('Settings') . '</a>';
		return $links;
	}
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'takopic_add_settings_link');
}
