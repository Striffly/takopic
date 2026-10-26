/**
 * plugins.js
 *
 * Warning of the Takopic row of the plugins page,
 * before deactivating Takopic: its originals are not
 * restored if it is then deleted, and the theme may
 * call takopic_image().
 */

(function () {
	var settings = window.takopicPlugins;
	var row = settings && document.querySelector('tr[data-plugin="' + settings.plugin + '"]');
	if (!row) {
		return;
	}
	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;

	function dialog(title, html, buttons) {
		var element = document.createElement('dialog');
		element.className = 'takopic-dialog';
		element.innerHTML = '<h2></h2><div class="takopic-dialog__body"></div><div class="takopic-dialog__actions"></div>';
		element.querySelector('h2').textContent = title;
		element.querySelector('.takopic-dialog__body').innerHTML = html;
		buttons.forEach(function (button) {
			var tag = button.href ? 'a' : 'button';
			var action = document.createElement(tag);
			action.className = 'button' + (button.primary ? ' button-primary' : '');
			action.textContent = button.label;
			if (button.href) {
				action.href = button.href;
			} else {
				action.type = 'button';
				action.addEventListener('click', function () {
					element.close();
					if (button.onClick) {
						button.onClick();
					}
				});
			}
			element.querySelector('.takopic-dialog__actions').appendChild(action);
		});
		element.addEventListener('close', function () {
			element.remove();
		});
		document.body.appendChild(element);
		element.showModal();
	}

	// Shows a dialog instead of following a link, and follows it once confirmed.
	function intercept(link, show) {
		if (!link) {
			return;
		}
		var confirmed = false;
		link.addEventListener('click', function (event) {
			if (confirmed) {
				confirmed = false;
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			show(function () {
				confirmed = true;
				link.click();
			});
		}, true);
	}

	// WordPress only shows "Delete" for inactive plugins, when Takopic no longer runs:
	// deactivating is the last moment to warn about the originals.
	intercept(row.querySelector('.deactivate a'), function (proceed) {
		var body = new URLSearchParams({action: 'takopic_uninstall_info', _ajax_nonce: settings.nonce});
		fetch(settings.ajaxurl, {method: 'POST', credentials: 'same-origin', body: body})
			.then(function (response) {
				return response.json();
			})
			.then(function (response) {
				var info = response.success ? response.data : {images: 0, size: ''};
				if (!info.images && !settings.themeCalls) {
					proceed();
					return;
				}
				var html = '';
				var buttons = [];
				var restore = __('Restore original images', 'takopic');
				if (settings.themeCalls) {
					/* translators: %s: takopic_image() */
					var calls = '<strong>' + sprintf(__('Your theme calls %s', 'takopic'), '<code>takopic_image()</code>') + '</strong>';
					/* translators: %s: Your theme calls takopic_image() */
					html += '<p>' + sprintf(__('%s: the pages using it will break while Takopic is deactivated.', 'takopic'), calls) + '</p>';
				}
				if (info.images) {
					var folder = '<code>uploads/takopic-originals/</code>' + (info.size ? ' (' + info.size + ')' : '');
					/* translators: %s: the number of images. */
					var images = '<strong>' + sprintf(_n('%s image', '%s images', info.images, 'takopic'), Number(info.images).toLocaleString()) + '</strong>';
					/* translators: 1: the number of images, 2: the folder of the originals and its size. */
					html += '<p>' + sprintf(__('Takopic has optimised %1$s. Deactivating or deleting Takopic keeps them as they are: compressed, with their WebP and AVIF versions, and their originals in %2$s.', 'takopic'), images, folder) + '</p>';
					/* translators: %s: Restore original images */
					html += '<p>' + sprintf(__('If you are going to delete Takopic, run %s first to get your original images back and remove every Takopic file.', 'takopic'), '<strong>' + restore + '</strong>') + '</p>';
					buttons.push({label: restore, href: settings.restoreUrl, primary: true});
				}
				buttons.push({label: __('Deactivate anyway', 'takopic'), onClick: proceed});
				buttons.push({label: __('Cancel', 'takopic'), primary: !info.images});
				dialog(__('Before deactivating Takopic', 'takopic'), html, buttons);
			});
	});
})();
