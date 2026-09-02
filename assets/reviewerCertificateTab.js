// SPDX-License-Identifier: GPL-3.0-or-later
(function () {
	'use strict';

	if (window.reviewerCertificateTabLoaded) {
		return;
	}
	window.reviewerCertificateTabLoaded = true;
	window.reviewerCertificateTabStatus = {
		version: '1.8.0',
		loaded: true,
		installed: false,
		panelLoaded: false
	};

	var state = {
		certificateUrl: '',
		tab: null,
		queueTab: null,
		archiveTab: null,
		tabsRoot: null,
		panel: null,
		panelTheme: null,
		cardTheme: null,
		certificateItems: [],
		currentPage: 1,
		pageSize: 30,
		searchQuery: '',
		hiddenPanels: []
	};

	function normalizeText(node) {
		return (node && node.textContent ? node.textContent : '')
			.replace(/\s+/g, ' ')
			.trim()
			.toLowerCase();
	}

	function escapeHtml(value) {
		var holder = document.createElement('div');
		holder.textContent = value || '';
		return holder.innerHTML;
	}

	function getCertificateUrl() {
		var match = window.location.pathname.match(
			/^(.*\/index\.php\/[^/]+)\/submissions(?:\/|$)/
		);
		return match ? match[1] + '/reviewercertificate' : '';
	}

	function findTab(pattern) {
		var nodes = document.querySelectorAll(
			'button, a, [role="tab"], [class*="pkpTabs"], [class*="tabs"], span, div'
		);
		var bestMatch = null;
		var bestLength = Infinity;
		for (var i = 0; i < nodes.length; i++) {
			var text = normalizeText(nodes[i]);
			if (pattern.test(text) && text.length < bestLength) {
				bestMatch = nodes[i];
				bestLength = text.length;
			}
		}
		return bestMatch;
	}

	function findCommonContainer(first, second) {
		var parent = first ? first.parentElement : null;
		var depth = 0;
		while (parent && parent !== document.body && depth < 8) {
			if (parent.contains(second)) {
				return parent;
			}
			parent = parent.parentElement;
			depth++;
		}
		return null;
	}

	function getDirectChild(container, node) {
		var child = node;
		while (child && child.parentElement !== container) {
			child = child.parentElement;
		}
		return child;
	}

	function findTabsRoot(tabContainer) {
		var parent = tabContainer;
		var depth = 0;
		while (parent && parent !== document.body && depth < 7) {
			if (parent.querySelector(
				'[role="tabpanel"], [class*="pkpTabs__panel"], [class*="pkpTabs__content"]'
			)) {
				return parent;
			}
			parent = parent.parentElement;
			depth++;
		}
		return tabContainer.parentElement;
	}

	function addStyles() {
		if (document.getElementById('rc-cert-styles')) {
			return;
		}
		var style = document.createElement('style');
		style.id = 'rc-cert-styles';
		style.textContent =
			'#rc-cert-tab.rc-cert-active{border-top-color:#00b2e3;color:#007ab2;background:#fff;}' +
			'#rc-cert-panel{background:#fff;border:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12));border-left:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12))!important;border-right:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12))!important;padding:42px;min-height:260px;font-family:inherit;font-size:inherit;color:inherit;}' +
			'#rc-cert-panel .rc-card{overflow:hidden;background:inherit;border:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12));border-left:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12))!important;border-right:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12))!important;border-radius:2px;}' +
			'#rc-cert-panel .rc-toolbar{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:12px 20px;border-bottom:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12));}' +
			'#rc-cert-panel .rc-heading{margin:0;font-family:inherit;font-size:1.125em;font-weight:700;line-height:1.35;color:inherit;}' +
			'#rc-cert-panel .rc-search{display:flex;align-items:stretch;width:min(430px,48%);background:inherit;border:1px solid var(--rc-panel-border-color,rgba(0,0,0,.16));}' +
			'#rc-cert-panel .rc-search-icon{display:flex;align-items:center;justify-content:center;width:42px;color:var(--rc-accent-color,#007ab2);font-size:1.05em;}' +
			'#rc-cert-panel .rc-search-input{min-width:0;width:100%;padding:9px 12px;border:0;border-left:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12));outline:0;background:transparent;color:inherit;font:inherit;}' +
			'#rc-cert-panel .rc-search-input:focus{box-shadow:inset 0 0 0 1px var(--rc-accent-color,#007ab2);}' +
			'#rc-cert-panel .rc-content{padding:0 20px;}' +
			'#rc-cert-panel .rc-loading,#rc-cert-panel .rc-empty,#rc-cert-panel .rc-error{padding:24px 0;color:inherit;opacity:.7;}' +
			'#rc-cert-panel .rc-error{color:#b33;}' +
			'#rc-cert-panel .rc-item{display:flex;align-items:center;gap:16px;padding:14px 0;border-top:1px solid var(--rc-panel-border-color,rgba(0,0,0,.12));}' +
			'#rc-cert-panel .rc-item:first-child{border-top:0;}' +
			'#rc-cert-panel .rc-copy{flex:1;min-width:0;}' +
			'#rc-cert-panel .rc-title{margin:0 0 4px;font-family:inherit;font-size:.95em;font-weight:600;line-height:1.4;color:inherit;}' +
			'#rc-cert-panel .rc-meta{font-family:inherit;font-size:.8125em;line-height:1.4;color:inherit;opacity:.65;}' +
			'#rc-cert-panel .rc-action{font-family:inherit;white-space:nowrap;}' +
			'#rc-cert-panel .rc-pagination{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:16px;padding:18px 0;}' +
			'#rc-cert-panel .rc-pagination-pages{display:flex;align-items:center;justify-content:center;gap:4px;}' +
			'#rc-cert-panel .rc-page-next{justify-self:end;}' +
			'#rc-cert-panel .rc-page-button{min-width:36px;padding:8px 11px;border:0;background:transparent;color:var(--rc-accent-color,#007ab2);font:inherit;font-weight:600;cursor:pointer;}' +
			'#rc-cert-panel .rc-page-button[aria-current="page"]{background:var(--rc-accent-color,#007ab2);color:#fff;}' +
			'#rc-cert-panel .rc-page-ellipsis{padding:0 5px;color:inherit;opacity:.65;}' +
			'#rc-cert-panel .rc-pagination .pkp_button:disabled{cursor:default;opacity:.5;}' +
			'@media(max-width:700px){#rc-cert-panel{padding:20px;}#rc-cert-panel .rc-toolbar{align-items:stretch;flex-direction:column;padding:16px;}#rc-cert-panel .rc-search{width:100%;}#rc-cert-panel .rc-content{padding:0 16px;}#rc-cert-panel .rc-item{align-items:flex-start;flex-direction:column;gap:10px;}#rc-cert-panel .rc-pagination{grid-template-columns:1fr 1fr;}#rc-cert-panel .rc-pagination-pages{grid-column:1/-1;grid-row:1;}#rc-cert-panel .rc-page-next{justify-self:end;}}';
		document.head.appendChild(style);
	}

	function getOriginalPanels() {
		var candidates = state.tabsRoot.querySelectorAll(
			'[role="tabpanel"], [class*="pkpTabs__panel"]'
		);
		var panels = [];
		for (var i = 0; i < candidates.length; i++) {
			if (candidates[i] !== state.panel && !candidates[i].contains(state.panel)) {
				panels.push(candidates[i]);
			}
		}
		if (!panels.length && state.tab && state.tab.parentElement) {
			var tabBar = state.tab.parentElement.parentElement;
			var sibling = tabBar ? tabBar.nextElementSibling : null;
			if (sibling && sibling !== state.panel) {
				panels.push(sibling);
			}
		}
		return panels;
	}

	function isTransparentColor(color) {
		return !color || color === 'transparent' ||
			color === 'rgba(0, 0, 0, 0)' || color === 'rgba(0,0,0,0)';
	}

	function findNativeListFrame(source) {
		var sourceRect = source.getBoundingClientRect();
		var nodes = source.querySelectorAll('section, div, [class*="pkpListPanel"]');
		var best = null;
		var bestArea = 0;
		for (var i = 0; i < nodes.length; i++) {
			var rect = nodes[i].getBoundingClientRect();
			var style = window.getComputedStyle(nodes[i]);
			var borderWidth = parseFloat(style.borderTopWidth) +
				parseFloat(style.borderRightWidth) +
				parseFloat(style.borderBottomWidth) +
				parseFloat(style.borderLeftWidth);
			var area = rect.width * rect.height;
			if (borderWidth > 0 && rect.width >= sourceRect.width * 0.7 &&
				rect.height > 100 && area > bestArea) {
				best = nodes[i];
				bestArea = area;
			}
		}
		return best;
	}

	function capturePanelTheme(panels) {
		var source = null;
		for (var i = 0; i < panels.length; i++) {
			var candidateStyle = window.getComputedStyle(panels[i]);
			if (candidateStyle.display !== 'none' && candidateStyle.visibility !== 'hidden') {
				source = panels[i];
				break;
			}
		}
		if (!source && panels.length) {
			source = panels[0];
		}
		if (!source) {
			return;
		}

		var sourceStyle = window.getComputedStyle(source);
		var backgroundColor = sourceStyle.backgroundColor;
		var backgroundImage = sourceStyle.backgroundImage;
		var backgroundNode = source.parentElement;
		while (backgroundNode && isTransparentColor(backgroundColor)) {
			var backgroundStyle = window.getComputedStyle(backgroundNode);
			backgroundColor = backgroundStyle.backgroundColor;
			if (backgroundImage === 'none') {
				backgroundImage = backgroundStyle.backgroundImage;
			}
			backgroundNode = backgroundNode.parentElement;
		}

		state.panelTheme = {
			backgroundColor: isTransparentColor(backgroundColor) ? '#fff' : backgroundColor,
			backgroundImage: backgroundImage,
			color: sourceStyle.color,
			borderTop: sourceStyle.borderTop,
			borderRight: sourceStyle.borderRight,
			borderBottom: sourceStyle.borderBottom,
			borderLeft: sourceStyle.borderLeft,
			borderRadius: sourceStyle.borderRadius,
			boxShadow: sourceStyle.boxShadow,
			borderColor: sourceStyle.borderTopColor,
			accentColor: window.getComputedStyle(state.archiveTab || state.queueTab).color
		};

		var listFrame = findNativeListFrame(source);
		if (listFrame) {
			var frameStyle = window.getComputedStyle(listFrame);
			state.cardTheme = {
				backgroundColor: isTransparentColor(frameStyle.backgroundColor)
					? state.panelTheme.backgroundColor : frameStyle.backgroundColor,
				backgroundImage: frameStyle.backgroundImage,
				borderTop: frameStyle.borderTop,
				borderRight: frameStyle.borderRight,
				borderBottom: frameStyle.borderBottom,
				borderLeft: frameStyle.borderLeft,
				borderRadius: frameStyle.borderRadius,
				boxShadow: frameStyle.boxShadow
			};
			if (!isTransparentColor(frameStyle.borderTopColor)) {
				state.panelTheme.borderColor = frameStyle.borderTopColor;
			}
		}
	}

	function applyPanelTheme(panel) {
		if (!state.panelTheme) {
			return;
		}
		panel.style.backgroundColor = state.panelTheme.backgroundColor;
		panel.style.backgroundImage = state.panelTheme.backgroundImage;
		panel.style.color = state.panelTheme.color;
		panel.style.borderTop = state.panelTheme.borderTop;
		panel.style.borderRight = state.panelTheme.borderRight;
		panel.style.borderBottom = state.panelTheme.borderBottom;
		panel.style.borderLeft = state.panelTheme.borderLeft;
		panel.style.borderRadius = state.panelTheme.borderRadius;
		panel.style.boxShadow = state.panelTheme.boxShadow;
		if (state.panelTheme.borderColor && !isTransparentColor(state.panelTheme.borderColor)) {
			panel.style.setProperty('--rc-panel-border-color', state.panelTheme.borderColor);
		}
		if (state.panelTheme.accentColor) {
			panel.style.setProperty('--rc-accent-color', state.panelTheme.accentColor);
		}
		var card = panel.querySelector('.rc-card');
		if (card && state.cardTheme) {
			card.style.backgroundColor = state.cardTheme.backgroundColor;
			card.style.backgroundImage = state.cardTheme.backgroundImage;
			card.style.borderRadius = state.cardTheme.borderRadius;
			card.style.boxShadow = state.cardTheme.boxShadow;
		}
	}

	function hideOriginalPanels() {
		state.hiddenPanels = [];
		var panels = getOriginalPanels();
		capturePanelTheme(panels);
		for (var i = 0; i < panels.length; i++) {
			state.hiddenPanels.push({
				node: panels[i],
				display: panels[i].style.display
			});
			panels[i].style.display = 'none';
		}
	}

	function restoreOriginalPanels() {
		for (var i = 0; i < state.hiddenPanels.length; i++) {
			state.hiddenPanels[i].node.style.display = state.hiddenPanels[i].display;
		}
		state.hiddenPanels = [];
		if (state.panel) {
			state.panel.style.display = 'none';
		}
		if (state.tab) {
			state.tab.classList.remove('rc-cert-active');
			state.tab.setAttribute('aria-selected', 'false');
		}
	}

	function createPanel() {
		if (state.panel) {
			return state.panel;
		}
		var panel = document.createElement('section');
		panel.id = 'rc-cert-panel';
		panel.setAttribute('role', 'tabpanel');
		panel.setAttribute('aria-labelledby', 'rc-cert-tab');
		panel.innerHTML =
			'<div class="rc-card">' +
			'<div class="rc-toolbar">' +
			'<h2 class="rc-heading">Review Certificates</h2>' +
			'<label class="rc-search">' +
			'<span class="rc-search-icon" aria-hidden="true">&#128269;</span>' +
			'<input type="search" class="rc-search-input" aria-label="Search certificates" placeholder="Search title, author, or article ID">' +
			'</label>' +
			'</div>' +
			'<div class="rc-content"><div class="rc-loading">Loading certificates…</div></div>' +
			'</div>';
		var panelHost = state.hiddenPanels.length && state.hiddenPanels[0].node.parentElement
			? state.hiddenPanels[0].node.parentElement
			: state.tabsRoot;
		panelHost.appendChild(panel);
		applyPanelTheme(panel);
		state.panel = panel;
		panel.querySelector('.rc-search-input').addEventListener('input', function () {
			state.searchQuery = this.value;
			state.currentPage = 1;
			renderCertificatePage(1);
		});
		return panel;
	}

	function normalizeSearchText(value) {
		var normalized = (value || '').toString().toLowerCase().trim();
		if (normalized.normalize) {
			normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		}
		return normalized.replace(/\s+/g, ' ');
	}

	function getFilteredItems() {
		var query = normalizeSearchText(state.searchQuery);
		if (!query) {
			return state.certificateItems;
		}
		return state.certificateItems.filter(function (item) {
			return item.searchText.indexOf(query) !== -1;
		});
	}

	function getPageNumbers(currentPage, totalPages) {
		var pages = [];
		for (var page = 1; page <= totalPages; page++) {
			if (page === 1 || page === totalPages || Math.abs(page - currentPage) <= 1) {
				pages.push(page);
			}
		}
		var output = [];
		for (var i = 0; i < pages.length; i++) {
			if (i > 0 && pages[i] - pages[i - 1] > 1) {
				output.push('ellipsis');
			}
			output.push(pages[i]);
		}
		return output;
	}

	function renderCertificatePage(requestedPage) {
		var content = state.panel.querySelector('.rc-content');
		var filteredItems = getFilteredItems();
		var totalPages = Math.max(1, Math.ceil(filteredItems.length / state.pageSize));
		state.currentPage = Math.max(1, Math.min(requestedPage, totalPages));
		var start = (state.currentPage - 1) * state.pageSize;
		var pageItems = filteredItems.slice(start, start + state.pageSize);
		if (!filteredItems.length) {
			content.innerHTML = '<div class="rc-empty">No certificates match your search.</div>';
			return;
		}
		var output = '<div class="rc-list">';

		for (var i = 0; i < pageItems.length; i++) {
			var item = pageItems[i];
			output += '<article class="rc-item">' +
				'<div class="rc-copy">' +
				'<h3 class="rc-title">' + escapeHtml(item.title) + '</h3>' +
				(item.meta ? '<div class="rc-meta">' + escapeHtml(item.meta) + '</div>' : '') +
				'</div>' +
				(item.href ? '<a class="pkp_button rc-action" href="' + escapeHtml(item.href) + '" target="_blank" rel="noopener">View Certificate</a>' : '') +
				'</article>';
		}
		output += '</div>';

		if (totalPages > 1) {
			var pageNumbers = getPageNumbers(state.currentPage, totalPages);
			output += '<nav class="rc-pagination" aria-label="Certificate pages">' +
				'<button type="button" class="pkp_button rc-page-prev" data-page="' + (state.currentPage - 1) + '"' +
				(state.currentPage === 1 ? ' disabled' : '') + '>Previous page</button>' +
				'<div class="rc-pagination-pages">';
			for (var j = 0; j < pageNumbers.length; j++) {
				if (pageNumbers[j] === 'ellipsis') {
					output += '<span class="rc-page-ellipsis" aria-hidden="true">…</span>';
				} else {
					output += '<button type="button" class="rc-page-button" data-page="' + pageNumbers[j] + '"' +
						(pageNumbers[j] === state.currentPage ? ' aria-current="page"' : '') +
						' aria-label="Page ' + pageNumbers[j] + '">' + pageNumbers[j] + '</button>';
				}
			}
			output += '</div>' +
				'<button type="button" class="pkp_button rc-page-next" data-page="' + (state.currentPage + 1) + '"' +
				(state.currentPage === totalPages ? ' disabled' : '') + '>Next page</button>' +
				'</nav>';
		}

		content.innerHTML = output;
		var pagination = content.querySelector('.rc-pagination');
		if (pagination) {
			pagination.addEventListener('click', function (event) {
				var button = event.target.closest('button[data-page]');
				if (!button || button.disabled) {
					return;
				}
				renderCertificatePage(parseInt(button.getAttribute('data-page'), 10));
				state.panel.querySelector('.rc-heading').scrollIntoView({block: 'start'});
			});
		}
	}

	function renderList(html) {
		var parser = new DOMParser();
		var source = parser.parseFromString(html, 'text/html');
		var sourceItems = source.querySelectorAll('.item');
		var content = state.panel.querySelector('.rc-content');

		if (!sourceItems.length) {
			content.innerHTML = '<div class="rc-empty">No review certificates are available yet.</div>';
			return;
		}

		state.certificateItems = [];
		for (var i = 0; i < sourceItems.length; i++) {
			var title = sourceItems[i].querySelector('.title');
			var meta = sourceItems[i].querySelector('.meta');
			var action = sourceItems[i].querySelector('a.btn, a.button, a[href*="reviewercertificate"]');
			if (!title) {
				continue;
			}
			state.certificateItems.push({
				title: title.textContent.trim(),
				meta: meta ? meta.textContent.trim() : '',
				href: action ? action.href : '',
				authors: sourceItems[i].getAttribute('data-authors') || '',
				articleId: sourceItems[i].getAttribute('data-submission-id') || ''
			});
		}
		for (var j = 0; j < state.certificateItems.length; j++) {
			var item = state.certificateItems[j];
			item.searchText = normalizeSearchText([
				item.title,
				item.authors,
				item.articleId,
				'id ' + item.articleId,
				'article id ' + item.articleId
			].join(' '));
		}
		state.searchQuery = '';
		var searchInput = state.panel.querySelector('.rc-search-input');
		if (searchInput) {
			searchInput.value = '';
		}
		state.currentPage = 1;
		renderCertificatePage(1);
		window.reviewerCertificateTabStatus.panelLoaded = true;
	}

	function loadCertificates() {
		var content = state.panel.querySelector('.rc-content');
		content.innerHTML = '<div class="rc-loading">Loading certificates…</div>';
		fetch(state.certificateUrl, {credentials: 'same-origin'})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.text();
			})
			.then(renderList)
			.catch(function (error) {
				content.innerHTML =
					'<div class="rc-error">Certificates could not be loaded (' +
					escapeHtml(error.message) +
					'). Please refresh the page and try again.</div>';
			});
	}

	function activateCertificates(event) {
		if (event) {
			event.preventDefault();
			event.stopPropagation();
		}
		if (state.panel && state.panel.style.display !== 'none') {
			return;
		}
		hideOriginalPanels();
		var panel = createPanel();
		applyPanelTheme(panel);
		panel.style.display = '';
		state.tab.classList.add('rc-cert-active');
		state.tab.setAttribute('aria-selected', 'true');
		if (state.queueTab) {
			state.queueTab.setAttribute('aria-selected', 'false');
		}
		if (state.archiveTab) {
			state.archiveTab.setAttribute('aria-selected', 'false');
		}
		window.location.hash = 'certificates';
		loadCertificates();
	}

	function installTab() {
		if (document.getElementById('rc-cert-tab')) {
			return;
		}

		state.certificateUrl = getCertificateUrl();
		if (!state.certificateUrl) {
			return;
		}

		state.queueTab = findTab(/^(my queue|queue|antrean saya|antrean)\b/);
		state.archiveTab = findTab(/^(archives?|arsip)\b/);
		if (!state.queueTab || !state.archiveTab) {
			return;
		}

		var container = findCommonContainer(state.queueTab, state.archiveTab);
		if (!container) {
			return;
		}

		var archiveRoot = getDirectChild(container, state.archiveTab);
		if (!archiveRoot) {
			return;
		}

		var tab = document.createElement(
			state.archiveTab.tagName.toLowerCase() === 'a' ? 'a' : 'button'
		);
		tab.id = 'rc-cert-tab';
		tab.className = state.archiveTab.className;
		tab.setAttribute('role', 'tab');
		tab.setAttribute('aria-selected', 'false');
		tab.setAttribute('title', 'My Review Certificates');
		tab.textContent = 'Certificates';
		if (tab.tagName.toLowerCase() === 'a') {
			tab.href = '#certificates';
		} else {
			tab.type = 'button';
		}
		tab.addEventListener('click', activateCertificates);

		var nodeToInsert = tab;
		if (archiveRoot !== state.archiveTab) {
			var wrapper = archiveRoot.cloneNode(false);
			wrapper.removeAttribute('id');
			wrapper.appendChild(tab);
			nodeToInsert = wrapper;
		}

		if (archiveRoot.nextSibling) {
			container.insertBefore(nodeToInsert, archiveRoot.nextSibling);
		} else {
			container.appendChild(nodeToInsert);
		}

		state.tab = tab;
		state.tabsRoot = findTabsRoot(container);
		state.queueTab.addEventListener('click', restoreOriginalPanels);
		state.archiveTab.addEventListener('click', restoreOriginalPanels);
		addStyles();
		window.reviewerCertificateTabStatus.installed = true;

		if (window.location.hash === '#certificates') {
			activateCertificates();
		}
	}

	function start() {
		installTab();
		var observer = new MutationObserver(installTab);
		observer.observe(document.body, {childList: true, subtree: true});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, {once: true});
	} else {
		start();
	}
})();
