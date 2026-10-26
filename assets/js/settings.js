/**
 * settings.js
 *
 * Starts the Takopic options page: a component on each
 * element with its js- class. The jobs and the test
 * are on the page only when the server can run them.
 */

(() => {
	const {
		Screen, Tabs, Form, HelpToggle, DependentField, CopyButton, Wallet,
		Count, Job, JobStart, Savings, Cta, AllOptimised, DeliveryTest,
	} = window.takopic;

	const componentList = [
		{name: 'js-takopic-tabs', Class: Tabs},
		{name: 'js-takopic-form', Class: Form},
		{name: 'js-takopic-help-toggle', Class: HelpToggle},
		{name: 'js-takopic-dependent', Class: DependentField},
		{name: 'js-takopic-copy', Class: CopyButton},
		{name: 'js-takopic-wallet', Class: Wallet},
		{name: 'js-takopic-count', Class: Count},
		{name: 'js-takopic-job', Class: Job},
		{name: 'js-takopic-job-start', Class: JobStart},
		{name: 'js-takopic-savings', Class: Savings},
		{name: 'js-takopic-cta', Class: Cta},
		{name: 'js-takopic-all-optimised', Class: AllOptimised},
		{name: 'js-takopic-delivery-test', Class: DeliveryTest},
	];

	const page = document.querySelector('.js-takopic-page');
	if (page) {
		// Without JS, the panels show one after another, without tabs.
		page.classList.add('takopic-page--js');
		new Screen(page, componentList, window.takopicSettings);
	}
})();
