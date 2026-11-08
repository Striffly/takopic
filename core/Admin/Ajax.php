<?php

/**
 * Ajax
 *
 * The AJAX requests of Takopic: what the scripts send
 * them, the nonce of the options page, and the
 * capability of the user, checked once the nonce is.
 * The nonce is checked in each request, where Plugin
 * Check sees it.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Admin;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Ajax
{
	/**
	 * Nonce action of the requests of the options page:
	 * the jobs, the settings and the delivery test. Not
	 * takopic_job, the action of the background library
	 * (Job): its nonce, made for the same user, goes in
	 * the URL of its requests, and so in the logs of the
	 * server.
	 *
	 * @var string
	 */
	const NONCE = 'takopic_admin';

	/**
	 * Gives a script of a screen what its requests need
	 * (see utils/Api.js): the URL of admin-ajax.php and
	 * the nonce of the screen, with other data, and the
	 * thousands separator of WordPress.
	 *
	 * @param string $handle
	 * @param string $name The object of the script, e.g. takopicSettings.
	 * @param string $nonce The nonce action.
	 * @param array $data
	 * @since 1.0.0
	 */
	public static function localize($handle, $name, $nonce, $data = [])
	{
		global $wp_locale;
		wp_localize_script($handle, $name, array_merge([
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce($nonce),
			// The numbers the scripts write look like those of number_format_i18n() (see views/Screen.js).
			'thousands' => html_entity_decode($wp_locale->number_format['thousands_sep'], ENT_QUOTES, 'UTF-8'),
		], $data));
	}

	/**
	 * Ends a request, its nonce checked, if the user
	 * does not have the capability.
	 *
	 * @param string $capability
	 * @since 1.0.0
	 */
	public static function authorize($capability = 'manage_options')
	{
		if (!current_user_can($capability)) {
			wp_send_json_error(null, 403);
		}
	}
}
