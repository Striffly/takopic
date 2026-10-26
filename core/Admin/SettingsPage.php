<?php

/**
 * SettingsPage
 *
 * The page of Takopic, in the Settings menu: its tabs,
 * and the fields of the settings it saves (see
 * Takopic\Settings).
 *
 * @package     Takopic
 * @author      Clément Gayot
 * @author      Ainsley Clark (Squidge, original author)
 * @category    Class
 * @repo        https://github.com/Striffly/takopic
 *
 */

namespace Takopic\Admin;

use Takopic\Delivery\Htaccess;
use Takopic\Encoders\AVIF;
use Takopic\Encoders\JPG;
use Takopic\Encoders\PNG;
use Takopic\Encoders\WebP;
use Takopic\Engines\Libraries;
use Takopic\Media\Cleaner;
use Takopic\Media\Files;
use Takopic\Media\Stats;
use Takopic\Media\Status;
use Takopic\Settings;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class SettingsPage
{
	/**
	 * The options page slug.
	 *
	 * @var string
	 */
	const PAGE = 'takopic';

	/**
	 * Where to buy a coffee to the author: PayPal (5 €
	 * suggested) or Bitcoin.
	 *
	 * @var string
	 */
	const PAYPAL = 'https://paypal.me/striffly/5EUR';
	const BITCOIN = 'bc1q3j7gxxykpu9zp3sgxyallw0hq0sr659zl35wre';

	/**
	 * Registers the options page.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'styles_and_scripts']);
	}

	/**
	 * Returns the URL of the options page.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function page_url()
	{
		return admin_url('options-general.php?page=' . self::PAGE);
	}

	/**
	 * Registers custom admin style and script.
	 *
	 * @param string $hook
	 * @since 1.0.0
	 */
	public function styles_and_scripts($hook)
	{
		if ($hook !== 'settings_page_' . self::PAGE) {
			return;
		}
		wp_enqueue_style('takopic-settings', TAKOPIC_URL . 'assets/settings.css', [], filemtime(TAKOPIC_PATH . 'assets/settings.css'));
		wp_enqueue_script('takopic-settings', TAKOPIC_URL . 'assets/settings.js', ['wp-i18n'], filemtime(TAKOPIC_PATH . 'assets/settings.js'), true);
		wp_set_script_translations('takopic-settings', 'takopic');
		wp_localize_script('takopic-settings', 'takopicJob', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce(JobsAjax::NONCE),
		]);
	}

	/**
	 * Adds the options page under Settings.
	 *
	 * @since 1.0.0
	 */
	public function add_page()
	{
		add_options_page('Takopic', __('Image Optimizer', 'takopic'), 'manage_options', self::PAGE, [$this, 'render_page']);
	}

	/**
	 * Renders the options page, in tabs by use: the
	 * overview (bytes saved, statuses, libraries), the
	 * optimisation of the media library, the delivery
	 * on the site, the settings and the maintenance.
	 * All tabs share one form, so saving never resets
	 * the hidden tabs; without JS they are shown one
	 * after another.
	 *
	 * @since 1.0.0
	 */
	public function render_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		// Saving the settings reads the versions of the libraries again, e.g. after an upgrade.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Set by WordPress after the settings are saved, with their nonce.
		if (isset($_GET['settings-updated'])) {
			Libraries::forget_versions();
		}
		$sections = self::sections();
		$formats = self::formats($sections);
		$counts = Status::counts();
		$jobs = class_exists('Takopic_Vendor_WP_Background_Process');
		$tabs = [
			'overview' => __('Overview', 'takopic'),
			'optimise' => __('Optimise', 'takopic'),
			'delivery' => __('Delivery', 'takopic'),
			'settings' => __('Settings', 'takopic'),
			'maintenance' => __('Maintenance', 'takopic'),
		];
		// The settings tab warns when a format has nothing to make it.
		$missing = in_array('off', array_map([self::class, 'state'], $formats), true);
		?>
		<div class="wrap takopic-page">
			<header class="takopic-header">
				<img class="takopic-header__logo" src="<?php echo esc_url(TAKOPIC_URL . 'assets/icon-128x128.png'); ?>" alt="" width="64" height="64">
				<div class="takopic-header__text">
					<h1 class="takopic-header__title">Takopic</h1>
					<?php // The tagline stays in English, as the name of Takopic. ?>
					<p class="takopic-header__tagline">Squeezes images. Spills no ink, sends no bill.</p>
				</div>
				<span class="takopic-header__version">v<?php echo esc_html(TAKOPIC_VERSION); ?></span>
			</header>
			<?php // WordPress puts its notices after the first heading of the page, or this marker. ?>
			<hr class="wp-header-end">

			<nav class="takopic-tabs" role="tablist" aria-label="Takopic">
				<?php foreach ($tabs as $id => $title) : ?>
					<a href="#<?php echo esc_attr($id); ?>" class="takopic-tabs__tab" role="tab" data-tab="<?php echo esc_attr($id); ?>">
						<?php echo esc_html($title); ?>
						<?php if ($id === 'settings' && $missing) : ?>
							<span class="takopic-dot takopic-dot--off" title="<?php esc_attr_e('A format has no library', 'takopic'); ?>"></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ($jobs) : ?>
				<?php $this->render_job(); ?>
			<?php endif; ?>

			<form class="takopic-page__form" action="options.php" method="post">
				<?php settings_fields(Settings::GROUP); ?>

				<section class="takopic-panel" id="takopic-panel-overview" role="tabpanel" data-panel="overview">
					<?php $this->render_overview($formats, $counts); ?>
				</section>

				<section class="takopic-panel" id="takopic-panel-optimise" role="tabpanel" data-panel="optimise">
					<?php $this->render_optimise($counts, $jobs); ?>
				</section>

				<section class="takopic-panel" id="takopic-panel-delivery" role="tabpanel" data-panel="delivery">
					<?php $this->render_delivery($sections['display'], $formats, $counts); ?>
				</section>

				<section class="takopic-panel" id="takopic-panel-settings" role="tabpanel" data-panel="settings">
					<?php $this->render_settings($sections['general'], $formats); ?>
				</section>

				<section class="takopic-panel" id="takopic-panel-maintenance" role="tabpanel" data-panel="maintenance">
					<?php $this->render_maintenance($jobs); ?>
				</section>
			</form>

			<?php $this->render_support(); ?>
		</div>
		<?php
	}

	/**
	 * Renders how to buy a coffee to the author, at the
	 * bottom of every tab: PayPal, or Bitcoin with its
	 * address and QR code, behind its button (settings.js
	 * hides them, shown without JS). No notice, nothing
	 * elsewhere in the admin but a link on the plugins
	 * page.
	 *
	 * @since 1.0.0
	 */
	private function render_support()
	{
		?>
		<footer class="takopic-support" id="takopic-support">
			<p class="takopic-support__links">
				<span aria-hidden="true">☕</span>
				<?php
				/* translators: Followed by the links to PayPal and Bitcoin. */
				esc_html_e('Buy me a coffee:', 'takopic');
				?>
				<a href="<?php echo esc_url(self::PAYPAL); ?>" target="_blank" rel="noopener">PayPal</a>
				<span aria-hidden="true">&middot;</span>
				<button type="button" class="button-link takopic-support__toggle" aria-expanded="true" aria-controls="takopic-support-wallet">Bitcoin</button>
			</p>
			<div class="takopic-support__wallet" id="takopic-support-wallet">
				<a class="takopic-support__qr" href="<?php echo esc_url('bitcoin:' . self::BITCOIN, ['bitcoin']); ?>">
					<img src="<?php echo esc_url(TAKOPIC_URL . 'assets/bitcoin.svg'); ?>" width="104" height="104" alt="<?php esc_attr_e('QR code of the Bitcoin address', 'takopic'); ?>">
				</a>
				<code class="takopic-support__address"><?php echo esc_html(self::BITCOIN); ?></code>
			</div>
		</footer>
		<?php
	}

	/**
	 * Returns the format sections (JPG, PNG, WebP,
	 * AVIF) with the state of their library: installed,
	 * version, the engine making the format (the
	 * library, or GD or Imagick for WebP and AVIF
	 * without it, null if none) and the optional
	 * libraries (e.g. pngquant).
	 *
	 * @param array $sections
	 * @return array
	 * @since 1.0.0
	 */
	private static function formats($sections)
	{
		$formats = [];
		foreach ($sections as $id => $section) {
			if (empty($section['service'])) {
				continue;
			}
			$service = $section['service'];
			$section['installed'] = $service::installed();
			$section['version'] = $service::version_number();
			$section['enabled'] = Settings::get($id . '_enable');
			$section['library'] = $service::library();
			$section['engine'] = $service::engine();
			$section['optional'] = isset($section['optional']) ? $section['optional'] : [];
			$section['extras'] = [];
			foreach (array_keys($section['optional']) as $name) {
				$section['extras'][$name] = $service::installed($name);
			}
			$formats[$id] = $section;
		}
		return $formats;
	}

	/**
	 * Renders the overview: the bytes saved, the images
	 * by status and the state of each format.
	 *
	 * @param array $formats
	 * @param array $counts
	 * @since 1.0.0
	 */
	private function render_overview($formats, $counts)
	{
		$savings = self::savings();
		?>
		<?php if (!Libraries::can_execute()) : ?>
			<div class="notice notice-error inline takopic-alert">
				<p>
					<strong><?php esc_html_e('PHP cannot run programs on this server.', 'takopic'); ?></strong>
					<?php
					$this->printf_html(
						/* translators: 1: proc_open(), 2: exec(), 3: disable_functions */
						__('%1$s and %2$s are disabled (%3$s): the libraries cannot run, even installed, so the JPG and PNG images are not compressed. Ask your host to enable %1$s. Meanwhile, GD or Imagick make the WebP and AVIF when they can.', 'takopic'),
						'<code>proc_open()</code>',
						'<code>exec()</code>',
						'<code>disable_functions</code>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<div class="takopic-card takopic-card--hero">
			<div class="takopic-savings">
				<div class="takopic-savings__main"<?php echo $savings['images'] ? '' : ' hidden'; ?>>
					<p class="takopic-savings__value">
						<?php
						/* translators: %s: a size, e.g. 12.5 MB. */
						$this->printf_html(__('%s saved', 'takopic'), '<span data-savings="saved">' . esc_html($savings['saved']) . '</span>');
						?>
					</p>
					<p class="takopic-savings__detail">
						<?php
						/* translators: %s: a reduction, e.g. −48 %. */
						$this->printf_html(__('%s on what the site serves', 'takopic'), '<span class="takopic-savings__reduction" data-savings="reduction">' . esc_html($savings['reduction']) . '</span>');
						?>
						<span class="takopic-savings__hint"<?php echo $savings['hint'] ? '' : ' hidden'; ?>>
							&middot; <a href="#delivery" data-tab="delivery">
								<?php
								/* translators: %s: a reduction, e.g. −62 %. */
								$this->printf_html(__('%s with WebP and AVIF served', 'takopic'), '<span data-savings="modern">' . esc_html($savings['modern']) . '</span>');
								?>
							</a>
						</span>
					</p>
					<p class="takopic-disk">
						<span class="takopic-disk__label">
							<span class="dashicons dashicons-database" aria-hidden="true"></span>
							<?php esc_html_e('On the disk', 'takopic'); ?>
						</span>
						<span class="takopic-disk__item">
							<?php
							/* translators: %s: the size of the original images, e.g. 4.2 MB. */
							$this->printf_html(__('%s of originals kept', 'takopic'), '<strong data-savings="originals">' . esc_html($savings['originals']) . '</strong>');
							?>
						</span>
						<span class="takopic-disk__item">
							<?php
							/* translators: %s: the size of the WebP and AVIF files, e.g. 916.6 KB. */
							$this->printf_html(__('%s of WebP and AVIF', 'takopic'), '<strong data-savings="formats">' . esc_html($savings['formats']) . '</strong>');
							?>
						</span>
					</p>
				</div>
				<div class="takopic-savings__empty"<?php echo $savings['images'] ? ' hidden' : ''; ?>>
					<p class="takopic-savings__value"><?php esc_html_e('Ready to squeeze', 'takopic'); ?></p>
					<p class="takopic-savings__detail"><?php esc_html_e('Takopic optimises your images on your own server: free, unlimited, and they never leave it.', 'takopic'); ?></p>
				</div>
				<?php // Filled while images wait for an optimisation. ?>
				<a class="takopic-cta<?php echo $counts[Status::TO_OPTIMISE] + $counts[Status::ERROR] + $counts[Status::OUTDATED] ? ' takopic-cta--filled' : ''; ?>" href="#optimise" data-tab="optimise">
					<?php esc_html_e('Optimise the media library', 'takopic'); ?>
					<span class="takopic-cta__arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				</a>
			</div>
			<ul class="takopic-counts">
				<?php foreach (self::count_labels() as $status => $label) : ?>
					<li class="takopic-counts__item takopic-counts__item--<?php echo esc_attr($status); ?>">
						<span class="takopic-counts__value" data-count="<?php echo esc_attr($status); ?>"><?php echo esc_html(number_format_i18n($counts[$status])); ?></span>
						<span class="takopic-counts__label"><?php echo esc_html($label); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<h2 class="takopic-heading"><?php esc_html_e('Formats', 'takopic'); ?></h2>
		<div class="takopic-libraries">
			<?php foreach ($formats as $id => $format) : ?>
				<?php $state = self::state($format); ?>
				<a class="takopic-library takopic-library--<?php echo esc_attr($state); ?>" href="#settings-<?php echo esc_attr($id); ?>" data-tab="settings" data-target="takopic-settings-<?php echo esc_attr($id); ?>">
					<span class="takopic-library__head">
						<span class="takopic-library__format"><?php echo esc_html($format['title']); ?></span>
						<?php $this->render_badge($state, self::state_label($state)); ?>
					</span>
					<?php $this->render_engine($format); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders the engine of a format: its library and
	 * version, or GD or Imagick without the library,
	 * then its optional libraries (e.g. pngquant).
	 *
	 * @param array $format
	 * @since 1.0.0
	 */
	private function render_engine($format)
	{
		$runs = Libraries::can_execute();
		if ($format['installed']) {
			$name = $format['library'];
			$state = __('installed', 'takopic');
		} elseif ($format['engine']) {
			$name = $format['engine'];
			$state = $runs
				/* translators: %s: a library, e.g. cwebp. */
				? sprintf(__('%s not installed', 'takopic'), $format['library'])
				/* translators: %s: a library, e.g. cwebp. */
				: sprintf(__('%s cannot run', 'takopic'), $format['library']);
		} else {
			$name = $format['library'];
			$state = $runs ? __('not installed', 'takopic') : __('cannot run', 'takopic');
		}
		?>
		<span class="takopic-engine takopic-engine--<?php echo $format['engine'] ? 'on' : 'off'; ?>">
			<span class="takopic-engine__name"><?php echo esc_html($name); ?></span>
			<span class="takopic-engine__state"><?php echo esc_html($state); ?></span>
			<?php if ($format['version']) : ?>
				<span class="takopic-engine__version"><?php echo esc_html($format['version']); ?></span>
			<?php endif; ?>
		</span>
		<?php foreach ($format['extras'] as $extra => $installed) : ?>
			<span class="takopic-engine takopic-engine--<?php echo $installed ? 'on' : 'off'; ?>">
				<span class="takopic-engine__name"><?php echo esc_html($extra); ?></span>
				<span class="takopic-engine__state"><?php echo esc_html($installed ? __('installed', 'takopic') : ($runs ? __('not installed', 'takopic') : __('cannot run', 'takopic'))); ?></span>
			</span>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Renders the optimisation of the media library:
	 * the new images, the outdated ones, or all.
	 *
	 * @param array $counts
	 * @param bool $jobs
	 * @since 1.0.0
	 */
	private function render_optimise($counts, $jobs)
	{
		?>
		<div class="takopic-card">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-images-alt2" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Optimise the media library', 'takopic'); ?></h2>
					<p class="takopic-card__description"><?php esc_html_e('These jobs run in the background, page closed or not, with the lowest priority on the server.', 'takopic'); ?></p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<?php $done = $counts['total'] > 0 && $counts[Status::OPTIMISED] === $counts['total']; ?>
				<div class="takopic-done" data-done role="status"<?php echo $done ? '' : ' hidden'; ?>>
					<span class="takopic-done__confetti" aria-hidden="true"></span>
					<span class="takopic-done__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<p class="takopic-done__text"><strong><?php esc_html_e('All your images are optimised.', 'takopic'); ?></strong> <?php esc_html_e('Nothing left to do: the new ones are optimised on upload.', 'takopic'); ?></p>
				</div>
				<div class="takopic-actions">
					<div class="takopic-action takopic-action--primary">
						<span class="takopic-action__icon dashicons dashicons-format-image" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('New images', 'takopic'); ?></h3>
						<p class="takopic-action__count" data-count="new"><?php echo esc_html(number_format_i18n($counts[Status::TO_OPTIMISE] + $counts[Status::ERROR])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Not optimised yet, or their optimisation failed.', 'takopic'); ?></p>
						<button type="button" class="button button-primary takopic-action__button" data-job="new"><?php esc_html_e('Optimise new images', 'takopic'); ?></button>
					</div>
					<div class="takopic-action">
						<span class="takopic-action__icon dashicons dashicons-update" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('Outdated images', 'takopic'); ?></h3>
						<p class="takopic-action__count" data-count="outdated"><?php echo esc_html(number_format_i18n($counts[Status::OUTDATED])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Optimised before a quality, a format, the maximum size or the thumbnail sizes changed. Optimised again from their originals.', 'takopic'); ?></p>
						<button type="button" class="button takopic-action__button" data-job="outdated"><?php esc_html_e('Re-optimise outdated images', 'takopic'); ?></button>
					</div>
					<div class="takopic-action">
						<span class="takopic-action__icon dashicons dashicons-images-alt" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('All images', 'takopic'); ?></h3>
						<p class="takopic-action__count" data-count="total"><?php echo esc_html(number_format_i18n($counts['total'])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Every image optimised again from its original, with its thumbnails made again.', 'takopic'); ?></p>
						<button type="button" class="button takopic-action__button" data-job="all" data-confirm="<?php esc_attr_e('Re-optimise all images from their originals, with their thumbnails? This can take a while.', 'takopic'); ?>"><?php esc_html_e('Re-optimise all images', 'takopic'); ?></button>
					</div>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
			<p class="takopic-card__footer">
				<?php esc_html_e('New uploads are optimised right away.', 'takopic'); ?>
				<a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>"><?php esc_html_e('See each image in the Media library', 'takopic'); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the delivery of the WebP and AVIF files,
	 * in three steps side by side: generate them (how
	 * many images have them), serve them (in the HTML or
	 * from the server) and test what the server sends.
	 * The results of the test come below, then the rules
	 * of the server and the theme helper, folded.
	 *
	 * @param array $display
	 * @param array $formats
	 * @param array $counts
	 * @since 1.0.0
	 */
	private function render_delivery($display, $formats, $counts)
	{
		$server = Htaccess::server();
		$names = ['apache' => 'Apache', 'nginx' => 'nginx', 'other' => __('Your server', 'takopic')];
		$made = self::with_formats();
		$total = $counts['total'];
		$percent = $total ? min(100, (int)round($made / $total * 100)) : 0;
		$rewrite = $display['fields']['serve_rewrite'];
		if ($server === 'apache') {
			// Takopic writes the rules: the box says so.
			/* translators: %s: uploads/.htaccess */
			$rewrite['note'] = sprintf(__('Apache: ticking it writes the rules below in %s, unticking it removes them.', 'takopic'), '<code>uploads/.htaccess</code>');
		} else {
			// Takopic cannot write the rules: the option tells they are in place.
			/* translators: %s: the server, e.g. nginx. */
			$rewrite['note'] = sprintf(__('%s: add the rules below to its configuration, then tick this box.', 'takopic'), esc_html($names[$server]));
			/* translators: 1: the server, e.g. nginx, 2: .htaccess. */
			$rewrite['help'] = sprintf(__('%1$s does not read %2$s files: add the rules below to its configuration, then tick this box so that Takopic counts the bytes saved. The HTML does not change, and every image is served, CSS backgrounds and custom themes included.', 'takopic'), esc_html($names[$server]), '<code>.htaccess</code>');
		}
		?>
		<ol class="takopic-steps">
			<li class="takopic-step">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">1</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php esc_html_e('Generate', 'takopic'); ?></h2>
						<p class="takopic-step__description"><?php esc_html_e('The WebP and AVIF versions are made while the images are optimised.', 'takopic'); ?></p>
					</div>
				</header>
				<div class="takopic-step__body takopic-generate">
					<p class="takopic-generate__value">
						<strong><?php echo esc_html(number_format_i18n($made)); ?></strong>
						<?php
						/* translators: %s: the number of images. */
						echo esc_html(sprintf(_n('of %s image has its WebP or AVIF version', 'of %s images have their WebP or AVIF version', $total, 'takopic'), number_format_i18n($total)));
						?>
					</p>
					<span class="takopic-meter" role="img" aria-label="<?php echo esc_attr($percent . ' %'); ?>"><span class="takopic-meter__value" style="width: <?php echo esc_attr($percent); ?>%"></span></span>
					<ul class="takopic-generate__formats">
						<?php foreach (['webp', 'avif'] as $id) : ?>
							<?php $state = self::state($formats[$id]); ?>
							<li>
								<a class="takopic-chip" href="#settings-<?php echo esc_attr($id); ?>" data-tab="settings" data-target="takopic-settings-<?php echo esc_attr($id); ?>">
									<span class="takopic-dot takopic-dot--<?php echo esc_attr($state); ?>" aria-hidden="true"></span>
									<span class="takopic-chip__name"><?php echo esc_html($formats[$id]['title']); ?></span>
									<span class="takopic-chip__state"><?php echo esc_html(self::state_label($state)); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ($made < $total) : ?>
						<a class="button takopic-generate__button" href="#optimise" data-tab="optimise"><?php esc_html_e('Optimise the media library', 'takopic'); ?></a>
					<?php endif; ?>
				</div>
			</li>

			<li class="takopic-step takopic-step--wide">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">2</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php echo esc_html($display['title']); ?></h2>
						<p class="takopic-step__description"><?php echo esc_html($display['description']); ?></p>
					</div>
				</header>
				<div class="takopic-step__body takopic-methods">
					<div class="takopic-method">
						<?php $this->render_field('serve_picture', $display['fields']['serve_picture']); ?>
					</div>
					<div class="takopic-method">
						<?php $this->render_field('serve_rewrite', $rewrite); ?>
					</div>
				</div>
				<footer class="takopic-step__actions">
					<?php submit_button(__('Save changes', 'takopic'), 'primary', 'submit', false, ['id' => 'takopic-save-delivery']); ?>
				</footer>
			</li>

			<li class="takopic-step takopic-test">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">3</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php esc_html_e('Test', 'takopic'); ?></h2>
						<p class="takopic-step__description"><?php esc_html_e('Requests an optimised image as a browser, after saving.', 'takopic'); ?></p>
					</div>
				</header>
				<div class="takopic-step__body">
					<ul class="takopic-test__list">
						<li><?php esc_html_e('Are the WebP and AVIF files served with their type?', 'takopic'); ?></li>
						<li><?php esc_html_e('Does the server answer the image with them?', 'takopic'); ?></li>
						<li><?php esc_html_e('Are the originals kept private?', 'takopic'); ?></li>
					</ul>
					<button type="button" class="button button-primary takopic-test__button" data-delivery-test><?php esc_html_e('Run the test', 'takopic'); ?></button>
					<p class="takopic-test__summary" aria-live="polite" hidden></p>
				</div>
			</li>
		</ol>

		<div class="takopic-card takopic-test__result" hidden>
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Test results', 'takopic'); ?></h2>
					<p class="takopic-card__description takopic-test__sample"></p>
				</div>
			</header>
			<div class="takopic-card__body">
				<ul class="takopic-checks"></ul>
			</div>
		</div>

		<h2 class="takopic-heading"><?php esc_html_e('Rules and code', 'takopic'); ?></h2>
		<?php if ($server === 'apache') : ?>
			<?php $written = Htaccess::rules_written(); ?>
			<details class="takopic-fold">
				<summary class="takopic-fold__summary">
					<span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for Apache and LiteSpeed', 'takopic'); ?></span>
					<?php $this->render_badge($written ? 'on' : 'muted', $written ? __('Written in uploads/.htaccess', 'takopic') : __('Not written', 'takopic')); ?>
				</summary>
				<div class="takopic-fold__content">
					<p class="takopic-fold__text">
							<?php
							/* translators: %s: uploads/.htaccess */
							$this->printf_html(__('Takopic writes them in %s when the server option is on, and removes them when it is off.', 'takopic'), '<code>uploads/.htaccess</code>');
							?>
						</p>
					<pre class="takopic-code"><code><?php echo esc_html(implode("\n", Htaccess::apache_rules())); ?></code></pre>
				</div>
			</details>
		<?php else : ?>
			<?php if ($server === 'other') : ?>
				<details class="takopic-fold">
					<summary class="takopic-fold__summary"><span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for Apache and LiteSpeed', 'takopic'); ?></span></summary>
					<div class="takopic-fold__content">
						<p class="takopic-fold__text">
								<?php
								/* translators: %s: uploads/.htaccess */
								$this->printf_html(__('In %s, written by Takopic when the server option is on.', 'takopic'), '<code>uploads/.htaccess</code>');
								?>
							</p>
						<pre class="takopic-code"><code><?php echo esc_html(implode("\n", Htaccess::apache_rules())); ?></code></pre>
					</div>
				</details>
			<?php endif; ?>
			<details class="takopic-fold">
				<summary class="takopic-fold__summary"><span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for nginx', 'takopic'); ?></span></summary>
				<div class="takopic-fold__content">
					<p class="takopic-fold__text"><?php esc_html_e('Add them to the configuration of the site, reload nginx, then tick the server option.', 'takopic'); ?></p>
					<pre class="takopic-code"><code><?php echo esc_html(Htaccess::nginx_rules()); ?></code></pre>
				</div>
			</details>
		<?php endif; ?>
		<?php $this->render_theme_help(); ?>
		<?php
	}

	/**
	 * Returns the number of images with a WebP or an
	 * AVIF version, for the first step of the delivery.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private static function with_formats()
	{
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One count over the media library, once per page.
		return (int)$wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT m.post_id) FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key IN (%s, %s)
			AND p.post_type = 'attachment' AND p.post_status = 'inherit'
			AND p.post_mime_type IN (" . implode(', ', array_fill(0, count(Files::MIMES), '%s')) . ")",
			array_merge([WebP::meta_key(), AVIF::meta_key()], Files::MIMES)
		));
	}

	/**
	 * Renders the settings: the image size, then one
	 * compact card per format, two per row on wide
	 * screens: its name, state and switch, the library
	 * making it, its quality fields, and how to install
	 * a missing library, folded.
	 *
	 * @param array $general
	 * @param array $formats
	 * @since 1.0.0
	 */
	private function render_settings($general, $formats)
	{
		?>
		<div class="takopic-card" id="takopic-settings-general">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-image-crop" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php echo esc_html($general['title']); ?></h2>
					<p class="takopic-card__description"><?php echo esc_html($general['description']); ?></p>
				</div>
			</header>
			<div class="takopic-card__body takopic-fields">
				<?php foreach ($general['fields'] as $name => $field) : ?>
					<?php $this->render_field($name, $field); ?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="takopic-formats">
			<?php foreach ($formats as $id => $format) : ?>
				<?php
				$state = self::state($format);
				$switch = $id . '_enable';
				$fields = $format['fields'];
				unset($fields[$switch]);
				?>
				<section class="takopic-format takopic-format--<?php echo esc_attr($state); ?>" id="takopic-settings-<?php echo esc_attr($id); ?>">
					<header class="takopic-format__header">
						<div class="takopic-format__top">
							<h2 class="takopic-format__title"><?php echo esc_html($format['title']); ?></h2>
							<?php $this->render_badge($state, self::state_label($state)); ?>
							<?php $this->render_switch($switch, $format['fields'][$switch]); ?>
						</div>
						<div class="takopic-format__engines">
							<?php $this->render_engine($format); ?>
						</div>
					</header>
					<p class="takopic-format__description"><?php echo wp_kses($format['fields'][$switch]['help'], ['strong' => [], 'code' => []]); ?></p>
					<?php if (!Libraries::can_execute()) : ?>
						<?php // Installing would not help: the overview says why. ?>
					<?php elseif (!$format['installed'] && $format['engine']) : ?>
						<details class="takopic-install takopic-install--advice">
							<summary class="takopic-install__summary">
								<?php
								/* translators: %s: a library, e.g. cwebp. */
								echo esc_html(sprintf(__('Recommended: install %s', 'takopic'), $format['library']));
								?>
							</summary>
							<p class="takopic-install__text"><?php echo esc_html(sprintf($format['recommendation'], $format['engine'])); ?></p>
							<?php $this->render_template('health' . DIRECTORY_SEPARATOR . $format['template']); ?>
						</details>
					<?php elseif (!$format['installed']) : ?>
						<details class="takopic-install">
							<summary class="takopic-install__summary">
								<?php
								/* translators: %s: a library, e.g. cwebp. */
								echo esc_html(sprintf(__('How to install %s', 'takopic'), $format['library']));
								?>
							</summary>
							<?php $this->render_template('health' . DIRECTORY_SEPARATOR . $format['template']); ?>
						</details>
					<?php endif; ?>
					<details class="takopic-format__settings">
						<summary class="takopic-format__summary"><?php esc_html_e('Settings', 'takopic'); ?></summary>
						<div class="takopic-format__fields takopic-fields">
							<?php foreach ($fields as $name => $field) : ?>
								<?php $this->render_field($name, self::availability($field, $format)); ?>
							<?php endforeach; ?>
							<?php foreach ($format['optional'] as $name => $use) : ?>
								<?php if ($format['installed'] && !$format['extras'][$name]) : ?>
									<details class="takopic-install takopic-install--advice">
										<summary class="takopic-install__summary">
											<?php
											/* translators: 1: a library, e.g. pngquant, 2: what it is for, e.g. lossy compression. */
											echo esc_html(sprintf(__('How to install %1$s, for the %2$s', 'takopic'), $name, $use));
											?>
										</summary>
										<?php $this->render_template('health' . DIRECTORY_SEPARATOR . $format['template']); ?>
									</details>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</details>
				</section>
			<?php endforeach; ?>
		</div>

		<div class="takopic-page__save">
			<?php submit_button(__('Save changes', 'takopic'), 'primary', 'submit', false, ['id' => 'takopic-save-settings']); ?>
		</div>
		<?php
	}

	/**
	 * Renders the maintenance: removing the WebP and
	 * AVIF files, restoring the original images. Each
	 * asks to tick a box first.
	 *
	 * @param bool $jobs
	 * @since 1.0.0
	 */
	private function render_maintenance($jobs)
	{
		?>
		<div class="takopic-card">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-trash" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Remove WebP and AVIF', 'takopic'); ?></h2>
					<p class="takopic-card__description">
						<?php
						/* translators: %s: the number of images. */
						$this->printf_html(__('Deletes the WebP and AVIF files of the %s images processed. The JPG and PNG files stay compressed, and the originals kept.', 'takopic'), '<strong data-count="processed">' . esc_html(number_format_i18n(count(Cleaner::ids()))) . '</strong>');
						?>
					</p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<div class="takopic-confirm">
					<label class="takopic-confirm__label"><input type="checkbox" class="takopic-confirm__input" data-confirm-for="remove_formats"> <?php esc_html_e('I understand the WebP and AVIF files will be deleted, until the images are optimised again.', 'takopic'); ?></label>
					<button type="button" class="button takopic-confirm__button" data-job="remove_formats"><?php esc_html_e('Remove WebP and AVIF', 'takopic'); ?></button>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
		</div>

		<div class="takopic-card takopic-card--danger">
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-backup" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Restore original images', 'takopic'); ?></h2>
					<p class="takopic-card__description"><?php esc_html_e('Puts the original images back and deletes every file made by Takopic (WebP, AVIF, backups). Do it before deleting Takopic: deleting it keeps the files as they are.', 'takopic'); ?></p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<div class="takopic-confirm">
					<label class="takopic-confirm__label"><input type="checkbox" class="takopic-confirm__input" data-confirm-for="restore"> <?php esc_html_e('I understand the images will no longer be optimised, and their WebP and AVIF files will be deleted.', 'takopic'); ?></label>
					<button type="button" class="button takopic-confirm__button takopic-button--danger" data-job="restore"><?php esc_html_e('Restore original images', 'takopic'); ?></button>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders why the jobs are not available: the
	 * dependencies of composer install are missing.
	 *
	 * @since 1.0.0
	 */
	private function render_no_jobs()
	{
		?>
		<p class="takopic-card__notice">
			<?php
			/* translators: 1: composer install, 2: wp takopic run */
			$this->printf_html(__('The background jobs need the dependencies of Takopic: run %1$s in its folder. WP-CLI (%2$s) works without them.', 'takopic'), '<code>composer install</code>', '<code>wp takopic run</code>');
			?>
		</p>
		<?php
	}

	/**
	 * Renders the progress of the background job, shown
	 * on every tab while it runs, and its result.
	 *
	 * @since 1.0.0
	 */
	private function render_job()
	{
		?>
		<div class="takopic-job" hidden>
			<div class="takopic-job__progress">
				<div class="takopic-job__head">
					<span class="takopic-job__icon dashicons dashicons-update" aria-hidden="true"></span>
					<p class="takopic-job__label" aria-live="polite"></p>
					<span class="takopic-job__percent"></span>
					<div class="takopic-job__controls">
						<button type="button" class="button" data-control="pause"><?php esc_html_e('Pause', 'takopic'); ?></button>
						<button type="button" class="button" data-control="resume"><?php esc_html_e('Resume', 'takopic'); ?></button>
						<button type="button" class="button-link button-link-delete" data-control="cancel"><?php esc_html_e('Cancel', 'takopic'); ?></button>
					</div>
				</div>
				<progress class="takopic-job__bar" max="100" value="0"></progress>
			</div>
			<div class="takopic-result" role="status" aria-live="polite">
				<span class="takopic-result__icon dashicons" aria-hidden="true"></span>
				<div class="takopic-result__body">
					<p class="takopic-result__title"></p>
					<p class="takopic-result__detail"></p>
				</div>
				<button type="button" class="takopic-result__close" data-control="dismiss">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e('Dismiss', 'takopic'); ?></span>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders how to use takopic_image() in a custom
	 * theme, whose images the display option does not
	 * change.
	 *
	 * @since 1.0.0
	 */
	private function render_theme_help()
	{
		$code = "<?php\nif (function_exists('takopic_image')) {\n\techo takopic_image(\$image_id, 'my-class');\n} else {\n\techo wp_get_attachment_image(\$image_id, 'full');\n}";
		?>
		<details class="takopic-fold">
			<summary class="takopic-fold__summary">
				<span class="takopic-fold__title">
					<?php
					/* translators: %s: takopic_image() */
					$this->printf_html(__('Custom themes: the %s helper', 'takopic'), '<code>takopic_image()</code>');
					?>
				</span>
			</summary>
			<div class="takopic-fold__content">
				<p class="takopic-fold__text">
					<?php
					/* translators: 1: <picture>, 2: takopic_image() */
					$this->printf_html(__('Images output with your own markup (e.g. from an ACF field) are not wrapped in a %1$s by the HTML option: serve them from the server, or use the %2$s helper, which returns their %1$s whatever the options.', 'takopic'), '<code>&lt;picture&gt;</code>', '<code>takopic_image()</code>');
					?>
				</p>
				<pre class="takopic-code"><code><?php echo esc_html($code); ?></code></pre>
				<p class="takopic-fold__text">
					<?php
					$this->printf_html(
						/* translators: 1: <picture>, 2: true, 3: data-src, 4: data-srcset, 5: function_exists() */
						__('The second argument is the class of the %1$s element. Pass %2$s as third argument to output %3$s and %4$s for a JavaScript lazy loader. Keep the %5$s check: without it, the pages break while Takopic is deactivated.', 'takopic'),
						'<code>&lt;picture&gt;</code>',
						'<code>true</code>',
						'<code>data-src</code>',
						'<code>data-srcset</code>',
						'<code>function_exists()</code>'
					);
					?>
				</p>
			</div>
		</details>
		<?php
	}

	/**
	 * Returns the bytes saved on the media library, as
	 * shown on the overview: the reduction of what the
	 * site serves, the one it would get with the WebP
	 * and AVIF served, and the disk used by Takopic. Sent
	 * again with the progress of a job (JobsAjax::status()).
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function savings()
	{
		$totals = Stats::totals();
		$served = Stats::served();
		return [
			'images' => $totals['images'],
			'saved' => Stats::size(max(0, $totals['original'] - $totals[$served])),
			'reduction' => (string)Stats::reduction($totals, $served),
			'modern' => (string)Stats::reduction($totals, 'modern'),
			// Serving the WebP and AVIF would save more.
			'hint' => $served === 'compressed' && $totals['modern'] < $totals['compressed'],
			'originals' => Stats::size($totals['original']),
			'formats' => Stats::size($totals['extra']),
		];
	}

	/**
	 * Returns the state of a format, used for the
	 * dots and badges: on (enabled, and its library
	 * installed or the image editor making it), off
	 * (enabled but nothing can make it) or muted
	 * (disabled).
	 *
	 * @param array $format
	 * @return string
	 * @since 1.0.0
	 */
	private static function state($format)
	{
		if (!$format['enabled']) {
			return 'muted';
		}
		return $format['engine'] ? 'on' : 'off';
	}

	/**
	 * Returns the labels of the counts of the overview,
	 * by status: a number of images, where the media
	 * column labels one image (MediaColumn::labels()).
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function count_labels()
	{
		return [
			Status::OPTIMISED => _x('Optimised', 'images', 'takopic'),
			Status::TO_OPTIMISE => _x('To optimise', 'images', 'takopic'),
			Status::OUTDATED => _x('Older settings', 'images', 'takopic'),
			Status::ERROR => _x('Error', 'images', 'takopic'),
		];
	}

	/**
	 * Returns the label of a format state, see state().
	 *
	 * @param string $state
	 * @return string
	 * @since 1.0.0
	 */
	private static function state_label($state)
	{
		$labels = [
			'on' => __('Active', 'takopic'),
			'off' => __('Library unavailable', 'takopic'),
			'muted' => __('Disabled', 'takopic'),
		];
		return $labels[$state];
	}

	/**
	 * Prints a translated text, escaped, with HTML in
	 * its placeholders, e.g. a <code> or a counter.
	 *
	 * @param string $format
	 * @param string ...$html Escaped by the caller.
	 * @since 1.0.0
	 */
	private function printf_html($format, ...$html)
	{
		echo sprintf(esc_html($format), ...$html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The text is escaped here, its placeholders by the caller.
	}

	/**
	 * Renders a status badge.
	 *
	 * @param string $variant on, off or muted.
	 * @param string $label
	 * @since 1.0.0
	 */
	private function render_badge($variant, $label)
	{
		printf('<span class="takopic-badge takopic-badge--%s">%s</span>', esc_attr($variant), esc_html($label));
	}

	/**
	 * Greys a setting of a format that nothing can use:
	 * all of them without a library or an image editor
	 * making the format, those of a missing optional
	 * library (e.g. pngquant), and the values needing
	 * one. A field disabled is not sent: it keeps its
	 * value.
	 *
	 * @param array $field
	 * @param array $format See formats().
	 * @return array The field, with disabled and disabled_options.
	 * @since 1.0.0
	 */
	private static function availability($field, $format)
	{
		$missing = function ($library) use ($format) {
			return empty($format['extras'][$library]);
		};
		$field['disabled'] = !$format['engine'] || (!empty($field['needs']) && $missing($field['needs']));
		$field['disabled_options'] = [];
		foreach (isset($field['option_needs']) ? $field['option_needs'] : [] as $option => $library) {
			if ($missing($library)) {
				$field['disabled_options'][$option] = $library;
			}
		}
		return $field;
	}

	/**
	 * Renders an option field: a toggle for a checkbox,
	 * a label and its control otherwise.
	 *
	 * @param string $name
	 * @param array $field
	 * @since 1.0.0
	 */
	private function render_field($name, $field)
	{
		$id = 'takopic-' . str_replace('_', '-', $name);
		$input = Settings::OPTION . '[' . $name . ']';
		$value = Settings::get($name);
		$setting = Settings::FIELDS[$name];
		$tags = ['strong' => [], 'code' => []];
		$disabled = !empty($field['disabled']) ? ' disabled' : '';
		// A field shown while another one has a value, e.g. the lossy quality (settings.js).
		$when = '';
		if (!empty($field['when'])) {
			$when = sprintf(' data-when="%s" data-when-value="%s"', esc_attr(Settings::OPTION . '[' . $field['when'][0] . ']'), esc_attr($field['when'][1]));
		}

		// The label names the control without being a <label>: hovering it would hover the control.
		printf(
			'<div class="takopic-field%s%s"%s><span class="takopic-field__label" id="%s-label">%s</span>',
			$field['type'] === 'checkbox' ? ' takopic-field--toggle' : '',
			$disabled ? ' takopic-field--disabled' : '',
			$when, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr($id),
			esc_html($field['label'])
		);

		if ($field['type'] === 'checkbox') {
			// An unticked box is not sent: the hidden field sends 0 instead.
			printf(
				'<input type="hidden" name="%s" value="0"><label class="takopic-toggle"><input type="checkbox" class="takopic-toggle__input" id="%s" name="%s" value="1" aria-labelledby="%s-label" %s><span class="takopic-toggle__track" aria-hidden="true"></span></label>',
				esc_attr($input), esc_attr($id), esc_attr($input), esc_attr($id), checked($value, true, false)
			);
		} else {
			echo '<div class="takopic-field__control">';
			if ($field['type'] === 'number') {
				printf(
					'<input type="number" class="takopic-field__input" id="%s" name="%s" value="%s" min="%d" max="%d" step="1" aria-labelledby="%s-label"%s>',
					esc_attr($id), esc_attr($input), esc_attr($value), (int)$setting['min'], (int)$setting['max'], esc_attr($id), esc_attr($disabled)
				);
			} elseif ($field['type'] === 'select') {
				printf('<select class="takopic-field__input" id="%s" name="%s" aria-labelledby="%s-label"%s>', esc_attr($id), esc_attr($input), esc_attr($id), esc_attr($disabled));
				foreach ($field['options'] as $option => $label) {
					printf('<option value="%s" %s>%s</option>', esc_attr($option), selected((string)$value, (string)$option, false), esc_html($label));
				}
				echo '</select>';
			} elseif ($field['type'] === 'choice') {
				// Every value in sight, as buttons side by side: radio buttons underneath. A value
				// needing a missing library is greyed, the first other one chosen, as the encoder does.
				$off = $field['disabled_options'] ?? [];
				if (isset($off[$value])) {
					$value = key(array_diff_key($field['options'], $off));
				}
				printf('<div class="takopic-choice" role="radiogroup" aria-labelledby="%s-label">', esc_attr($id));
				foreach ($field['options'] as $option => $label) {
					printf(
						'<label class="takopic-choice__option%s"%s><input type="radio" class="takopic-choice__input" name="%s" value="%s" %s%s><span class="takopic-choice__label">%s</span></label>',
						isset($off[$option]) ? ' takopic-choice__option--disabled' : '',
						/* translators: %s: a library, e.g. pngquant. */
						isset($off[$option]) ? sprintf(' title="%s"', esc_attr(sprintf(__('%s not installed', 'takopic'), $off[$option]))) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here.
						esc_attr($input), esc_attr($option), checked($value, $option, false), esc_attr($disabled || isset($off[$option]) ? ' disabled' : ''), esc_html($label)
					);
				}
				echo '</div>';
			}
			if (!empty($field['unit'])) {
				printf('<span class="takopic-field__unit">%s</span>', esc_html($field['unit']));
			}
			echo '</div>';
		}

		if (empty($field['note'])) {
			printf('<p class="takopic-field__help">%s</p></div>', wp_kses($field['help'], $tags));
			return;
		}
		// A short note, the full help behind a "?" button (settings.js hides it, shown without JS).
		printf(
			'<p class="takopic-field__help">%s <button type="button" class="takopic-help__toggle" aria-expanded="false" aria-controls="%s-help"><span aria-hidden="true">?</span><span class="screen-reader-text">%s</span></button></p><p class="takopic-field__help takopic-help__text" id="%s-help">%s</p></div>',
			wp_kses($field['note'], $tags), esc_attr($id), /* translators: %s: the label of a setting. */ esc_html(sprintf(__('More about: %s', 'takopic'), $field['label'])), esc_attr($id), wp_kses($field['help'], $tags)
		);
	}

	/**
	 * Renders a checkbox option as a bare switch, for
	 * the header of a format card: its label is read by
	 * screen readers, its help shown apart.
	 *
	 * @param string $name
	 * @param array $field
	 * @since 1.0.0
	 */
	private function render_switch($name, $field)
	{
		$input = Settings::OPTION . '[' . $name . ']';
		printf(
			'<input type="hidden" name="%s" value="0"><label class="takopic-toggle takopic-toggle--switch"><input type="checkbox" class="takopic-toggle__input" id="%s" name="%s" value="1" %s><span class="takopic-toggle__track" aria-hidden="true"></span><span class="screen-reader-text">%s</span></label>',
			esc_attr($input), esc_attr('takopic-' . str_replace('_', '-', $name)), esc_attr($input), checked(Settings::get($name), true, false), esc_html($field['label'])
		);
	}

	/**
	 * Returns the option fields, grouped by section.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function sections()
	{
		return [
			'jpg' => [
				'title' => 'JPG',
				'service' => JPG::class,
				'template' => 'jpg.php',
				'fields' => [
					'jpg_enable' => [
						'type' => 'checkbox',
						'label' => __('Compress JPG images', 'takopic'),
						'help' => __('Compresses the JPG images in place with jpegoptim, and makes them progressive. Their originals are kept.', 'takopic'),
					],
					'jpg_quality' => [
						'type' => 'number',
						'label' => __('Quality', 'takopic'),
						'help' => __('From 0 to 100, 80 by default.', 'takopic'),
					],
				],
			],
			'png' => [
				'title' => 'PNG',
				'service' => PNG::class,
				'template' => 'png.php',
				'optional' => ['pngquant' => __('lossy compression', 'takopic')],
				'fields' => [
					'png_enable' => [
						'type' => 'checkbox',
						'label' => __('Compress PNG images', 'takopic'),
						'help' => __('Compresses the PNG images in place with oxipng or optipng, after pngquant when lossy. Their originals are kept.', 'takopic'),
					],
					'png_compression' => [
						'type' => 'choice',
						'label' => __('Compression', 'takopic'),
						'options' => [
							'lossless' => __('Lossless', 'takopic'),
							'lossy' => __('Lossy (pngquant)', 'takopic'),
						],
						'option_needs' => ['lossy' => 'pngquant'],
						'help' => __('Lossy: often 60 to 80 % lighter, for graphics and screenshots. Needs pngquant.', 'takopic'),
					],
					'png_level' => [
						'type' => 'select',
						'label' => __('Optimisation level', 'takopic'),
						'options' => ['0' => 'o0', '1' => 'o1', '2' => 'o2', '3' => 'o3', '4' => 'o4', '5' => 'o5', '6' => 'o6', '7' => 'o7'],
						'help' => __('From o0 (fastest) to o7 (smallest files), o2 by default.', 'takopic'),
					],
					'png_lossy_quality' => [
						'type' => 'number',
						'label' => __('Lossy quality', 'takopic'),
						'when' => ['png_compression', 'lossy'],
						'needs' => 'pngquant',
						'help' => __('The quality pngquant aims at, 80 by default.', 'takopic'),
					],
				],
			],
			'webp' => [
				'title' => 'WebP',
				'service' => WebP::class,
				'template' => 'webp.php',
				/* translators: %s: GD or Imagick. */
				'recommendation' => __('%s makes the WebP files for now, in the PHP process. cwebp runs apart from PHP, and makes the PNG graphics and screenshots much lighter.', 'takopic'),
				'fields' => [
					'webp_enable' => [
						'type' => 'checkbox',
						'label' => __('Make WebP versions', 'takopic'),
						'help' => __('Makes a WebP version of each image and thumbnail (<code>photo.jpg.webp</code>), lighter than the JPG or PNG.', 'takopic'),
					],
					'webp_quality' => [
						'type' => 'number',
						'label' => __('Quality', 'takopic'),
						'help' => __('From 0 to 100, 80 by default.', 'takopic'),
					],
				],
			],
			'avif' => [
				'title' => 'AVIF',
				'service' => AVIF::class,
				'template' => 'avif.php',
				/* translators: %s: GD or Imagick. */
				'recommendation' => __('%s makes the AVIF files for now, in the PHP process. avifenc runs apart from PHP, on several threads, and keeps the colours of the PNG images sharper.', 'takopic'),
				'fields' => [
					'avif_enable' => [
						'type' => 'checkbox',
						'label' => __('Make AVIF versions', 'takopic'),
						'help' => __('Makes an AVIF version of each image and thumbnail (<code>photo.jpg.avif</code>), often the lightest. Kept only when lighter than the WebP and the JPG or PNG.', 'takopic'),
					],
					'avif_quality' => [
						'type' => 'number',
						'label' => __('Quality', 'takopic'),
						'help' => __('From 0 to 100 (lossless), 70 by default.', 'takopic'),
					],
				],
			],
			'general' => [
				'title' => __('Image size', 'takopic'),
				'description' => __('WordPress scales the big images down on upload, and keeps the image uploaded as their original.', 'takopic'),
				'fields' => [
					'max_size' => [
						'type' => 'number',
						'label' => __('Maximum image size', 'takopic'),
						'unit' => 'px',
						'help' => __('Longest side, 2560 by default. <strong>0 means no limit: huge images can exhaust the server.</strong>', 'takopic'),
					],
				],
			],
			'display' => [
				'title' => __('Serve', 'takopic'),
				'description' => __('Each browser gets the lightest version it reads: AVIF, then WebP, or the JPG or PNG for older ones. Choose one way, or both.', 'takopic'),
				'fields' => [
					'serve_picture' => [
						'type' => 'checkbox',
						'label' => __('In the HTML, with <picture>', 'takopic'),
						'note' => __('Wraps the images of the content in a <code>&lt;picture&gt;</code>. Untick it if the layout breaks.', 'takopic'),
						'help' => __('Images of the post content, featured images and galleries are wrapped in a <code>&lt;picture&gt;</code> tag offering the AVIF and WebP versions. Works on every server. <strong>Untick this if your theme layout breaks around images.</strong>', 'takopic'),
					],
					'serve_rewrite' => [
						'type' => 'checkbox',
						'label' => __('From the server, with rewrite rules', 'takopic'),
						'note' => __('Every image, CSS backgrounds included, the HTML unchanged.', 'takopic'),
						'help' => __('The server answers <code>photo.jpg</code> with <code>photo.jpg.avif</code> or <code>.webp</code> when the browser reads them. The HTML does not change, and every image is served, CSS backgrounds and custom themes included. A CDN must vary its cache on the <code>Accept</code> header.', 'takopic'),
					],
				],
			],
		];
	}

	/**
	 * Renders an Takopic template in the /templates
	 * folder.
	 *
	 * @param $name
	 * @since 1.0.0
	 */
	private function render_template($name)
	{
		include TAKOPIC_TEMPLATE_PATH . DIRECTORY_SEPARATOR . $name;
	}
}
