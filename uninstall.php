<?php

/**
 * Uninstall
 *
 * Deletes the data of Takopic when it is deleted, see
 * Lifecycle::uninstall(). WordPress does not load the
 * plugin before: its classes are loaded here.
 *
 * The files are kept: the compressed images, their WebP
 * and AVIF versions, and the originals in
 * uploads/takopic-originals/. A request may time out
 * before thousands of images are restored: use
 * "Restore original images" before deleting Takopic.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @category    Uninstall
 * @repo        https://github.com/Striffly/takopic
 *
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit; // Exit if not uninstalled by WordPress.
}

require_once __DIR__ . '/autoload.php';

Takopic\Lifecycle::uninstall();
