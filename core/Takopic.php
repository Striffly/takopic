<?php

/**
 * Takopic
 *
 * Main Takopic class.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic;

use Takopic\Admin;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

final class Takopic
{
	/**
	 * The plugin version number.
	 *
	 * @var string
	 */
	var $version = '0.1.4';

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
	 * @date 24/11/2021
	 * @since 0.1.0
	 */
	public static function boot()
	{
		if (self::$instance == null) {
			$takopic = new Takopic();
			$takopic->initialize();
			self::$instance = $takopic;
		}
		return self::$instance;
	}

	/**
	 * Sets up the Takopic plugin.
	 *
	 * @param void
	 * @return void
	 * @since 0.1.2
	 * @date 05/12/2021
	 */
	private function initialize()
	{
		$base = dirname(__FILE__);
		$this->define('TAKOPIC_URL', plugin_dir_url($base));
		$this->define('TAKOPIC_PATH', plugin_dir_path($base));
		$this->define('TAKOPIC_BASENAME', plugin_basename(dirname($base)));
		$this->define('TAKOPIC_TEMPLATE_PATH', dirname($base) . DIRECTORY_SEPARATOR . 'templates');
		$this->define('TAKOPIC_VERSION', $this->version);
		$uploadData = wp_upload_dir();
		$this->define('TAKOPIC_UPLOAD_DIR', $uploadData['basedir']);
		$this->define('TAKOPIC_UPLOAD_URL', $uploadData['baseurl']);
		new Admin\Fields();
		new Admin\Upload();
		new Admin\Notices();
		new Admin\Media();
		new Admin\Plugins();
		// The background jobs need the dependencies of composer install.
		if (class_exists('Takopic_Vendor_WP_Background_Process')) {
			Package\Job::instance();
			new Admin\Tools();
		}
		new Front\Display();
	}

	/**
	 * Defines a constant if doesnt already exist.
	 *
	 * @param string $name The constant name.
	 * @param mixed $value The constant value.
	 * @return void
	 * @since 0.1.0
	 * @date    24/11/2021
	 *
	 */
	function define($name, $value = true)
	{
		if (!defined($name)) {
			define($name, $value);
		}
	}
}
