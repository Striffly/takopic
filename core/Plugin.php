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

use Takopic\Admin\SettingsPage;
use Takopic\Admin\JobsAjax;
use Takopic\Admin\MediaColumn;
use Takopic\Admin\Notices;
use Takopic\Admin\PluginsScreen;
use Takopic\Delivery\Display;
use Takopic\Delivery\Htaccess;
use Takopic\Delivery\Test;
use Takopic\Media\Job;
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
		$this->define('TAKOPIC_URL', plugin_dir_url($base));
		$this->define('TAKOPIC_PATH', plugin_dir_path($base));
		$this->define('TAKOPIC_TEMPLATE_PATH', dirname($base) . DIRECTORY_SEPARATOR . 'templates');
		add_action('init', [Settings::class, 'register']);
		new SettingsPage();
		new UploadHooks();
		new Notices();
		new MediaColumn();
		new PluginsScreen();
		new Htaccess();
		new Test();
		// The background jobs need the dependencies of composer install.
		if (class_exists('Takopic_Vendor_WP_Background_Process')) {
			Job::instance();
			new JobsAjax();
		}
		new Display();
	}

	/**
	 * Defines a constant if doesnt already exist.
	 *
	 * @param string $name The constant name.
	 * @param mixed $value The constant value.
	 * @return void
	 * @since 1.0.0
	 *
	 */
	function define($name, $value = true)
	{
		if (!defined($name)) {
			define($name, $value);
		}
	}
}
