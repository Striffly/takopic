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
use Takopic\Encoders\Formats;
use Takopic\Engines\Libraries;
use Takopic\Media\Cleaner;
use Takopic\Media\Files;
use Takopic\Media\Stats;
use Takopic\Media\Status;
use Takopic\Plugin;
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
	 * The stylesheets of the page, in their cascade
	 * order: base/ what every tab shares, tabs/ one tab
	 * each.
	 *
	 * @var string[]
	 */
	const STYLES = [
		'base/tokens',
		'base/layout',
		'base/wordpress',
		'base/components',
		'base/fields',
		'base/jobs',
		'tabs/overview',
		'tabs/optimise',
		'tabs/delivery',
		'tabs/settings',
	];

	/**
	 * The scripts of the page: each class after the
	 * ones it uses, then settings.js, which starts them.
	 *
	 * @var string[]
	 */
	const SCRIPTS = [
		'utils/Api',
		'utils/Confetti',
		'utils/Roll',
		'views/Screen',
		'components/Component',
		'components/Tabs',
		'components/Form',
		'components/HelpToggle',
		'components/DependentField',
		'components/CopyButton',
		'components/Wallet',
		'components/Count',
		'components/Job',
		'components/JobStart',
		'components/Savings',
		'components/Cta',
		'components/AllOptimised',
		'components/DeliveryTest',
		'settings',
	];

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
		Plugin::enqueue_chain(self::STYLES, 'style');
		$script = Plugin::enqueue_chain(self::SCRIPTS, 'script');
		Ajax::localize($script, 'takopicSettings', Ajax::NONCE);
		add_action('admin_head', [$this, 'preload_font']);
	}

	/**
	 * Preloads the display font (tokens.css), so that
	 * the titles and the figures do not show in another
	 * font first, then move once it is loaded.
	 *
	 * @since 1.0.0
	 */
	public function preload_font()
	{
		printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url(TAKOPIC_URL . 'assets/fonts/unbounded-variable.woff2'));
	}

	/**
	 * Adds the options page under Settings, and a link
	 * to its optimise tab under Media, where the other
	 * optimizers put their bulk optimisation. Without a
	 * callback, WordPress links the slug as it is.
	 *
	 * @since 1.0.0
	 */
	public function add_page()
	{
		$hook = add_options_page('Takopic', 'Takopic', 'manage_options', self::PAGE, [$this, 'render_page']);
		// Before the header, which shows the errors of the settings.
		add_action('load-' . $hook, [Htaccess::class, 'repair']);
		add_media_page('Takopic', __('Optimise images', 'takopic'), 'manage_options', 'options-general.php?page=' . self::PAGE . '#optimise');
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
		$sections = self::sections();
		$formats = self::formats($sections);
		$counts = Status::counts();
		$jobs = Plugin::has_background();
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
		<div class="wrap takopic-page js-takopic-page">
			<header class="takopic-header">
				<?php // The octopus floats, unless the system asks for less motion. Loaded early, as it opens the page. ?>
				<picture class="takopic-header__picture">
					<source srcset="<?php echo esc_url(TAKOPIC_URL . 'assets/images/icon-128x128.png'); ?> 1x, <?php echo esc_url(TAKOPIC_URL . 'assets/images/icon-256x256.png'); ?> 2x" media="(prefers-reduced-motion: reduce)">
					<img class="takopic-header__logo" src="<?php echo esc_url(TAKOPIC_URL . 'assets/images/icon-128x128.gif'); ?>" srcset="<?php echo esc_url(TAKOPIC_URL . 'assets/images/icon-256x256.gif'); ?> 2x" alt="" width="80" height="80" fetchpriority="high">
				</picture>
				<div class="takopic-header__text">
					<h1 class="takopic-header__title">Takopic</h1>
					<?php // The tagline stays in English, as the name of Takopic. ?>
					<p class="takopic-header__tagline">Squeezes images. Spills no ink, sends no bill.</p>
				</div>
				<span class="takopic-header__version">v<?php echo esc_html(TAKOPIC_VERSION); ?></span>
			</header>
			<?php // WordPress puts its notices after the first heading of the page, or this marker. ?>
			<hr class="wp-header-end">

			<nav class="takopic-tabs js-takopic-tabs" role="tablist" aria-label="Takopic">
				<?php foreach ($tabs as $id => $title) : ?>
					<a href="#<?php echo esc_attr($id); ?>" class="takopic-tabs__tab js-takopic-tab js-takopic-tab-link" role="tab" data-tab="<?php echo esc_attr($id); ?>">
						<?php echo esc_html($title); ?>
						<?php if ($id === 'settings') : ?>
							<?php // Follows the settings as they are saved (Form.js). ?>
							<span class="takopic-tabs__dot js-takopic-live" id="takopic-tab-settings-dot">
								<?php if ($missing) : ?>
									<span class="takopic-dot takopic-dot--off" title="<?php esc_attr_e('A format has no library', 'takopic'); ?>"></span>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
			// Before the page paints, picks the tab as Tabs.js will, so that the other panels never show while the scripts load.
			wp_print_inline_script_tag(
				"(() => {
					const page = document.currentScript.closest('.js-takopic-page');
					const tabs = [...page.querySelectorAll('.js-takopic-tab')];
					let name = location.hash.slice(1);
					try {
						name ||= sessionStorage.getItem('takopic-tab');
					} catch {
						// Not kept.
					}
					if (!tabs.some((tab) => tab.dataset.tab === name)) {
						name = 'overview';
					}
					page.classList.add('takopic-page--js');
					page.dataset.tab = name;
					tabs.forEach((tab) => tab.classList.toggle('takopic-tabs__tab--active', tab.dataset.tab === name));
				})();"
			);
			?>

			<?php if ($jobs) : ?>
				<?php $this->render_job(); ?>
			<?php endif; ?>

			<form class="takopic-page__form js-takopic-form" action="options.php" method="post">
				<?php settings_fields(Settings::GROUP); ?>

				<section class="takopic-panel js-takopic-panel" id="takopic-panel-overview" role="tabpanel" data-panel="overview">
					<?php $this->render_overview($formats, $counts); ?>
				</section>

				<section class="takopic-panel js-takopic-panel" id="takopic-panel-optimise" role="tabpanel" data-panel="optimise">
					<?php $this->render_optimise($counts, $jobs); ?>
				</section>

				<section class="takopic-panel js-takopic-panel" id="takopic-panel-delivery" role="tabpanel" data-panel="delivery">
					<?php $this->render_delivery($sections['display'], $formats, $counts); ?>
				</section>

				<section class="takopic-panel js-takopic-panel" id="takopic-panel-settings" role="tabpanel" data-panel="settings">
					<?php $this->render_settings($sections['general'], $formats); ?>
				</section>

				<section class="takopic-panel js-takopic-panel" id="takopic-panel-maintenance" role="tabpanel" data-panel="maintenance">
					<?php $this->render_maintenance($jobs); ?>
				</section>
			</form>

			<?php $this->render_support(); ?>
			<span class="screen-reader-text js-takopic-announcer" aria-live="polite"></span>
		</div>
		<?php
	}

	/**
	 * Renders how to buy a coffee to the author, at the
	 * bottom of every tab: PayPal, or Bitcoin with its
	 * address and QR code, behind its button (Wallet.js
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
				esc_html_e('Takopic is free, and will stay so. If it saves you time or bytes, you can buy me a coffee:', 'takopic');
				?>
				<a href="<?php echo esc_url(self::PAYPAL); ?>" target="_blank" rel="noopener">PayPal</a>
				<span aria-hidden="true">&middot;</span>
				<button type="button" class="button-link takopic-support__toggle js-takopic-wallet-toggle" aria-expanded="true" aria-controls="takopic-support-wallet">Bitcoin</button>
			</p>
			<div class="takopic-support__wallet js-takopic-wallet" id="takopic-support-wallet">
				<a class="takopic-support__qr" href="<?php echo esc_url('bitcoin:' . self::BITCOIN, ['bitcoin']); ?>">
					<img src="<?php echo esc_url(TAKOPIC_URL . 'assets/images/bitcoin-qr.svg'); ?>" width="104" height="104" alt="<?php esc_attr_e('QR code of the Bitcoin address', 'takopic'); ?>">
				</a>
				<?php $this->render_command(self::BITCOIN, 'takopic-support__address'); ?>
			</div>
		</footer>
		<?php
	}

	/**
	 * Returns the format sections (JPG, PNG, WebP,
	 * AVIF) with the state of their library: installed,
	 * version, the engine making the format (the
	 * library, or GD or Imagick for WebP and AVIF
	 * without it, null if none), the optional
	 * libraries (e.g. pngquant), and whether the
	 * better library replacing the one installed is
	 * missing (oxipng, for optipng).
	 *
	 * @param array $sections
	 * @return array
	 * @since 1.0.0
	 */
	private static function formats($sections)
	{
		$formats = [];
		foreach (Formats::ENCODERS as $id => $service) {
			$section = $sections[$id];
			$section['title'] = $service::TITLE;
			$section['installed'] = $service::installed();
			$section['version'] = $service::version_number();
			$section['enabled'] = Settings::get($id . '_enable');
			$section['library'] = $service::library();
			$section['engine'] = $service::engine();
			$section['preferred'] = $service::PREFERRED;
			$section['upgrade'] = $section['installed'] && $service::PREFERRED && !$service::installed($service::PREFERRED);
			$section['extras'] = [];
			foreach ($service::OPTIONAL as $name) {
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
		$savings = Stats::savings();
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
			<div class="takopic-savings js-takopic-savings">
				<div class="takopic-savings__main js-takopic-savings-main"<?php echo $savings['images'] ? '' : ' hidden'; ?>>
					<p class="takopic-savings__value">
						<?php
						/* translators: %s: a size, e.g. 12.5 MB. */
						$this->printf_html(__('%s saved', 'takopic'), '<span class="js-takopic-savings-value" data-savings="saved">' . esc_html($savings['saved']) . '</span>');
						?>
					</p>
					<?php
					// Before the page paints, shows 0 over the size: it rolls up the first time the overview shows (Savings.js).
					wp_print_inline_script_tag(
						"(() => {
							const saved = document.currentScript.previousElementSibling.querySelector('.js-takopic-savings-value');
							if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
								saved.dataset.value = saved.textContent.replace(/\d(?:[\d.,\s]*\d)?/, (number) => (/[.,]\d$/.test(number) ? '0' + number.at(-2) + '0' : '0'));
								saved.classList.add('takopic-savings__rolling');
							}
						})();"
					);
					?>
					<p class="takopic-savings__detail">
						<?php
						/* translators: %s: a reduction, e.g. −48 %. */
						$this->printf_html(__('%s on what the site serves', 'takopic'), '<span class="takopic-savings__reduction js-takopic-savings-value" data-savings="reduction">' . esc_html($savings['reduction']) . '</span>');
						?>
						<span class="takopic-savings__hint js-takopic-savings-hint"<?php echo $savings['hint'] ? '' : ' hidden'; ?>>
							&middot; <a class="js-takopic-tab-link" href="#delivery" data-tab="delivery">
								<?php
								/* translators: %s: a reduction, e.g. −62 %. */
								$this->printf_html(__('%s with WebP and AVIF served', 'takopic'), '<span class="js-takopic-savings-value" data-savings="modern">' . esc_html($savings['modern']) . '</span>');
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
							$this->printf_html(__('%s of originals kept', 'takopic'), '<strong class="js-takopic-savings-value" data-savings="originals">' . esc_html($savings['originals']) . '</strong>');
							?>
						</span>
						<span class="takopic-disk__item">
							<?php
							/* translators: %s: the size of the WebP and AVIF files, e.g. 916.6 KB. */
							$this->printf_html(__('%s of WebP and AVIF', 'takopic'), '<strong class="js-takopic-savings-value" data-savings="formats">' . esc_html($savings['formats']) . '</strong>');
							?>
						</span>
					</p>
				</div>
				<div class="takopic-savings__empty js-takopic-savings-empty"<?php echo $savings['images'] ? ' hidden' : ''; ?>>
					<p class="takopic-savings__value"><?php esc_html_e('Ready to squeeze', 'takopic'); ?></p>
					<p class="takopic-savings__detail"><?php esc_html_e('Takopic optimises your images on your own server: free, unlimited, and they never leave it.', 'takopic'); ?></p>
				</div>
				<?php // Filled while new images wait for their optimisation, not those optimised with older settings. ?>
				<a class="takopic-cta js-takopic-cta js-takopic-tab-link<?php echo $counts[Status::TO_OPTIMISE] ? ' takopic-cta--filled' : ''; ?>" href="#optimise" data-tab="optimise">
					<?php esc_html_e('Optimise the media library', 'takopic'); ?>
					<span class="takopic-cta__arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				</a>
			</div>
			<ul class="takopic-counts">
				<?php foreach (self::count_labels() as $status => $label) : ?>
					<li class="takopic-counts__item takopic-counts__item--<?php echo esc_attr($status); ?>">
						<span class="takopic-counts__value js-takopic-count" data-count="<?php echo esc_attr($status); ?>"><?php echo esc_html(number_format_i18n($counts[$status])); ?></span>
						<span class="takopic-counts__label"><?php echo esc_html($label); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<h2 class="takopic-heading"><?php esc_html_e('Formats', 'takopic'); ?></h2>
		<div class="takopic-libraries js-takopic-live" id="takopic-overview-formats">
			<?php foreach ($formats as $id => $format) : ?>
				<?php $state = self::state($format); ?>
				<a class="takopic-library takopic-library--<?php echo esc_attr($state); ?> js-takopic-tab-link" href="#settings-<?php echo esc_attr($id); ?>" data-tab="settings" data-target="takopic-settings-<?php echo esc_attr($id); ?>">
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
				<div class="takopic-done js-takopic-all-optimised" role="status"<?php echo $done ? '' : ' hidden'; ?>>
					<span class="takopic-confetti js-takopic-confetti" aria-hidden="true"></span>
					<span class="takopic-done__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<p class="takopic-done__text js-takopic-live" id="takopic-optimise-done">
						<strong><?php esc_html_e('All your images are optimised.', 'takopic'); ?></strong>
						<?php if (Settings::get('optimise_uploads')) : ?>
							<?php esc_html_e('Nothing left to do: the new ones are optimised on upload.', 'takopic'); ?>
						<?php else : ?>
							<?php esc_html_e('The new uploads will wait here, to optimise when you like.', 'takopic'); ?>
						<?php endif; ?>
					</p>
				</div>
				<div class="takopic-actions">
					<div class="takopic-action takopic-action--primary">
						<span class="takopic-action__icon dashicons dashicons-format-image" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('New images', 'takopic'); ?></h3>
						<p class="takopic-action__count js-takopic-count" data-count="new"><?php echo esc_html(number_format_i18n($counts[Status::TO_OPTIMISE] + $counts[Status::ERROR])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Not optimised yet, or their optimisation failed.', 'takopic'); ?></p>
						<button type="button" class="button button-primary takopic-action__button js-takopic-job-start" data-job="new" disabled><?php esc_html_e('Optimise new images', 'takopic'); ?></button>
					</div>
					<div class="takopic-action">
						<span class="takopic-action__icon dashicons dashicons-update" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('Outdated images', 'takopic'); ?></h3>
						<p class="takopic-action__count js-takopic-count" data-count="outdated"><?php echo esc_html(number_format_i18n($counts[Status::OUTDATED])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Optimised before a quality, a format, the maximum size or the thumbnail sizes changed. Optimised again from their originals.', 'takopic'); ?></p>
						<button type="button" class="button takopic-action__button js-takopic-job-start" data-job="outdated" disabled><?php esc_html_e('Re-optimise outdated images', 'takopic'); ?></button>
					</div>
					<div class="takopic-action">
						<span class="takopic-action__icon dashicons dashicons-images-alt" aria-hidden="true"></span>
						<h3 class="takopic-action__title"><?php esc_html_e('All images', 'takopic'); ?></h3>
						<p class="takopic-action__count js-takopic-count" data-count="total"><?php echo esc_html(number_format_i18n($counts['total'])); ?></p>
						<p class="takopic-action__text"><?php esc_html_e('Every image optimised again from its original, with its thumbnails made again.', 'takopic'); ?></p>
						<button type="button" class="button takopic-action__button js-takopic-job-start" data-job="all" data-confirm="<?php esc_attr_e('Re-optimise all images from their originals, with their thumbnails? This can take a while.', 'takopic'); ?>" disabled><?php esc_html_e('Re-optimise all images', 'takopic'); ?></button>
					</div>
				</div>
			<?php else : ?>
				<?php $this->render_no_jobs(); ?>
			<?php endif; ?>
			<p class="takopic-card__footer js-takopic-live" id="takopic-optimise-footer">
				<?php if (Settings::get('optimise_uploads')) : ?>
					<span class="takopic-uploads takopic-uploads--on"><?php esc_html_e('New uploads are optimised right away.', 'takopic'); ?></span>
				<?php else : ?>
					<span class="takopic-uploads takopic-uploads--off">
						<?php esc_html_e('New uploads wait here, as new images.', 'takopic'); ?>
						<a class="js-takopic-tab-link" href="#settings" data-tab="settings" data-target="takopic-settings-general"><?php esc_html_e('Optimise them on upload', 'takopic'); ?></a>
					</span>
				<?php endif; ?>
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
		$made = Status::with_versions();
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
				<div class="takopic-step__body takopic-generate js-takopic-live" id="takopic-delivery-generate">
					<p class="takopic-generate__value">
						<strong><?php echo esc_html(number_format_i18n($made)); ?></strong>
						<?php
						/* translators: %s: the number of images. */
						echo esc_html(sprintf(_n('of %s image has its WebP or AVIF version', 'of %s images have their WebP or AVIF version', $total, 'takopic'), number_format_i18n($total)));
						?>
					</p>
					<span class="takopic-meter" role="img" aria-label="<?php echo esc_attr($percent . ' %'); ?>"><span class="takopic-meter__value" style="width: <?php echo esc_attr($percent); ?>%"></span></span>
					<ul class="takopic-generate__formats">
						<?php foreach (array_keys(Formats::variants()) as $id) : ?>
							<?php $state = self::state($formats[$id]); ?>
							<li>
								<a class="takopic-chip js-takopic-tab-link" href="#settings-<?php echo esc_attr($id); ?>" data-tab="settings" data-target="takopic-settings-<?php echo esc_attr($id); ?>">
									<span class="takopic-dot takopic-dot--<?php echo esc_attr($state); ?>" aria-hidden="true"></span>
									<span class="takopic-chip__name"><?php echo esc_html($formats[$id]['title']); ?></span>
									<span class="takopic-chip__state"><?php echo esc_html(self::state_label($state)); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ($made < $total) : ?>
						<a class="button takopic-generate__button js-takopic-tab-link" href="#optimise" data-tab="optimise"><?php esc_html_e('Optimise the media library', 'takopic'); ?></a>
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
				<footer class="takopic-step__actions takopic-save">
					<?php submit_button(__('Save changes', 'takopic'), 'primary', 'submit', false, ['id' => 'takopic-save-delivery']); ?>
				</footer>
			</li>

			<li class="takopic-step takopic-test">
				<header class="takopic-step__header">
					<span class="takopic-step__number" aria-hidden="true">3</span>
					<div class="takopic-step__heading">
						<h2 class="takopic-step__title"><?php esc_html_e('Test', 'takopic'); ?></h2>
						<p class="takopic-step__description"><?php esc_html_e('Requests an optimised image as a browser would.', 'takopic'); ?></p>
					</div>
				</header>
				<div class="takopic-step__body">
					<ul class="takopic-test__list">
						<li><?php esc_html_e('Are the WebP and AVIF files served with their type?', 'takopic'); ?></li>
						<li><?php esc_html_e('Does the server answer the image with them?', 'takopic'); ?></li>
						<li><?php esc_html_e('Are the originals kept private?', 'takopic'); ?></li>
					</ul>
					<button type="button" class="button button-primary takopic-test__button js-takopic-delivery-test"><?php esc_html_e('Run the test', 'takopic'); ?></button>
					<p class="takopic-test__summary js-takopic-test-summary" aria-live="polite" hidden></p>
				</div>
			</li>
		</ol>

		<div class="takopic-card takopic-test__result js-takopic-test-result" hidden>
			<header class="takopic-card__header">
				<span class="takopic-card__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div>
					<h2 class="takopic-card__title"><?php esc_html_e('Test results', 'takopic'); ?></h2>
					<p class="takopic-card__description takopic-test__sample js-takopic-test-sample"></p>
				</div>
			</header>
			<div class="takopic-card__body">
				<ul class="takopic-checks js-takopic-test-checks"></ul>
				<template class="js-takopic-check-template">
					<li class="takopic-checks__item js-takopic-check">
						<span class="takopic-checks__icon dashicons" aria-hidden="true"></span>
						<span class="takopic-checks__label js-takopic-check-label"></span>
						<span class="takopic-checks__detail js-takopic-check-detail"></span>
						<span class="takopic-checks__advice js-takopic-check-advice"></span>
					</li>
				</template>
			</div>
		</div>

		<h2 class="takopic-heading"><?php esc_html_e('Rules and code', 'takopic'); ?></h2>
		<?php if ($server !== 'nginx') : ?>
			<details class="takopic-fold">
				<summary class="takopic-fold__summary">
					<span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for Apache and LiteSpeed', 'takopic'); ?></span>
					<?php if ($server === 'apache') : ?>
						<?php $written = Htaccess::rules_written(); ?>
						<?php $this->render_badge($written ? 'on' : 'muted', $written ? __('Written in uploads/.htaccess', 'takopic') : __('Not written', 'takopic'), 'takopic-delivery-rules-state'); ?>
					<?php endif; ?>
				</summary>
				<div class="takopic-fold__content">
					<p class="takopic-fold__text">
						<?php
						$this->printf_html(
							$server === 'apache'
								/* translators: %s: uploads/.htaccess */
								? __('Takopic writes them in %s when the server option is on, and removes them when it is off.', 'takopic')
								/* translators: %s: uploads/.htaccess */
								: __('In %s, written by Takopic when the server option is on.', 'takopic'),
							'<code>uploads/.htaccess</code>'
						);
						?>
					</p>
					<?php $this->render_code(implode("\n", Htaccess::apache_rules()), 'takopic-code-apache'); ?>
				</div>
			</details>
		<?php endif; ?>
		<?php if ($server !== 'apache') : ?>
			<details class="takopic-fold">
				<summary class="takopic-fold__summary"><span class="takopic-fold__title"><?php esc_html_e('Rewrite rules for nginx', 'takopic'); ?></span></summary>
				<div class="takopic-fold__content">
					<p class="takopic-fold__text"><?php esc_html_e('Add them to the configuration of the site, reload nginx, then tick the server option.', 'takopic'); ?></p>
					<?php $this->render_code(Htaccess::nginx_rules(), 'takopic-code-nginx'); ?>
				</div>
			</details>
		<?php endif; ?>
		<?php $this->render_theme_help(); ?>
		<?php
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
				<span class="takopic-card__icon dashicons dashicons-upload" aria-hidden="true"></span>
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
				<?php // Its state follows the settings as they are saved (Form.js): its class only, its fields kept. ?>
				<section class="takopic-format takopic-format--<?php echo esc_attr($state); ?> js-takopic-live" id="takopic-settings-<?php echo esc_attr($id); ?>" data-live="class">
					<header class="takopic-format__header">
						<div class="takopic-format__top">
							<h2 class="takopic-format__title"><?php echo esc_html($format['title']); ?></h2>
							<?php $this->render_badge($state, self::state_label($state), 'takopic-settings-' . $id . '-state'); ?>
							<?php $this->render_toggle($switch, $format['fields'][$switch]['label']); ?>
						</div>
						<div class="takopic-format__engines">
							<?php $this->render_engine($format); ?>
						</div>
					</header>
					<p class="takopic-format__description"><?php echo wp_kses($format['fields'][$switch]['help'], ['strong' => [], 'code' => []]); ?></p>
					<?php if (!Libraries::can_execute()) : ?>
						<?php // Installing would not help: the overview says why. ?>
					<?php elseif (!$format['installed'] && $format['engine']) : ?>
						<?php
						/* translators: %s: a library, e.g. cwebp. */
						$this->render_install($id, sprintf(__('Recommended: install %s', 'takopic'), $format['library']), sprintf($format['recommendation'], $format['engine']));
						?>
					<?php elseif (!$format['installed']) : ?>
						<?php
						/* translators: %s: a library, e.g. cwebp. */
						$this->render_install($id, sprintf(__('How to install %s', 'takopic'), $format['library']));
						?>
					<?php elseif ($format['upgrade']) : ?>
						<?php
						/* translators: %s: a library, e.g. cwebp. */
						$this->render_install($format['preferred'], sprintf(__('Recommended: install %s', 'takopic'), $format['preferred']), $format['preference']);
						?>
					<?php endif; ?>
					<details class="takopic-format__settings js-takopic-fold">
						<summary class="takopic-format__summary"><?php esc_html_e('Settings', 'takopic'); ?></summary>
						<div class="takopic-format__fields takopic-fields">
							<?php foreach ($fields as $name => $field) : ?>
								<?php $this->render_field($name, $field + Formats::availability($name)); ?>
							<?php endforeach; ?>
							<?php foreach ($format['extras'] as $name => $installed) : ?>
								<?php if ($format['installed'] && !$installed) : ?>
									<?php
									/* translators: 1: a library, e.g. pngquant, 2: what it is for, e.g. lossy compression. */
									$this->render_install($id, sprintf(__('How to install %1$s, for the %2$s', 'takopic'), $name, $format['uses'][$name]), '', true);
									?>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</details>
				</section>
			<?php endforeach; ?>
		</div>

		<div class="takopic-page__save takopic-save">
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
						$this->printf_html(__('Deletes the WebP and AVIF files of the %s images processed. The JPG and PNG files stay compressed, and the originals kept.', 'takopic'), '<strong class="js-takopic-count" data-count="processed">' . esc_html(number_format_i18n(count(Cleaner::ids()))) . '</strong>');
						?>
					</p>
				</div>
			</header>
			<?php if ($jobs) : ?>
				<div class="takopic-confirm">
					<label class="takopic-confirm__label"><input type="checkbox" class="takopic-confirm__input js-takopic-job-confirm" data-confirm-for="remove_formats"> <?php esc_html_e('I understand the WebP and AVIF files will be deleted, until the images are optimised again.', 'takopic'); ?></label>
					<button type="button" class="button takopic-confirm__button js-takopic-job-start" data-job="remove_formats" disabled><?php esc_html_e('Remove WebP and AVIF', 'takopic'); ?></button>
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
					<label class="takopic-confirm__label"><input type="checkbox" class="takopic-confirm__input js-takopic-job-confirm" data-confirm-for="restore"> <?php esc_html_e('I understand the images will no longer be optimised, and their WebP and AVIF files will be deleted.', 'takopic'); ?></label>
					<button type="button" class="button takopic-confirm__button takopic-button--danger js-takopic-job-start" data-job="restore" disabled><?php esc_html_e('Restore original images', 'takopic'); ?></button>
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
		<div class="takopic-job js-takopic-job" hidden>
			<div class="takopic-job__progress">
				<div class="takopic-job__head">
					<span class="takopic-job__icon dashicons dashicons-update" aria-hidden="true"></span>
					<p class="takopic-job__label js-takopic-job-label" aria-live="polite"></p>
					<span class="takopic-job__percent js-takopic-job-percent"></span>
					<div class="takopic-job__controls">
						<button type="button" class="button js-takopic-job-control" data-control="pause"><?php esc_html_e('Pause', 'takopic'); ?></button>
						<button type="button" class="button js-takopic-job-control" data-control="resume"><?php esc_html_e('Resume', 'takopic'); ?></button>
						<button type="button" class="button-link button-link-delete js-takopic-job-control" data-control="cancel"><?php esc_html_e('Cancel', 'takopic'); ?></button>
					</div>
				</div>
				<progress class="takopic-job__bar js-takopic-job-bar" max="100" value="0"></progress>
			</div>
			<div class="takopic-result js-takopic-job-result" role="status" aria-live="polite">
				<span class="takopic-confetti js-takopic-confetti" aria-hidden="true"></span>
				<span class="takopic-result__icon dashicons" aria-hidden="true"></span>
				<div class="takopic-result__body">
					<p class="takopic-result__title js-takopic-job-title"></p>
					<p class="takopic-result__detail">
						<span class="js-takopic-job-detail"></span>
						<span class="js-takopic-job-errors" hidden>
							<span class="js-takopic-job-errors-count"></span>
							<a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>"><?php esc_html_e('see the errors in the Media library', 'takopic'); ?></a>.
						</span>
						<span class="js-takopic-job-missing" hidden></span>
					</p>
				</div>
				<button type="button" class="takopic-result__close js-takopic-job-control" data-control="dismiss">
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
				<?php $this->render_code($code, 'takopic-code-theme'); ?>
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
	 * Renders a block of code, with its copy button
	 * (CopyButton.js, hidden without JS).
	 *
	 * @param string $code
	 * @param string $id Unique on the page.
	 * @since 1.0.0
	 */
	private function render_code($code, $id)
	{
		?>
		<div class="takopic-code-block">
			<pre class="takopic-code" id="<?php echo esc_attr($id); ?>"><code><?php echo esc_html($code); ?></code></pre>
			<button type="button" class="takopic-copy js-takopic-copy" aria-controls="<?php echo esc_attr($id); ?>"><?php esc_html_e('Copy', 'takopic'); ?></button>
		</div>
		<?php
	}

	/**
	 * Renders a command or an address on one line, with
	 * its copy button after it.
	 *
	 * @param string $command
	 * @param string $class Of the code.
	 * @since 1.0.0
	 */
	private function render_command($command, $class = '')
	{
		$id = 'takopic-command-' . sanitize_title($command);
		printf(
			'<code class="%1$s" id="%2$s">%3$s</code> <button type="button" class="takopic-copy js-takopic-copy" aria-controls="%2$s">%4$s</button>',
			esc_attr($class),
			esc_attr($id),
			esc_html($command),
			esc_html__('Copy', 'takopic')
		);
	}

	/**
	 * Renders a status badge.
	 *
	 * @param string $variant on, off or muted.
	 * @param string $label
	 * @param string $id To follow the settings and the jobs.
	 * @since 1.0.0
	 */
	private function render_badge($variant, $label, $id = '')
	{
		if ($id) {
			// It follows the settings and the jobs (Form.js).
			printf('<span class="takopic-badge takopic-badge--%s js-takopic-live" id="%s">%s</span>', esc_attr($variant), esc_attr($id), esc_html($label));
			return;
		}
		printf('<span class="takopic-badge takopic-badge--%s">%s</span>', esc_attr($variant), esc_html($label));
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
		$id = self::field_id($name);
		$input = Settings::OPTION . '[' . $name . ']';
		$value = Settings::get($name);
		$setting = Settings::FIELDS[$name];
		$tags = ['strong' => [], 'code' => []];
		$disabled = !empty($field['disabled']) ? ' disabled' : '';
		// A field shown while another one has a value, e.g. the lossy quality (DependentField.js).
		// Off as the page opens: hidden while the scripts load (fields.css), shown without JS.
		$when = '';
		$off = false;
		if (!empty($field['when'])) {
			$when = sprintf(' data-when="%s" data-when-value="%s"', esc_attr(Settings::OPTION . '[' . $field['when'][0] . ']'), esc_attr($field['when'][1]));
			$off = (string)Settings::get($field['when'][0]) !== (string)$field['when'][1];
		}

		// The label names the control without being a <label>: hovering it would hover the control.
		printf(
			'<div class="takopic-field%s%s%s%s"%s><span class="takopic-field__label" id="%s-label">%s</span>',
			$field['type'] === 'checkbox' ? ' takopic-field--toggle' : '',
			$disabled ? ' takopic-field--disabled' : '',
			$off ? ' takopic-field--off' : '',
			$when ? ' js-takopic-dependent' : '',
			$when, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr($id),
			esc_html($field['label'])
		);

		if ($field['type'] === 'checkbox') {
			$this->render_toggle($name);
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
		// A short note, the full help behind a "?" button (HelpToggle.js hides it, shown without JS).
		printf(
			'<p class="takopic-field__help">%s <button type="button" class="takopic-help__toggle js-takopic-help-toggle" aria-expanded="false" aria-controls="%s-help"><span aria-hidden="true">?</span><span class="screen-reader-text">%s</span></button></p><p class="takopic-field__help takopic-help__text" id="%s-help">%s</p></div>',
			wp_kses($field['note'], $tags), esc_attr($id), /* translators: %s: the label of a setting. */ esc_html(sprintf(__('More about: %s', 'takopic'), $field['label'])), esc_attr($id), wp_kses($field['help'], $tags)
		);
	}

	/**
	 * Renders the toggle of a checkbox option, named by
	 * the label of its field. With a label, a bare
	 * switch, for the header of a format card: its label
	 * is read by screen readers, its help shown apart.
	 * An unticked box is not sent: the hidden field
	 * sends 0 instead.
	 *
	 * @param string $name
	 * @param string $label
	 * @since 1.0.0
	 */
	private function render_toggle($name, $label = '')
	{
		$id = self::field_id($name);
		$input = Settings::OPTION . '[' . $name . ']';
		printf(
			'<input type="hidden" name="%1$s" value="0"><label class="takopic-toggle%2$s"><input type="checkbox" class="takopic-toggle__input" id="%3$s" name="%1$s" value="1"%4$s %5$s><span class="takopic-toggle__track" aria-hidden="true"></span>%6$s</label>',
			esc_attr($input),
			$label ? ' takopic-toggle--switch' : '',
			esc_attr($id),
			$label ? '' : sprintf(' aria-labelledby="%s-label"', esc_attr($id)), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here.
			checked(Settings::get($name), true, false),
			$label ? sprintf('<span class="screen-reader-text">%s</span>', esc_html($label)) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here.
		);
	}

	/**
	 * Returns the id of the control of an option, e.g.
	 * takopic-jpg-quality.
	 *
	 * @param string $name
	 * @return string
	 * @since 1.0.0
	 */
	private static function field_id($name)
	{
		return 'takopic-' . str_replace('_', '-', $name);
	}

	/**
	 * Returns the help of a number setting with its
	 * bounds and its default, from Settings::FIELDS.
	 *
	 * @param string $help With %1$d the minimum, %2$d the maximum, %3$d the default.
	 * @param string $name
	 * @return string
	 * @since 1.0.0
	 */
	private static function bounds($help, $name)
	{
		$setting = Settings::FIELDS[$name];
		return sprintf($help, $setting['min'], $setting['max'], $setting['default']);
	}

	/**
	 * Returns the optimisation levels of the PNG images,
	 * o0 to o7, by value.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	private static function levels()
	{
		$levels = [];
		foreach (range(Settings::FIELDS['png_level']['min'], Settings::FIELDS['png_level']['max']) as $level) {
			$levels[(string)$level] = 'o' . $level;
		}
		return $levels;
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
				'fields' => [
					'jpg_enable' => [
						'type' => 'checkbox',
						'label' => __('Compress JPG images', 'takopic'),
						'help' => __('Compresses the JPG images in place with jpegoptim, and makes them progressive. Their originals are kept.', 'takopic'),
					],
					'jpg_quality' => [
						'type' => 'number',
						'label' => __('Quality', 'takopic'),
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('From %1$d to %2$d, %3$d by default.', 'takopic'), 'jpg_quality'),
					],
				],
			],
			'png' => [
				// What the optional libraries are for, see AbstractEncoder::OPTIONAL.
				'uses' => ['pngquant' => __('lossy compression', 'takopic')],
				'preference' => __('optipng compresses the PNG images for now. Once installed, oxipng replaces it on its own: the files are lighter at the same level, and it is a little faster.', 'takopic'),
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
						'help' => __('Lossy: often 60 to 80 % lighter, for graphics and screenshots. Needs pngquant.', 'takopic'),
					],
					'png_level' => [
						'type' => 'select',
						'label' => __('Optimisation level', 'takopic'),
						'options' => self::levels(),
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('From o%1$d (fastest) to o%2$d (smallest files), o%3$d by default.', 'takopic'), 'png_level'),
					],
					'png_lossy_quality' => [
						'type' => 'number',
						'label' => __('Lossy quality', 'takopic'),
						'when' => ['png_compression', 'lossy'],
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('The quality pngquant aims at, %3$d by default.', 'takopic'), 'png_lossy_quality'),
					],
				],
			],
			'webp' => [
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
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('From %1$d to %2$d, %3$d by default.', 'takopic'), 'webp_quality'),
					],
				],
			],
			'avif' => [
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
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('From %1$d to %2$d (lossless), %3$d by default.', 'takopic'), 'avif_quality'),
					],
					'avif_speed' => [
						'type' => 'number',
						'label' => __('Speed', 'takopic'),
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('From %1$d to %2$d (fastest, heavier files), %3$d by default.', 'takopic'), 'avif_speed'),
					],
				],
			],
			'general' => [
				'title' => __('Uploads', 'takopic'),
				'description' => __('What happens to the images added to the media library.', 'takopic'),
				'fields' => [
					'optimise_uploads' => [
						'type' => 'checkbox',
						'label' => __('Optimise new uploads', 'takopic'),
						'help' => __('Each image added to the media library is optimised right away. Unticked, they wait in the Optimise tab, as new images.', 'takopic'),
					],
					'max_size' => [
						'type' => 'number',
						'label' => __('Maximum image size', 'takopic'),
						'unit' => 'px',
						/* translators: 1: the minimum, 2: the maximum, 3: the default. */
						'help' => self::bounds(__('WordPress scales the bigger images down on upload, and keeps them as their original. Longest side, %3$d by default. <strong>0 means no limit: huge images can exhaust the server.</strong>', 'takopic'), 'max_size'),
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
	 * Renders how to install a library, folded: a
	 * missing one, or a better one recommended (advice),
	 * with why, see templates/install.php.
	 *
	 * @param string $takopic_install The format of the library, or oxipng.
	 * @param string $summary
	 * @param string $text Why to install it, if recommended.
	 * @param bool $advice Recommended, the format working without it.
	 * @since 1.0.0
	 */
	private function render_install($takopic_install, $summary, $text = '', $advice = false)
	{
		?>
		<details class="takopic-install<?php echo $advice || $text ? ' takopic-install--advice' : ''; ?>">
			<summary class="takopic-install__summary"><?php echo esc_html($summary); ?></summary>
			<?php if ($text) : ?>
				<p class="takopic-install__text"><?php echo esc_html($text); ?></p>
			<?php endif; ?>
			<?php include TAKOPIC_TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'install.php'; ?>
		</details>
		<?php
	}
}
