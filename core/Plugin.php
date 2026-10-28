<?php

/**
 * Plugin
 *
 * Main class of Takopic: boots it once.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic;

use Takopic\Admin\JobsAjax;
use Takopic\Admin\MediaColumn;
use Takopic\Admin\Notices;
use Takopic\Admin\PluginsScreen;
use Takopic\Admin\SettingsAjax;
use Takopic\Admin\SettingsPage;
use Takopic\Delivery\Display;
use Takopic\Delivery\Htaccess;
use Takopic\Delivery\Test;
use Takopic\Media\Job;
use Takopic\Media\Lock;
use Takopic\Media\Queue;
use Takopic\Media\UploadHooks;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

final class Plugin
{
	/**
	 * The instance of Takopic.
	 *
	 * @var null
	 */
	private static $instance = NULL;

	/**
	 * Boots Takopic and initialises the
	 * static instance, once.
	 *
	 * @return Takopic|null
	 * @since 1.0.0
	 */
	public static function boot()
	{
		if (self::$instance == null) {
			$plugin = new Plugin();
			$plugin->initialize();
			self::$instance = $plugin;
		}
		return self::$instance;
	}

	/**
	 * Sets up the Takopic plugin.
	 *
	 * @param void
	 * @return void
	 * @since 1.0.0
	 */
	private function initialize()
	{
		$base = dirname(__FILE__);
		define('TAKOPIC_URL', plugin_dir_url($base));
		define('TAKOPIC_PATH', plugin_dir_path($base));
		define('TAKOPIC_TEMPLATE_PATH', dirname($base) . DIRECTORY_SEPARATOR . 'templates');
		add_action('init', [Settings::class, 'register']);
		add_action('takopic_working', [Lock::class, 'beat']);
		new SettingsPage();
		new SettingsAjax();
		new UploadHooks();
		new Notices();
		new MediaColumn();
		new PluginsScreen();
		new Htaccess();
		new Test();
		// The background jobs need the dependencies of composer install.
		if (class_exists('Takopic_Vendor_WP_Background_Process')) {
			Job::instance();
			Queue::instance();
			new JobsAjax();
		}
		new Display();
	}

	/**
	 * Enqueues a stylesheet of assets/css, e.g.
	 * base/tokens as takopic-base-tokens. Its version
	 * is its last change, so that browsers load it again
	 * after an update.
	 *
	 * @param string $path Relative to assets/css, without .css.
	 * @param string[] $deps
	 * @return string The handle.
	 * @since 1.0.0
	 */
	public static function enqueue_style($path, $deps = [])
	{
		$handle = self::handle($path);
		$file = 'assets/css/' . $path . '.css';
		wp_enqueue_style($handle, TAKOPIC_URL . $file, $deps, filemtime(TAKOPIC_PATH . $file));
		return $handle;
	}

	/**
	 * Enqueues a script of assets/js in the footer, with
	 * its translations when it uses wp.i18n.
	 *
	 * @param string $path Relative to assets/js, without .js.
	 * @param string[] $deps
	 * @return string The handle.
	 * @since 1.0.0
	 */
	public static function enqueue_script($path, $deps = [])
	{
		$handle = self::handle($path);
		$file = 'assets/js/' . $path . '.js';
		wp_enqueue_script($handle, TAKOPIC_URL . $file, $deps, filemtime(TAKOPIC_PATH . $file), true);
		if (in_array('wp-i18n', $deps, true)) {
			wp_set_script_translations($handle, 'takopic');
		}
		return $handle;
	}

	/**
	 * Enqueues files in their order, each one after
	 * the previous one: the stylesheets cascade, the
	 * scripts use the classes loaded before them.
	 *
	 * @param string[] $paths
	 * @param string $type style or script.
	 * @param string[] $deps Of the first file.
	 * @return string The handle of the last file.
	 * @since 1.0.0
	 */
	public static function enqueue_chain($paths, $type, $deps = [])
	{
		$handle = '';
		foreach ($paths as $path) {
			$handle = $type === 'style' ? self::enqueue_style($path, $deps) : self::enqueue_script($path, array_merge(['wp-i18n'], $deps));
			$deps = [$handle];
		}
		return $handle;
	}

	/**
	 * Returns the handle of an asset, from its path.
	 *
	 * @param string $path
	 * @return string
	 * @since 1.0.0
	 */
	private static function handle($path)
	{
		return 'takopic-' . strtolower(str_replace('/', '-', $path));
	}
}
