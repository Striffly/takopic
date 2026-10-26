/**
 * plugins.js
 *
 * Starts the warning of the plugins screen, before
 * Takopic is deactivated.
 */

(() => {
	const {Screen, DeactivationWarning} = window.takopic;

	const componentList = [
		{name: 'js-takopic-deactivation', Class: DeactivationWarning},
	];

	new Screen(document.body, componentList, window.takopicPlugins);
})();
