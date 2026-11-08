/**
 * views/Screen.js
 *
 * A screen of the admin: builds a component on each
 * element with its js- class, then starts them. The
 * components talk through its events, e.g. tab:shown,
 * and share its API and its announcer for screen
 * readers.
 */

(() => {
	const {Api} = window.takopic;

	class Screen extends EventTarget {
		/**
		 * The thousands separator of WordPress, see Ajax::localize().
		 */
		static #thousands = ',';

		/**
		 * @param {HTMLElement} el
		 * @param {Array<{name: string, Class: Function}>} components A js- class, and the component built on its elements.
		 * @param {{ajaxurl: string, nonce: string, thousands: string}} settings
		 */
		constructor(el, components, settings) {
			super();
			Screen.#thousands = settings.thousands ?? ',';
			this.el = el;
			this.settings = settings;
			this.api = new Api(settings);
			this.announcer = el.querySelector('.js-takopic-announcer');
			this.components = components.flatMap(({name, Class}) => [...el.querySelectorAll(`.${name}`)].map((element) => new Class(element, this)));
			this.components.forEach((component) => component.init());
		}

		/**
		 * Formats a whole number as WordPress does
		 * (number_format_i18n()), e.g. "1 044" in French:
		 * as the numbers the server writes, whatever the
		 * language of the browser.
		 *
		 * @param {number} value
		 * @returns {string}
		 */
		static number(value) {
			const digits = String(Math.abs(Math.round(value))).replace(/\B(?=(\d{3})+(?!\d))/g, Screen.#thousands);
			return (value < 0 ? '-' : '') + digits;
		}

		/**
		 * @param {string} name
		 * @param {*} detail
		 */
		emit(name, detail = null) {
			this.dispatchEvent(new CustomEvent(name, {detail}));
		}

		/**
		 * Tells screen readers.
		 *
		 * @param {string} text
		 */
		announce(text) {
			if (this.announcer) {
				this.announcer.textContent = text;
			}
		}
	}

	(window.takopic ??= {}).Screen = Screen;
})();
