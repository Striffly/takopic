<?php

/**
 * Mimes
 *
 * Mime times for files stored here.
 *
 * @package     Takopic
 * @version     0.1.4
 * @author      Ainsley Clark
 * @category    Class
 * @repo        https://github.com/ainsleyclark/squidge
 *
 */

namespace Takopic\Types;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

abstract class Mimes
{
	const PNG = "image/png";
	const JPG = 'image/jpeg';
}
