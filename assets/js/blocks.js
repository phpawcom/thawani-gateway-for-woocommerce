(function () {
	'use strict';

	var registry = window.wc && window.wc.wcBlocksRegistry;
	var settingsModule = window.wc && window.wc.wcSettings;
	var element = window.wp && window.wp.element;
	var htmlEntities = window.wp && window.wp.htmlEntities;

	if (!registry || !settingsModule || !element || !htmlEntities) {
		return;
	}

	var registerPaymentMethod = registry.registerPaymentMethod;
	var getSetting = settingsModule.getSetting;
	var createElement = element.createElement;
	var decodeEntities = htmlEntities.decodeEntities;

	var settings = getSetting('thawani_data', {});

	function Content() {
		return createElement('div', {
			dangerouslySetInnerHTML: { __html: decodeEntities(settings.description || '') }
		});
	}

	function Label() {
		return createElement('div', {
			dangerouslySetInnerHTML: { __html: decodeEntities(settings.title || '') }
		});
	}

	registerPaymentMethod({
		name: 'thawani',
		label: createElement(Label, null),
		content: createElement(Content, null),
		edit: createElement(Content, null),
		canMakePayment: function () { return true; },
		ariaLabel: decodeEntities(settings.title || ''),
		supports: {
			features: settings.supports || []
		}
	});
})();
