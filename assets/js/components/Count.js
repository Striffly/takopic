/**
 * components/Count.js
 *
 * A count of images (.js-takopic-count), data-count
 * naming it: a status, new, total or processed. Kept
 * up to date while a job runs, rolling to its new
 * value (utils/Roll.js).
 */

(() => {
	const {Component, Roll, Screen} = window.takopic;

	class Count extends Component {
		vars() {
			super.vars();
			this.value = null;
			this.roll = null;
		}

		events() {
			super.events();
			this.addEvent({target: this.view, name: 'job:status', callback: ({detail}) => this.set(detail.counts[this.el.dataset.count])});
		}

		/**
		 * @param {number|undefined} value
		 */
		set(value) {
			if (value === undefined) {
				return;
			}
			// The first value is the one shown by the server.
			const from = this.value ?? value;
			this.value = value;
			this.roll?.stop();
			this.roll = new Roll(from, value, (v) => this.show(Math.round(v)));
		}

		/**
		 * @param {number} value
		 */
		show(value) {
			this.el.textContent = Screen.number(value);
		}

		destroy() {
			super.destroy();
			this.roll?.stop();
		}
	}

	window.takopic.Count = Count;
})();
