<?php

/**
 * Uninstall
 *
 * Deletes the data of Takopic from the database when
 * the plugin is deleted: its settings, the meta of the
 * attachments, its recent errors and background job.
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

global $wpdb;

/*
 * The names of Takopic only: a pattern as wide as takopic_% would also delete the
 * options of another plugin using this prefix.
 */

// Recent errors, progress of the background job, cached theme scan and versions of the
// libraries, locks of the images.
$options = ['takopic_errors', 'takopic_job', 'takopic_theme_calls', 'takopic_versions'];
$option_prefixes = ['takopic_lock_'];
// The settings, by name: _ and the name of each field of Fields::sections(), as
// WordPress does not load Takopic here. A prefix would delete the options of another
// plugin starting with _takopic_.
$settings = [
	'takopic_jpg_enable',
	'takopic_jpg_quality',
	'takopic_png_enable',
	'takopic_png_quality',
	'takopic_webp_enable',
	'takopic_webp_quality',
	'takopic_avif_enable',
	'takopic_avif_quality',
	'takopic_display_formats',
];
foreach ($settings as $setting) {
	$options[] = '_' . $setting;
}
// The background job library keeps its queue, status and lock in the site options,
// under the identifier of the job: takopic_job, or takopic_2_job on the site 2 of a network.
$site_options = [];
$site_option_prefixes = [];
$add_job = function ($identifier) use (&$site_options, &$site_option_prefixes) {
	array_push($site_options, $identifier . '_status', '_site_transient_' . $identifier . '_process_lock', '_site_transient_timeout_' . $identifier . '_process_lock');
	$site_option_prefixes[] = $identifier . '_batch_';
	wp_clear_scheduled_hook($identifier . '_cron');
};

/**
 * Deletes the options of Takopic and the meta of the
 * attachments of the current site.
 */
$clean_site = function () use ($wpdb, $options, $option_prefixes) {
	foreach ($options as $option) {
		delete_option($option);
		delete_transient($option);
	}
	foreach ($option_prefixes as $prefix) {
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
	}
	// Meta of the attachments: settings used, files made, errors, sizes.
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like('_takopic_') . '%'));
};

if (is_multisite()) {
	foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
		switch_to_blog($site_id);
		$clean_site();
		$add_job('takopic_' . $site_id . '_job');
		restore_current_blog();
	}
	$table = $wpdb->sitemeta;
	$column = 'meta_key';
} else {
	$clean_site();
	$add_job('takopic_job');
	$table = $wpdb->options;
	$column = 'option_name';
}

foreach ($site_options as $option) {
	$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE {$column} = %s", $option));
}
foreach ($site_option_prefixes as $prefix) {
	$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE {$column} LIKE %s", $wpdb->esc_like($prefix) . '%'));
}

wp_cache_flush();
