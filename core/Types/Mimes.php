<?php

/**
 * Mimes
 *
 * Mime times for files stored here.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
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
