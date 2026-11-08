<?php

/**
 * Commands
 *
 * The wp takopic commands of WP-CLI.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    CLI
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Cli;

use Takopic\Encoders\Formats;
use Takopic\Engines\Libraries;
use Takopic\Media\Cleaner;
use Takopic\Media\Files;
use Takopic\Media\Lock;
use Takopic\Media\Optimiser;
use Takopic\Media\Stats;
use Takopic\Media\Status;
use Takopic\Settings;
use WP_CLI;
use WP_CLI_Command;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Optimises the images of the media library, or undoes it.
 *
 * Compresses the JPG and PNG images and makes their
 * WebP and AVIF versions, removes these versions or
 * restores the originals.
 */
class Commands extends WP_CLI_Command
{

	/**
	 * Prints the version of Takopic.
	 *
	 * @since 1.0.0
	 */
	public function version()
	{
		$this->print_welcome();
		WP_CLI::log(TAKOPIC_VERSION);
	}

	/**
	 * Checks that the libraries are installed.
	 *
	 * Without cwebp or avifenc, tells whether GD or
	 * Imagick makes the format instead.
	 *
	 * @since 1.0.0
	 */
	public function health()
	{
		$this->print_welcome();
		if (!Libraries::can_execute()) {
			WP_CLI::warning(__('PHP cannot run programs: proc_open() and exec() are disabled, the libraries cannot run.', 'takopic'));
		}
		foreach (Formats::compressions() as $service) {
			$this->print_health($service::library(), $service::installed());
			foreach ($service::OPTIONAL as $name) {
				/* translators: %s: an optional library, e.g. pngquant. */
				$this->print_health(sprintf(__('%s (optional)', 'takopic'), $name), $service::installed($name));
			}
		}
		foreach (Formats::variants() as $service) {
			$this->print_converter($service);
		}
	}

	/**
	 * Optimises the JPG and PNG images of the media library.
	 *
	 * Compresses them and makes their WebP and AVIF
	 * versions, with the settings of the settings page,
	 * unless the options below override them: each
	 * setting of a format is an option of the same
	 * name. The images already optimised are only
	 * completed (e.g. a new thumbnail size), unless
	 * --force. Exits with an error if images fail.
	 *
	 * ## OPTIONS
	 *
	 * [--[no-]jpg]
	 * : Compress the JPG images (--jpg) or not (--no-jpg). As on the settings page by default.
	 *
	 * [--[no-]png]
	 * : Compress the PNG images (--png) or not (--no-png). As on the settings page by default.
	 *
	 * [--[no-]webp]
	 * : Make the WebP versions (--webp) or not (--no-webp). As on the settings page by default.
	 *
	 * [--[no-]avif]
	 * : Make the AVIF versions (--avif) or not (--no-avif). As on the settings page by default.
	 *
	 * [--jpg-quality=<quality>]
	 * : The quality of the JPG files, from 0 to 100.
	 *
	 * [--png-compression=<compression>]
	 * : The compression of the PNG files: lossless, or lossy (with pngquant).
	 *
	 * [--png-level=<level>]
	 * : The optimisation level of the PNG files, from 0 (fastest) to 7 (smallest).
	 *
	 * [--png-lossy-quality=<quality>]
	 * : The quality pngquant aims at, from 0 to 100.
	 *
	 * [--webp-quality=<quality>]
	 * : The quality of the WebP files, from 0 to 100.
	 *
	 * [--avif-quality=<quality>]
	 * : The quality of the AVIF files, from 0 to 100.
	 *
	 * [--avif-speed=<speed>]
	 * : The speed of avifenc, from 0 (slowest) to 10. Faster, the files are often heavier.
	 *
	 * [--force]
	 * : Optimise again the images already optimised.
	 *
	 * [--regenerate]
	 * : Make the thumbnails again from the originals first. Implies --force.
	 *
	 * [--thumbnails-only]
	 * : Leave the full size images out.
	 *
	 * ## EXAMPLES
	 *
	 *     # Optimise the new images.
	 *     $ wp takopic run
	 *
	 *     # Make the WebP files again at quality 75, and nothing else.
	 *     $ wp takopic run --webp --no-avif --no-jpg --no-png --webp-quality=75 --force
	 *
	 * @param $args
	 * @param $assoc_args
	 * @since 1.0.0
	 */
	public function run($args, $assoc_args)
	{
		$this->print_welcome();

		$settings = Formats::settings(true, self::settings($assoc_args));
		$force = self::flag($assoc_args, 'force');
		$thumbnails_only = self::flag($assoc_args, 'thumbnails-only');
		$regenerate = self::flag($assoc_args, 'regenerate');

		/* translators: %s: the formats, e.g. jpg, webp. */
		WP_CLI::log(sprintf(__('Formats: %s', 'takopic'), empty($settings) ? __('none', 'takopic') : implode(', ', array_keys($settings))));

		$counter = 0;
		$failed = 0;
		$ids = Status::ids(null);
		$total = count($ids);
		foreach ($ids as $index => $id) {
			/* translators: 1: the number of the image, 2: the number of images. */
			$progress = sprintf(__('Processing image %1$d/%2$d:', 'takopic'), $index + 1, $total);
			/* translators: 1: the title of an image, 2: its ID. */
			WP_CLI::log(WP_CLI::colorize('%B' . $progress . ' %n') . sprintf(__('%1$s (ID %2$d)', 'takopic'), get_the_title($id), $id));
			$done = Lock::run($id, function () use ($id, $regenerate, $settings, $force, $thumbnails_only) {
				$metadata = $regenerate ? Optimiser::regenerate($id) : null;
				return Optimiser::attachment($id, $metadata, $settings, ['force' => $force || $regenerate, 'thumbnails_only' => $thumbnails_only, 'context' => Optimiser::CLI]);
			});
			if ($done === null) {
				self::skipped($id);
				continue;
			}
			$counter++;
			if (Status::of($id) === Status::ERROR) {
				$failed++;
			}
		}

		if ($failed) {
			/* translators: 1: the number of images, 2: the number of images with errors. */
			WP_CLI::error(sprintf(_n('Processed %1$d image, %2$d with errors: see them in the Media library.', 'Processed %1$d images, %2$d with errors: see them in the Media library.', $counter, 'takopic'), $counter, $failed));
		}
		/* translators: %d: the number of images. */
		WP_CLI::success(sprintf(_n('Successfully processed %d image.', 'Successfully processed %d images.', $counter, 'takopic'), $counter));
	}

	/**
	 * Removes the WebP and AVIF files of all images.
	 *
	 * The JPG and PNG files stay compressed.
	 *
	 * @since 1.0.0
	 */
	public function clean()
	{
		$this->print_welcome();
		$ids = Cleaner::ids();
		foreach ($ids as $id) {
			Cleaner::remove_formats($id);
		}
		/* translators: %d: the number of images. */
		WP_CLI::success(sprintf(_n('Removed the WebP and AVIF files of %d image.', 'Removed the WebP and AVIF files of %d images.', count($ids), 'takopic'), count($ids)));
	}

	/**
	 * Restores the original images.
	 *
	 * Removes every file made by Takopic (WebP, AVIF,
	 * backups) and its data on the images.
	 *
	 * @since 1.0.0
	 */
	public function restore()
	{
		$this->print_welcome();
		$ids = Cleaner::ids();
		$missing = 0;
		$skipped = 0;
		foreach ($ids as $id) {
			$restored = Cleaner::restore($id);
			if ($restored === null) {
				$skipped++;
				self::skipped($id);
			} elseif (!$restored) {
				$missing++;
				/* translators: %s: the title of an image. */
				WP_CLI::warning(sprintf(__('%s: no original to restore, it stays compressed.', 'takopic'), get_the_title($id)));
			}
		}
		$done = count($ids) - $missing - $skipped;
		/* translators: %d: the number of images. */
		$message = sprintf(_n('Restored %d image.', 'Restored %d images.', $done, 'takopic'), $done);
		if ($missing) {
			/* translators: %d: the number of images. */
			$message .= ' ' . sprintf(_n('%d had no original.', '%d had no original.', $missing, 'takopic'), $missing);
		}
		if ($skipped) {
			/* translators: %d: the number of images. */
			$message .= ' ' . sprintf(_n('%d skipped.', '%d skipped.', $skipped, 'takopic'), $skipped);
		}
		WP_CLI::success($message);
	}

	/**
	 * Prints the bytes saved on the media library.
	 *
	 * First measures the images optimised before Takopic
	 * measured them, from the sizes of their files:
	 * nothing is encoded.
	 *
	 * ## OPTIONS
	 *
	 * [--rebuild]
	 * : Measure every image again.
	 *
	 * @param $args
	 * @param $assoc_args
	 * @since 1.0.0
	 */
	public function stats($args, $assoc_args)
	{
		$this->print_welcome();
		$rebuild = self::flag($assoc_args, 'rebuild');
		$measured = 0;
		foreach (array_chunk(Cleaner::ids(), 500) as $ids) {
			_prime_post_caches($ids, false, true);
			foreach ($ids as $id) {
				if (!in_array(get_post_mime_type($id), Files::MIMES, true) || (!$rebuild && Stats::of($id) !== null)) {
					continue;
				}
				$done = Lock::run($id, function () use ($id) {
					Stats::save($id);
					return true;
				});
				if ($done === null) {
					self::skipped($id);
					continue;
				}
				$measured++;
			}
		}
		/* translators: %d: the number of images. */
		WP_CLI::log(sprintf(_n('Measured %d image.', 'Measured %d images.', $measured, 'takopic'), $measured));

		$totals = Stats::totals(true);
		$labels = [
			'original' => __('Originals', 'takopic'),
			'compressed' => __('Compressed JPG and PNG', 'takopic'),
			'modern' => __('With WebP and AVIF served', 'takopic'),
			'extra' => __('WebP and AVIF files', 'takopic'),
		];
		$items = [];
		foreach ($labels as $name => $label) {
			$items[] = [
				'Files' => $label,
				'Bytes' => $totals[$name],
				'Size' => Stats::size($totals[$name]),
				'Reduction' => in_array($name, ['compressed', 'modern'], true) ? (string)Stats::reduction($totals, $name) : '',
			];
		}
		\WP_CLI\Utils\format_items('table', $items, ['Files', 'Bytes', 'Size', 'Reduction']);

		$savings = Stats::savings();
		$saved = $savings['saved'];
		$reduction = $savings['reduction'];
		WP_CLI::success(Stats::served() === 'modern'
			/* translators: 1: a size, e.g. 12.5 MB, 2: the number of images, 3: a reduction, e.g. −48 %. */
			? sprintf(_n('%1$s saved on %2$d image, %3$s: the site serves the WebP and AVIF.', '%1$s saved on %2$d images, %3$s: the site serves the WebP and AVIF.', $totals['images'], 'takopic'), $saved, $totals['images'], $reduction)
			/* translators: 1: a size, e.g. 12.5 MB, 2: the number of images, 3: a reduction, e.g. −48 %. */
			: sprintf(_n('%1$s saved on %2$d image, %3$s: the site serves the JPG and PNG, not the WebP and AVIF.', '%1$s saved on %2$d images, %3$s: the site serves the JPG and PNG, not the WebP and AVIF.', $totals['images'], 'takopic'), $saved, $totals['images'], $reduction));
	}

	/**
	 * Returns the settings, with the ones of the formats
	 * the options override: --jpg-quality sets
	 * jpg_quality, --jpg or --no-jpg sets jpg_enable.
	 * Ends with an error on a value a setting cannot
	 * take; a number out of its bounds is brought within
	 * them, as on the settings page.
	 *
	 * @param array $assoc_args
	 * @return array By name, see Settings::all().
	 * @since 1.0.0
	 */
	private static function settings($assoc_args)
	{
		$values = Settings::all();
		foreach (array_keys(Settings::FIELDS) as $name) {
			$format = (string)strstr($name, '_', true);
			if (!isset(Formats::ENCODERS[$format])) {
				continue;
			}
			$option = $name === $format . '_enable' ? $format : str_replace('_', '-', $name);
			if (!isset($assoc_args[$option])) {
				continue;
			}
			if (!Settings::valid($name, $assoc_args[$option])) {
				WP_CLI::error($option === $format
					/* translators: %s: a format, e.g. webp. */
					? sprintf(__('--%1$s is on or off: use --%1$s or --no-%1$s.', 'takopic'), $format)
					/* translators: 1: an option, e.g. jpg-quality, 2: its value. */
					: sprintf(__('--%1$s=%2$s: not a value of this option, see wp help takopic run.', 'takopic'), $option, $assoc_args[$option]));
			}
			$values[$name] = Settings::clean($name, $assoc_args[$option]);
		}
		return $values;
	}

	/**
	 * Determines if a flag is on: --force, or
	 * --force=true.
	 *
	 * @param array $assoc_args
	 * @param string $name
	 * @return bool
	 * @since 1.0.0
	 */
	private static function flag($assoc_args, $name)
	{
		return isset($assoc_args[$name]) && filter_var($assoc_args[$name], FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Warns that an image was skipped, another request
	 * processing it.
	 *
	 * @param int $id
	 * @since 1.0.0
	 */
	private static function skipped($id)
	{
		/* translators: %s: the title of an image. */
		WP_CLI::warning(sprintf(__('%s: skipped, another request is processing it.', 'takopic'), get_the_title($id)));
	}

	/**
	 * Prints CLI welcome message.
	 *
	 * @since 1.0.0
	 */
	private function print_welcome()
	{
		// Not WP_CLI::line(): it echoes, held back until the end by an output buffer a theme or plugin may open.
		WP_CLI::log(WP_CLI::colorize('%G' . __('Welcome to the Takopic CLI...', 'takopic') . '%n' . PHP_EOL));
	}

	/**
	 * Prints the health information for a service.
	 *
	 * @param $type
	 * @param $active
	 * @since 1.0.0
	 */
	private function print_health($type, $active)
	{
		if ($active) {
			/* translators: %s: a library, e.g. cwebp. */
			WP_CLI::success(sprintf(__('%s active.', 'takopic'), $type));
			return;
		}
		/* translators: %s: a library, e.g. cwebp. */
		WP_CLI::log(WP_CLI::colorize('%R' . __('Error:', 'takopic') . ' %n') . sprintf(__('%s not installed, see the settings page of Takopic.', 'takopic'), $type));
	}

	/**
	 * Prints the health information of a format made by
	 * its library, or by the image editor (GD or
	 * Imagick) where the library is missing.
	 *
	 * @param string $service
	 * @since 1.0.0
	 */
	private function print_converter($service)
	{
		if ($service::installed() || $service::editor() === null) {
			$this->print_health($service::cmd_name(), $service::installed());
			return;
		}
		/* translators: 1: GD or Imagick, 2: a format, e.g. WebP, 3: a library, e.g. cwebp. */
		WP_CLI::success(sprintf(__('%1$s active for %2$s: %3$s not installed, recommended.', 'takopic'), $service::editor(), $service::TITLE, $service::cmd_name()));
	}
}
