/**
 * components/Savings.js
 *
 * The bytes saved, at the top of the overview
 * (.js-takopic-savings), kept up to date while a job
 * runs. Formatted by the server, each in its
 * .js-takopic-savings-value, data-savings naming it.
 *
 * The first time the overview shows, the size saved
 * rolls up from 0 (utils/Roll.js). Before it paints,
 * the page shows 0 in its data-value, over the size
 * itself, kept invisible to hold its place: the text
 * after it stays still, whatever the font
 * (overview.css).
 */

(() => {
	const {Component, Roll, Screen} = window.takopic;

	class Savings extends Component {
		vars() {
			super.vars();
			this.roll = null;
		}

		getElems() {
			super.getElems();
			this.main = this.el.querySelector('.js-takopic-savings-main');
			this.empty = this.el.querySelector('.js-takopic-savings-empty');
			this.hint = this.el.querySelector('.js-takopic-savings-hint');
			this.values = this.el.querySelectorAll('.js-takopic-savings-value');
			this.saved = this.el.querySelector('.js-takopic-savings-value[data-savings="saved"]');
		}

		events() {
			super.events();
			this.addEvent({target: this.view, name: 'job:status', callback: ({detail}) => this.show(detail.savings)});
			this.addEvent({target: this.view, name: 'tab:shown', callback: ({detail}) => detail === 'overview' && this.rollUp()});
		}

		/**
		 * @param {?Object<string, string>} savings
		 */
		show(savings) {
			if (!savings) {
				return;
			}
			// The status of the job comes right after the page opens: the same size keeps rolling.
			if (this.roll && savings.saved !== this.saved.textContent) {
				this.stop();
			}
			this.main.hidden = !savings.images;
			this.empty.hidden = Boolean(savings.images);
			this.hint.hidden = !savings.hint;
			this.values.forEach((value) => {
				value.textContent = savings[value.dataset.savings];
			});
		}

		/**
		 * Rolls the size saved up from 0, the first time the
		 * overview shows, e.g. to "40.6 MB" from "0.0 MB".
		 */
		rollUp() {
			if (!this.saved || !('value' in this.saved.dataset) || this.roll) {
				return;
			}
			const text = this.saved.textContent;
			const parts = text.match(/^(\D*)(\d(?:[\d.,\s]*\d)?)(.*)$/s);
			if (this.main.hidden || !parts) {
				this.stop();
				return;
			}
			const [, before, number, after] = parts;
			// One decimal from the kilobytes on (see Stats::size()); the other separators group the thousands.
			const decimal = number.match(/([.,])\d$/)?.[1];
			const value = Number(number.replace(/\D/g, '')) / (decimal ? 10 : 1);
			// The thousands separated as the server writes them, e.g. "1 023,5 Mo" in French.
			const format = (v) => {
				if (!decimal) {
					return before + Screen.number(v) + after;
				}
				const [whole, tenth] = v.toFixed(1).split('.');
				return before + Screen.number(Number(whole)) + decimal + tenth + after;
			};

			this.roll = new Roll(0, value, (v) => {
				if (v === value) {
					this.stop();
				} else {
					this.saved.dataset.value = format(v);
				}
			});
		}

		/**
		 * Stops rolling: the size itself shows.
		 */
		stop() {
			this.roll?.stop();
			// Not null: the size has rolled, it does not roll again.
			this.roll = false;
			this.saved?.classList.remove('takopic-savings__rolling');
			delete this.saved?.dataset.value;
		}

		destroy() {
			super.destroy();
			this.stop();
		}
	}

	window.takopic.Savings = Savings;
})();
