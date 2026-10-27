/**
 * components/Cta.js
 *
 * The button to optimise, on the overview
 * (.js-takopic-cta): filled with the gradient, and
 * pulsing, while new images wait for their
 * optimisation. Not for the ones optimised with older
 * settings: they are already light.
 */

(() => {
	const {Component} = window.takopic;

	class Cta extends Component {
		events() {
			super.events();
			this.addEvent({target: this.view, name: 'job:status', callback: ({detail}) => this.update(detail.counts)});
		}

		/**
		 * @param {Object<string, number>} counts
		 */
		update(counts) {
			this.el.classList.toggle('takopic-cta--filled', counts['to-optimise'] > 0);
		}
	}

	window.takopic.Cta = Cta;
})();
