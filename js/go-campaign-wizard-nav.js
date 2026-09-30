/**
 * Campaign add wizard navigation (loaded early so Next/Prev/Finish always work).
 */
(function (window, document) {
	'use strict';

	function goHideTelephonyFab() {
		var menus = document.querySelectorAll('.bottom-menu');
		var i;
		for (i = 0; i < menus.length; i++) {
			menus[i].setAttribute('data-go-fab-prev-display', menus[i].style.display || '');
			menus[i].style.display = 'none';
		}
	}

	function goShowTelephonyFab() {
		var menus = document.querySelectorAll('.bottom-menu');
		var i;
		for (i = 0; i < menus.length; i++) {
			var prev = menus[i].getAttribute('data-go-fab-prev-display');
			menus[i].style.display = prev || '';
			menus[i].removeAttribute('data-go-fab-prev-display');
		}
	}

	window.goHideTelephonyFab = goHideTelephonyFab;
	window.goShowTelephonyFab = goShowTelephonyFab;

	window.goShowAddCampaignModal = function () {
		var modal = document.getElementById('add_campaign');
		if (!modal) {
			window.alert('Campaign wizard did not load. Refresh the page or check server PHP errors.');
			return false;
		}
		var showModal = function () {
			goHideTelephonyFab();
			if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
				window.jQuery(modal).modal('show');
				window.setTimeout(function () {
					var nav = document.getElementById('go_campaign_wizard_footer');
					if (nav && nav.scrollIntoView) {
						nav.scrollIntoView({ block: 'nearest' });
					}
					if (typeof window.goPrepareAddCampaignWizard === 'function') {
						window.goPrepareAddCampaignWizard();
					} else if (typeof window.goCampaignWizardSetStep === 'function') {
						window.goCampaignWizardSetStep(0);
					}
				}, 350);
			} else {
				modal.style.display = 'block';
				modal.classList.add('in');
				document.body.classList.add('modal-open');
			}
		};
		if (window.jQuery) {
			showModal();
		} else {
			var tries = 0;
			var timer = window.setInterval(function () {
				tries++;
				if (window.jQuery || tries > 40) {
					window.clearInterval(timer);
					showModal();
				}
			}, 100);
		}
		return false;
	};

	window.goSafeJsonParse = window.goSafeJsonParse || function (input, fallback) {
		if (typeof fallback === 'undefined') {
			fallback = null;
		}
		if (input === undefined || input === null) {
			return fallback;
		}
		if (typeof input === 'object') {
			return input;
		}
		var text = String(input).trim();
		if (!text) {
			return fallback;
		}
		try {
			return JSON.parse(text);
		} catch (e) {
			return fallback;
		}
	};

	window.goCampaignWizardGetStep = function () {
		var modal = document.getElementById('add_campaign');
		if (!modal) {
			return 0;
		}
		var step = parseInt(modal.getAttribute('data-wizard-step') || '0', 10);
		return isNaN(step) ? 0 : step;
	};

	window.goRefreshCampaignWizardStep2Fields = function () {
		var stepEl = document.getElementById('go_campaign_wizard_step_1');
		if (!stepEl) {
			return;
		}
		var typeEl = document.getElementById('campaignType');
		var type = typeEl ? typeEl.value : 'outbound';
		var fieldset = stepEl.querySelector('fieldset');
		if (!fieldset) {
			return;
		}
		var groups = fieldset.querySelectorAll('.form-group');
		var i;
		for (i = 0; i < groups.length; i++) {
			groups[i].classList.add('hide');
		}
		var dialRow = fieldset.querySelector('.dial-method-row');
		var autoDial = fieldset.querySelector('.auto-dial-level');
		var outboundFields = fieldset.querySelectorAll('.outbound');
		var surveyFields = fieldset.querySelectorAll('.survey');
		if (type === 'survey') {
			for (i = 0; i < surveyFields.length; i++) {
				surveyFields[i].classList.remove('hide');
			}
		} else {
			if (dialRow) {
				dialRow.classList.remove('hide');
			}
			if (autoDial) {
				autoDial.classList.remove('hide');
			}
			if (type === 'outbound' || type === 'blended') {
				for (i = 0; i < outboundFields.length; i++) {
					outboundFields[i].classList.remove('hide');
				}
			}
		}
		var dialSelect = document.getElementById('dial-method');
		if (dialSelect && typeof window.dialMethod === 'function') {
			window.dialMethod(dialSelect.value);
		} else if (dialSelect && typeof dialMethod === 'function') {
			dialMethod(dialSelect.value);
		}
	};

	window.goCampaignWizardSetStep = function (step) {
		var modal = document.getElementById('add_campaign');
		if (!modal) {
			return;
		}
		if (step < 0) {
			step = 0;
		}
		if (step > 1) {
			step = 1;
		}
		modal.setAttribute('data-wizard-step', String(step));
		window.goManualStepIndex = step;
		var tabHref = step === 0 ? '#go_campaign_wizard_step_0' : '#go_campaign_wizard_step_1';
		var tabId = tabHref.substring(1);
		var panes = modal.querySelectorAll('.go-campaign-wizard-tab-content > .tab-pane');
		var i;
		for (i = 0; i < panes.length; i++) {
			if (panes[i].id === tabId) {
				panes[i].classList.add('active');
			} else {
				panes[i].classList.remove('active');
			}
		}
		var tabItems = modal.querySelectorAll('#go_campaign_wizard_tabs > li');
		for (i = 0; i < tabItems.length; i++) {
			tabItems[i].classList.remove('active');
		}
		var tabLink = modal.querySelector('#go_campaign_wizard_tabs a[href="' + tabHref + '"]');
		if (tabLink && tabLink.parentElement) {
			tabLink.parentElement.classList.add('active');
		}
		if (window.jQuery && window.jQuery.fn && window.jQuery.fn.tab) {
			window.jQuery('#go_campaign_wizard_tabs a[href="' + tabHref + '"]').tab('show');
		}
		if (step === 1) {
			window.setTimeout(function () {
				if (typeof window.goRefreshCampaignWizardStep2Fields === 'function') {
					window.goRefreshCampaignWizardStep2Fields();
				}
			}, 0);
		}
	};

	window.goCampaignWizardClickNext = function (ev) {
		if (ev && ev.preventDefault) {
			ev.preventDefault();
		}
		if (ev && ev.stopPropagation) {
			ev.stopPropagation();
		}
		if (typeof window.goWizardValidateStep === 'function' && !window.goWizardValidateStep()) {
			return false;
		}
		window.goCampaignWizardSetStep(1);
		return false;
	};

	window.goCampaignWizardClickPrev = function (ev) {
		if (ev && ev.preventDefault) {
			ev.preventDefault();
		}
		if (ev && ev.stopPropagation) {
			ev.stopPropagation();
		}
		window.goCampaignWizardSetStep(0);
		return false;
	};

	window.goCampaignWizardClickFinish = function (ev) {
		if (ev && ev.preventDefault) {
			ev.preventDefault();
		}
		if (typeof window.goWizardFinish === 'function') {
			window.goWizardFinish();
		} else {
			window.alert('Please wait for the page to finish loading, then try Finish again.');
		}
		return false;
	};

	function closestEl(el, selector) {
		if (!el) {
			return null;
		}
		if (el.closest) {
			return el.closest(selector);
		}
		while (el && el.nodeType === 1) {
			if (el.matches && el.matches(selector)) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		var trigger = closestEl(target, '.btn-add-campaign, [data-go-open-campaign-modal], .fab-div-item[data-target="#add_campaign"]');
		if (trigger) {
			event.preventDefault();
			window.goShowAddCampaignModal();
			return;
		}
		var modal = document.getElementById('add_campaign');
		if (!modal || !closestEl(target, '#add_campaign')) {
			return;
		}
		if (closestEl(target, '.go-wizard-next-btn')) {
			window.goCampaignWizardClickNext(event);
			return;
		}
		if (closestEl(target, '.go-wizard-prev-btn')) {
			window.goCampaignWizardClickPrev(event);
			return;
		}
		if (closestEl(target, '.go-wizard-finish-btn')) {
			window.goCampaignWizardClickFinish(event);
		}
	}, true);
})(window, document);
