/**
 * components/DeactivationWarning.js
 *
 * The dialog of the plugins screen
 * (.js-takopic-deactivation), shown instead of
 * following the Deactivate link of Takopic: its originals
 * are not restored if it is then deleted, and the
 * theme may call takopic_image(). WordPress only shows
 * "Delete" for inactive plugins, when Takopic no longer
 * runs: deactivating is the last moment to warn.
 * Deactivates at once when there is nothing to warn
 * about.
 */

(() => {
	const {_n, sprintf} = wp.i18n;
	const {Component, Screen} = window.takopic;

	class DeactivationWarning extends Component {
		vars() {
			super.vars();
			this.confirmed = false;
			this.themeCalls = this.el.dataset.themeCalls === 'true';
		}

		getElems() {
			super.getElems();
			// The row of Takopic, rendered by WordPress.
			this.link = document.querySelector(`tr[data-plugin="${this.el.dataset.plugin}"] .deactivate a`);
			this.originals = this.el.querySelector('.js-takopic-deactivation-originals');
			this.images = this.el.querySelector('.js-takopic-deactivation-images');
			this.size = this.el.querySelector('.js-takopic-deactivation-size');
			this.restore = this.el.querySelector('.js-takopic-deactivation-restore');
			this.proceedButton = this.el.querySelector('.js-takopic-deactivation-proceed');
			this.cancelButton = this.el.querySelector('.js-takopic-deactivation-cancel');
		}

		events() {
			super.events();
			// Before the handlers of WordPress.
			this.addEvent({target: this.link, name: 'click', callback: this.onDeactivate, capture: true});
			this.addEvent({target: this.proceedButton, name: 'click', callback: this.proceed});
			this.addEvent({target: this.cancelButton, name: 'click', callback: () => this.el.close()});
		}

		/**
		 * @param {MouseEvent} event
		 */
		onDeactivate(event) {
			if (this.confirmed) {
				this.confirmed = false;
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			this.warn();
		}

		async warn() {
			const response = await this.view.api.post('takopic_uninstall_info');
			const {images, size} = response.success ? response.data : {images: 0, size: ''};
			if (!images && !this.themeCalls) {
				this.proceed();
				return;
			}
			/* translators: %s: the number of images. */
			this.images.textContent = sprintf(_n('%s image', '%s images', images, 'takopic'), Screen.number(images));
			this.size.textContent = size ? ` (${size})` : '';
			this.originals.hidden = !images;
			this.restore.hidden = !images;
			// Without originals to restore, cancelling is the safe choice.
			this.cancelButton.classList.toggle('button-primary', !images);
			this.el.showModal();
		}

		proceed() {
			this.el.close();
			this.confirmed = true;
			this.link.click();
		}
	}

	window.takopic.DeactivationWarning = DeactivationWarning;
})();
