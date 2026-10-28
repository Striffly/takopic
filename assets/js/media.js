/**
 * media.js
 *
 * Starts the actions of Takopic in the media library, list
 * mode: on its body, marked by Takopic (MediaColumn).
 */

(() => {
	const {Screen, MediaQueue} = window.takopic;

	const componentList = [
		{name: 'js-takopic-media', Class: MediaQueue},
	];

	document.addEventListener('DOMContentLoaded', () => {
		new Screen(document.documentElement, componentList, window.takopicMedia);
	});
})();
