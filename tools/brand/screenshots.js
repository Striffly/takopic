/**
 * tools/brand/screenshots.js
 *
 * Captures the screenshots of WordPress.org on a test site,
 * in the order of readme.txt: each screen of the admin,
 * 1408x880 at 2x, rounded and shadowed on the night of the
 * header of the page, written to .wordpress-org/screenshot-1.png
 * and on.
 *
 * The site has Takopic active, its media library optimised,
 * and the WebP and AVIF served by the rewrite rules. The
 * second screen optimises every image again.
 *
 * bun run screenshots -- --url=http://localhost:8086 --user=bench --password=bench
 *
 * --chrome sets the browser, chromium by default.
 */

import {writeFileSync} from 'node:fs';
import {parseArgs} from 'node:util';
import {createCanvas, loadImage} from '@napi-rs/canvas';
import puppeteer from 'puppeteer-core';

// Of the page, where the functions given to evaluate() and waitForFunction() run.
/* global document, scrollTo */

const WORDPRESS_ORG = new URL('../../.wordpress-org/', import.meta.url);
const WIDTH = 2400, HEIGHT = 1560, SHOT = 2160, RADIUS = 28;
const VIEWPORT = {width: 1408, height: 880, deviceScaleFactor: 2};
const SETTINGS = '/wp-admin/options-general.php?page=takopic';

const {values: options} = parseArgs({
	options: {
		url: {type: 'string', default: 'http://localhost:8086'},
		user: {type: 'string', default: 'bench'},
		password: {type: 'string', default: 'bench'},
		chrome: {type: 'string', default: 'chromium'},
	},
});

/**
 * The screens, each with what to do on it before its capture.
 *
 * @type {{path: string, before?: function(import('puppeteer-core').Page): Promise<void>}[]}
 */
const SCREENS = [
	// The overview: the bytes saved, the status of the images and the libraries of each format.
	{path: `${SETTINGS}#overview`},
	// The optimisation of the media library in the background, half way.
	{
		path: `${SETTINGS}#optimise`,
		async before(page) {
			await page.click('.js-takopic-job-start[data-job="all"]');
			await page.waitForFunction(() => {
				const percent = document.querySelector('.js-takopic-job')?.textContent.match(/(\d+)\s*%/);
				return percent && +percent[1] >= 50;
			}, {polling: 200, timeout: 0});
		},
	},
	// The delivery of WebP and AVIF on the site, and its test. Once the job is done, so that it is not shown.
	{
		path: `${SETTINGS}#delivery`,
		async before(page) {
			await page.waitForFunction(() => !['running', 'pausing', 'cancelling'].includes(document.querySelector('.js-takopic-job')?.dataset.state), {polling: 500, timeout: 0});
			await page.reload();
			await settle(page);
			await page.click('.js-takopic-delivery-test');
			await page.waitForSelector('.js-takopic-test-summary:not([hidden])');
		},
	},
	// The settings of each format, the first opened.
	{
		path: `${SETTINGS}#settings`,
		async before(page) {
			await page.$eval('[data-panel="settings"] details', (details) => {
				details.open = true;
			});
			// The arrow of the fold turns.
			await new Promise((resolve) => setTimeout(resolve, 400));
		},
	},
	// The status and the bytes saved of each image, in the media library.
	{path: '/wp-admin/upload.php?mode=list'},
];

/**
 * Waits for the screen to be drawn: the counts filled, no scrollbar.
 *
 * @param {import('puppeteer-core').Page} page
 */
async function settle(page) {
	await page.evaluate(() => {
		document.documentElement.style.scrollbarWidth = 'none';
	});
	await new Promise((resolve) => setTimeout(resolve, 1800));
}

/**
 * @param {Buffer} png A capture.
 * @return {Promise<Buffer>} The dressed PNG.
 */
async function dress(png) {
	const capture = await loadImage(png);
	const canvas = createCanvas(WIDTH, HEIGHT);
	const ctx = canvas.getContext('2d');

	// The night of the header (tokens.css), at 120deg, and a pink glow from the top right corner.
	const night = ctx.createLinearGradient(0, 0, WIDTH, WIDTH * 0.577);
	night.addColorStop(0, '#1b0b3b');
	night.addColorStop(0.55, '#2a0e4f');
	night.addColorStop(1, '#3d1170');
	ctx.fillStyle = night;
	ctx.fillRect(0, 0, WIDTH, HEIGHT);
	const glow = ctx.createRadialGradient(WIDTH, 0, 0, WIDTH, 0, WIDTH * 0.7);
	glow.addColorStop(0, 'rgb(255 60 172 / 28%)');
	glow.addColorStop(0.45, 'rgb(123 47 247 / 12%)');
	glow.addColorStop(1, 'rgb(123 47 247 / 0%)');
	ctx.fillStyle = glow;
	ctx.fillRect(0, 0, WIDTH, HEIGHT);

	// The capture, centred, rounded, on its shadow.
	const height = Math.round((capture.height / capture.width) * SHOT);
	const x = (WIDTH - SHOT) / 2, y = Math.round((HEIGHT - height) / 2);
	ctx.save();
	ctx.shadowColor = 'rgb(8 3 20 / 55%)';
	ctx.shadowBlur = 60;
	ctx.shadowOffsetY = 24;
	ctx.fillStyle = '#000';
	ctx.beginPath();
	ctx.roundRect(x, y, SHOT, height, RADIUS);
	ctx.fill();
	ctx.restore();
	ctx.save();
	ctx.beginPath();
	ctx.roundRect(x, y, SHOT, height, RADIUS);
	ctx.clip();
	ctx.drawImage(capture, x, y, SHOT, height);
	ctx.restore();
	ctx.strokeStyle = 'rgb(255 255 255 / 14%)';
	ctx.lineWidth = 2;
	ctx.beginPath();
	ctx.roundRect(x + 1, y + 1, SHOT - 2, height - 2, RADIUS - 1);
	ctx.stroke();

	return canvas.encode('png');
}

const browser = await puppeteer.launch({
	executablePath: Bun.which(options.chrome) ?? options.chrome,
	defaultViewport: VIEWPORT,
	args: ['--hide-scrollbars'],
	// The job of the second screen may take minutes.
	protocolTimeout: 30 * 60 * 1000,
});
try {
	const page = await browser.newPage();
	await page.emulateMediaFeatures([{name: 'prefers-reduced-motion', value: 'reduce'}]);
	// The confirmation of the job of every image.
	page.on('dialog', (dialog) => dialog.accept());

	await page.goto(`${options.url}/wp-login.php`);
	await page.type('#user_login', options.user);
	await page.type('#user_pass', options.password);
	await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

	for (const [i, {path, before}] of SCREENS.entries()) {
		// A query of its own, so that the page loads again when only the tab changes.
		const url = new URL(path, options.url);
		url.searchParams.set('screen', i + 1);
		await page.goto(url.href);
		await settle(page);
		await before?.(page);
		await page.evaluate(() => scrollTo(0, 0));
		const name = `screenshot-${i + 1}.png`;
		writeFileSync(new URL(name, WORDPRESS_ORG), await dress(await page.screenshot()));
		console.log(`.wordpress-org/${name}`);
	}
} finally {
	await browser.close();
}
