<?php

/**
 * Ajax
 *
 * The AJAX requests of Takopic: the nonce of the options
 * page, and the capability of the user, checked once
 * the nonce is. The nonce is checked in each request,
 * where Plugin Check sees it.
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
