<?php

/**
 * Media
 *
 * Media shows the Takopic status of each image in
 * the media library (list mode), with an action to
 * optimise it.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @category    Admin
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Admin;

use Takopic\Media\Cleaner;
use Takopic\Media\Files;
use Takopic\Media\Optimiser;
use Takopic\Media\Stats;
use Takopic\Media\Status;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class MediaColumn
{
	/**
	 * Action optimising one image.
	 *
	 * @var string
	 */
	const ACTION = 'takopic_optimise';

	/**
	 * Action restoring the original of one image.
	 *
	 * @var string
	 */
	const RESTORE = 'takopic_restore';

	/**
	 * Query args of the notices after an action.
	 *
	 * @var string[]
	 */
	const NOTICES = ['takopic_optimised', 'takopic_restored', 'takopic_busy'];

	/**
	 * Adds the column, the action and its notice.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_filter('manage_media_columns', [$this, 'columns']);
		add_action('manage_media_custom_column', [$this, 'column'], 10, 2);
		add_filter('media_row_actions', [$this, 'row_actions'], 10, 2);
		add_action('admin_post_' . self::ACTION, [$this, 'optimise']);
		add_action('admin_post_' . self::RESTORE, [$this, 'restore']);
		add_action('admin_notices', [$this, 'notice']);
		add_action('admin_enqueue_scripts', [$this, 'styles']);
	}

	/**
	 * Returns the labels of the statuses, as shown to
	 * the user.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function labels()
	{
		return [
			Status::OPTIMISED => __('Optimised', 'takopic'),
			Status::TO_OPTIMISE => __('To optimise', 'takopic'),
			Status::OUTDATED => __('Older settings', 'takopic'),
			Status::ERROR => __('Error', 'takopic'),
		];
	}

	/**
	 * Determines if Takopic optimises an attachment.
	 *
	 * @param int $id
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_image($id)
	{
		return in_array(get_post_mime_type($id), Files::MIMES, true);
	}

	/**
	 * Adds the Optimisation column.
	 *
	 * @param array $columns
	 * @return array
	 * @since 1.0.0
	 */
	public function columns($columns)
	{
		$columns['takopic'] = __('Optimisation', 'takopic');
		return $columns;
	}

	/**
	 * Renders the status of an image, and how much
	 * lighter the files the site serves are than its
	 * originals.
	 *
	 * @param string $name
	 * @param int $id
	 * @since 1.0.0
	 */
	public function column($name, $id)
	{
		if ($name !== 'takopic') {
			return;
		}
		if (!$this->is_image($id)) {
			echo '&mdash;';
			return;
		}
		$status = Status::of($id);
		printf('<span class="takopic-status-label takopic-status-label--%s">%s</span>', esc_attr($status), esc_html(self::labels()[$status]));
		$bytes = Stats::of($id);
		$reduction = $bytes ? Stats::reduction($bytes, Stats::served()) : null;
		if ($reduction !== null) {
			printf(' <span class="takopic-saved" title="%s">&middot; %s</span>', esc_attr($this->details($bytes)), esc_html($reduction));
		}
	}

	/**
	 * Returns the sizes of the files of an image, one
	 * per line, for the title of its reduction.
	 *
	 * @param array $bytes See Stats::KEYS.
	 * @return string
	 * @since 1.0.0
	 */
	private function details($bytes)
	{
		$lines = [
			/* translators: %s: a size, e.g. 1.2 MB. */
			sprintf(__('Original: %s', 'takopic'), Stats::size($bytes['original'])),
			/* translators: 1: a size, e.g. 640 KB, 2: its reduction, e.g. −48 %. */
			sprintf(__('Compressed: %1$s (%2$s)', 'takopic'), Stats::size($bytes['compressed']), Stats::reduction($bytes, 'compressed')),
		];
		if ($bytes['extra']) {
			/* translators: 1: a size, e.g. 420 KB, 2: its reduction, e.g. −65 %. */
			$lines[] = sprintf(__('With WebP and AVIF: %1$s (%2$s)', 'takopic'), Stats::size($bytes['modern']), Stats::reduction($bytes, 'modern'));
			/* translators: %s: a size, e.g. 900 KB. */
			$lines[] = sprintf(__('WebP and AVIF files: %s', 'takopic'), Stats::size($bytes['extra']));
		}
		$lines[] = Stats::served() === 'modern' ? __('The site serves the WebP and AVIF.', 'takopic') : __('The site serves the JPG or PNG.', 'takopic');
		return implode("\n", $lines);
	}

	/**
	 * Adds the action optimising an image.
	 *
	 * @param array $actions
	 * @param \WP_Post $post
	 * @return array
	 * @since 1.0.0
	 */
	public function row_actions($actions, $post)
	{
		if (!$this->is_image($post->ID) || !current_user_can('edit_post', $post->ID)) {
			return $actions;
		}
		$url = wp_nonce_url(admin_url('admin-post.php?action=' . self::ACTION . '&id=' . $post->ID), self::ACTION . '_' . $post->ID);
		$label = Status::of($post->ID) === Status::OPTIMISED ? __('Optimise again', 'takopic') : __('Optimise', 'takopic');
		$actions[self::ACTION] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
		if (Cleaner::has_data($post->ID)) {
			$url = wp_nonce_url(admin_url('admin-post.php?action=' . self::RESTORE . '&id=' . $post->ID), self::RESTORE . '_' . $post->ID);
			$actions[self::RESTORE] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html__('Restore original', 'takopic'));
		}
		return $actions;
	}

	/**
	 * Optimises an image with the current settings: the
	 * missing formats, the formats or thumbnails with
	 * older settings, or everything again from the
	 * original if it is already optimised.
	 *
	 * @since 1.0.0
	 */
	public function optimise()
	{
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		check_admin_referer(self::ACTION . '_' . $id);
		if (!$id || !$this->is_image($id) || !current_user_can('edit_post', $id)) {
			wp_die(esc_html__('You cannot optimise this image.', 'takopic'), 403);
		}
		$done = Optimiser::optimise($id);

		$redirect = remove_query_arg(self::NOTICES, wp_get_referer() ?: admin_url('upload.php?mode=list'));
		wp_safe_redirect(add_query_arg($done === null ? 'takopic_busy' : 'takopic_optimised', $id, $redirect));
		exit;
	}

	/**
	 * Restores the original of an image and removes its
	 * Takopic files.
	 *
	 * @since 1.0.0
	 */
	public function restore()
	{
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		check_admin_referer(self::RESTORE . '_' . $id);
		if (!$id || !$this->is_image($id) || !current_user_can('edit_post', $id)) {
			wp_die(esc_html__('You cannot restore this image.', 'takopic'), 403);
		}
		$restored = Cleaner::restore($id);

		$redirect = remove_query_arg(self::NOTICES, wp_get_referer() ?: admin_url('upload.php?mode=list'));
		if ($restored === null) {
			$redirect = add_query_arg('takopic_busy', $id, $redirect);
		} else {
			$redirect = add_query_arg('takopic_restored', $restored ? $id : -$id, $redirect);
		}
		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * Confirms the optimisation of an image. Its errors,
	 * if any, are shown by the errors notice.
	 *
	 * @since 1.0.0
	 */
	public function notice()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Query args of the redirect after an action checked by its nonce: they only pick a notice.
		if (!current_user_can('upload_files')) {
			return;
		}
		if (!empty($_GET['takopic_busy'])) {
			$id = (int)$_GET['takopic_busy'];
			if (current_user_can('edit_post', $id)) {
				/* translators: %s: the title of an image. */
				$this->notice_html('warning', __('Takopic is already processing %s, try again in a moment.', 'takopic'), get_the_title($id));
			}
			return;
		}
		if (!empty($_GET['takopic_restored'])) {
			$id = (int)$_GET['takopic_restored'];
			if (!current_user_can('edit_post', abs($id))) {
				return;
			}
			if ($id > 0) {
				/* translators: %s: the title of an image. */
				$this->notice_html('success', __('Takopic restored the original of %s.', 'takopic'), get_the_title($id));
			} else {
				/* translators: %s: the title of an image. */
				$this->notice_html('warning', __('Takopic removed its files of %s, but its original is missing: it stays compressed.', 'takopic'), get_the_title(-$id));
			}
			return;
		}
		if (empty($_GET['takopic_optimised'])) {
			return;
		}
		$id = (int)$_GET['takopic_optimised'];
		if (!current_user_can('edit_post', $id) || Status::of($id) === Status::ERROR) {
			return;
		}
		/* translators: %s: the title of an image. */
		$this->notice_html('success', __('Takopic optimised %s.', 'takopic'), get_the_title($id));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Renders a notice about an image, its title in
	 * place of %s. The name of Takopic is in bold.
	 *
	 * @param string $type success or warning.
	 * @param string $text
	 * @param string $title
	 * @since 1.0.0
	 */
	private function notice_html($type, $text, $title)
	{
		$text = str_replace('Takopic', '<strong>Takopic</strong>', esc_html($text));
		printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), sprintf($text, esc_html($title))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}

	/**
	 * Adds the colours of the statuses to the media
	 * library.
	 *
	 * @param string $hook
	 * @since 1.0.0
	 */
	public function styles($hook)
	{
		if ($hook !== 'upload.php') {
			return;
		}
		wp_enqueue_style('takopic-media', TAKOPIC_URL . 'assets/css/media.css', [], filemtime(TAKOPIC_PATH . 'assets/css/media.css'));
	}
}
