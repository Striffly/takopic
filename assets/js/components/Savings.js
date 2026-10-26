/**
 * components/Savings.js
 *
 * The bytes saved, at the top of the overview
 * (.js-takopic-savings), kept up to date while a job
 * runs. Formatted by the server, each in its
 * .js-takopic-savings-value, data-savings naming it.
 */

(() => {
	const {Component} = window.takopic;

	class Savings extends Component {
		getElems() {
			super.getElems();
			this.main = this.el.querySelector('.js-takopic-savings-main');
			this.empty = this.el.querySelector('.js-takopic-savings-empty');
			this.hint = this.el.querySelector('.js-takopic-savings-hint');
			this.values = this.el.querySelectorAll('.js-takopic-savings-value');
		}

		events() {
			super.events();
			this.addEvent({target: this.view, name: 'job:status', callback: ({detail}) => this.show(detail.savings)});
		}

		/**
		 * @param {?Object<string, string>} savings
		 */
		show(savings) {
			if (!savings) {
				return;
			}
			this.main.hidden = !savings.images;
			this.empty.hidden = Boolean(savings.images);
			this.hint.hidden = !savings.hint;
			this.values.forEach((value) => {
				value.textContent = savings[value.dataset.savings];
			});
		}
	}

	window.takopic.Savings = Savings;
})();
