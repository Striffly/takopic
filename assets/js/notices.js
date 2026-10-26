/**
 * notices.js
 *
 * Starts the error notice of Takopic on the screens of the
 * media library, once the uploader of WordPress is
 * ready with the page.
 */

(() => {
	const {Screen, ErrorsNotice} = window.takopic;

	const componentList = [
		{name: 'js-takopic-errors-notice-template', Class: ErrorsNotice},
	];

	document.addEventListener('DOMContentLoaded', () => {
		new Screen(document.body, componentList, window.takopicNotices);
	});
})();
