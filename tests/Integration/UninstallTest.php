<?php

/**
 * UninstallTest
 *
 * Deleting Takopic deletes its data, and nothing else,
 * on a single site and on every site of a network.
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Tests\Integration;

use Takopic\Delivery\Htaccess;
use Takopic\Media\Files;
use Takopic\Settings;
use Takopic\Tests\TestCase;

class UninstallTest extends TestCase
{
	/**
	 * The options of other plugins, kept by the
	 * uninstall.
	 */
	const FOREIGN = ['takopic_other_plugin', '_takopic_other_plugin', 'takopic_jobs'];

	/**
	 * The rules of another plugin in uploads/.htaccess.
	 */
	const OTHER_RULES = "# Another plugin\nOptions -Indexes\n";

	public function test_the_data_of_takopic_is_deleted_from_a_site()
	{
		$this->skipWithMultisite();
		$attachment = $this->fill_site(true);
		$this->fill_job('takopic_job');

		self::uninstall();

		$this->assert_site_clean($attachment);
		$this->assert_job_clean('takopic_job');
		$this->assertSame(self::OTHER_RULES, file_get_contents(Htaccess::htaccess()));
	}

	public function test_the_data_of_takopic_is_deleted_from_every_site_of_a_network()
	{
		$this->skipWithoutMultisite();
		$main = get_current_blog_id();
		$sites = [$main => $this->fill_site(true)];
		$this->fill_job('takopic_' . $main . '_job');
		$other = self::factory()->blog->create();
		switch_to_blog($other);
		$sites[$other] = $this->fill_site(false);
		$this->fill_job('takopic_' . $other . '_job');
		restore_current_blog();

		self::uninstall();

		foreach ($sites as $site => $attachment) {
			switch_to_blog($site);
			$this->assert_site_clean($attachment);
			$this->assert_job_clean('takopic_' . $site . '_job');
			restore_current_blog();
		}
		$this->assertSame(self::OTHER_RULES, file_get_contents($this->uploads . '/.htaccess'));
		$this->assertFileDoesNotExist($this->uploads . '/sites/' . $other . '/.htaccess', 'It held the rules of Takopic only.');
	}

	/**
	 * Saves the data of Takopic on the current site, and of
	 * other plugins.
	 *
	 * @param bool $other_rules Whether the .htaccess has the rules of another plugin.
	 * @return int An attachment with metas of Takopic.
	 */
	private function fill_site($other_rules)
	{
		Settings::set(array_map(function ($field) {
			return $field['default'];
		}, Settings::FIELDS));
		foreach (['takopic_errors', 'takopic_job', 'takopic_lock_7'] as $option) {
			update_option($option, ['data']);
		}
		// The key of the folder of the originals.
		Files::backup_dir();
		set_transient('takopic_versions', ['cwebp' => '1.6.0']);
		set_transient('takopic_theme_calls', ['functions.php']);
		foreach (self::FOREIGN as $option) {
			update_option($option, 'kept');
		}
		$id = self::factory()->attachment->create_object(['file' => '2026/10/photo.jpg', 'post_mime_type' => 'image/jpeg']);
		update_post_meta($id, '_takopic_files', ['2026/10/photo.jpg']);
		update_post_meta($id, '_takopic_webp', 'quality:80');
		update_post_meta($id, 'takopic_other_plugin', 'kept');

		wp_mkdir_p(dirname(Htaccess::htaccess()));
		if ($other_rules) {
			file_put_contents(Htaccess::htaccess(), self::OTHER_RULES);
		}
		Settings::set(['serve_rewrite' => true]);
		$this->assertTrue(Htaccess::rules_written());
		return $id;
	}

	/**
	 * Saves the queue, status and lock of a background
	 * job, as the library does: in the options of the
	 * network, its cron event in the current site.
	 *
	 * @param string $identifier
	 */
	private function fill_job($identifier)
	{
		update_site_option($identifier . '_batch_' . md5($identifier), [1, 2, 3]);
		update_site_option($identifier . '_status', 1);
		set_site_transient($identifier . '_process_lock', microtime(), 60);
		wp_schedule_event(time(), 'hourly', $identifier . '_cron');
	}

	/**
	 * Runs uninstall.php, as WordPress does when Takopic is
	 * deleted.
	 */
	private static function uninstall()
	{
		if (!defined('WP_UNINSTALL_PLUGIN')) {
			define('WP_UNINSTALL_PLUGIN', 'takopic/takopic.php');
		}
		include dirname(__DIR__, 2) . '/uninstall.php';
	}

	/**
	 * Checks that the current site has no data of Takopic
	 * left, and that the data of other plugins stays.
	 *
	 * @param int $attachment
	 */
	private function assert_site_clean($attachment)
	{
		global $wpdb;
		$left = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%takopic%' ORDER BY option_name");
		$foreign = self::FOREIGN;
		sort($foreign);
		$this->assertSame($foreign, $left);
		$this->assertSame(['takopic_other_plugin'], array_values(preg_grep('/takopic/', array_keys(get_post_meta($attachment)))));
		$this->assertFalse(Htaccess::rules_written());
	}

	/**
	 * Checks that a background job has nothing left.
	 *
	 * @param string $identifier
	 */
	private function assert_job_clean($identifier)
	{
		global $wpdb;
		list($table, $column) = is_multisite() ? [$wpdb->sitemeta, 'meta_key'] : [$wpdb->options, 'option_name'];
		$left = $wpdb->get_col($wpdb->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE %s", '%' . $wpdb->esc_like($identifier . '_') . '%'));
		$this->assertSame([], $left);
		$this->assertFalse(wp_next_scheduled($identifier . '_cron'));
	}
}
