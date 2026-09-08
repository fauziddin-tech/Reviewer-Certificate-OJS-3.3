/* Reviewer Certificate 1.9.0 public insights — no external runtime dependencies. */
(function () {
	'use strict';

	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
			return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
		});
	}

	function addStyles() {
		if (document.getElementById('rc-insights-styles')) return;
		var style = document.createElement('style');
		style.id = 'rc-insights-styles';
		style.textContent =
			'.rc-insights{--rc-primary:#173f7a;--rc-accent:#c9a253;--rc-ink:#172033;--rc-muted:#667085;margin:42px 0 12px;padding:26px;border:1px solid #dce5f0;border-radius:14px;background:linear-gradient(145deg,#fff,#f7f9fc);box-shadow:0 8px 28px rgba(16,46,90,.07)}' +
			'.rc-insights__heading{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:20px}.rc-insights__heading h2{margin:3px 0 0;font-size:1.45rem;line-height:1.25;color:var(--rc-ink)}' +
			'.rc-insights__eyebrow{color:var(--rc-primary);font-size:.72rem;font-weight:750;letter-spacing:.11em;text-transform:uppercase}' +
			'.rc-insights__summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:18px}.rc-insights__metric{padding:13px 14px;border:1px solid #e0e7ef;border-radius:10px;background:#fff}.rc-insights__number{display:block;color:var(--rc-primary);font-size:1.45rem;font-weight:750;line-height:1.1}.rc-insights__label{display:block;margin-top:4px;color:var(--rc-muted);font-size:.76rem}' +
			'.rc-insights__grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(230px,.75fr);gap:18px;align-items:stretch}.rc-insights__map-wrap{position:relative;min-height:330px;padding:8px;border:1px solid #e0e7ef;border-radius:11px;background:#eef4f8;overflow:hidden}.rc-insights__map{display:block;width:100%;height:auto;min-height:310px}.rc-insights__country{fill:#dce5ec;stroke:#fff;stroke-width:.7;vector-effect:non-scaling-stroke;transition:fill .18s ease}.rc-insights__country.is-active{fill:var(--rc-primary);cursor:pointer}.rc-insights__country.is-active:hover,.rc-insights__country.is-active:focus{fill:var(--rc-accent);outline:none}' +
			'.rc-insights__side{display:flex;flex-direction:column;min-width:0}.rc-insights__side h3{margin:0 0 12px;font-size:.92rem;color:var(--rc-ink)}.rc-insights__bars{display:flex;flex-direction:column;gap:10px}.rc-insights__bar-head{display:flex;justify-content:space-between;gap:10px;font-size:.76rem}.rc-insights__bar-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rc-insights__bar-value{font-weight:700;color:var(--rc-primary)}.rc-insights__track{height:7px;margin-top:4px;border-radius:9px;background:#e7edf3;overflow:hidden}.rc-insights__fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--rc-primary),#3970ad)}' +
			'.rc-insights__legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:auto;padding-top:17px;color:var(--rc-muted);font-size:.72rem}.rc-insights__dot{display:inline-block;width:9px;height:9px;margin-right:5px;border-radius:50%;vertical-align:-1px}.rc-insights__dot--reviewer{background:var(--rc-primary)}.rc-insights__dot--editor{background:var(--rc-accent)}.rc-insights__empty{padding:40px 18px;text-align:center;color:var(--rc-muted);border:1px dashed #cbd6e2;border-radius:10px}' +
			'.rc-insights__table{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}' +
			'@media(max-width:800px){.rc-insights{padding:19px}.rc-insights__summary{grid-template-columns:repeat(2,minmax(0,1fr))}.rc-insights__grid{grid-template-columns:1fr}.rc-insights__map-wrap{min-height:230px}.rc-insights__map{min-height:210px}.rc-insights__legend{margin-top:5px}}@media(max-width:480px){.rc-insights__summary{grid-template-columns:1fr 1fr}.rc-insights__metric{padding:11px}.rc-insights__number{font-size:1.2rem}}';
		document.head.appendChild(style);
	}

	function polygons(geometry) {
		if (!geometry) return [];
		if (geometry.type === 'Polygon') return [geometry.coordinates];
		if (geometry.type === 'MultiPolygon') return geometry.coordinates;
		return [];
	}

	function ringPath(ring, shift) {
		if (!ring || !ring.length) return '';
		var min = 180;
		var max = -180;
		for (var i = 0; i < ring.length; i++) {
			min = Math.min(min, ring[i][0]);
			max = Math.max(max, ring[i][0]);
		}
		var crossesDateLine = max - min > 180;
		var output = '';
		for (var point = 0; point < ring.length; point++) {
			var longitude = ring[point][0];
			if (crossesDateLine && longitude < 0) longitude += 360;
			var x = ((longitude + 180) / 360 * 1000) + shift;
			var y = (90 - ring[point][1]) / 180 * 500;
			output += (point ? 'L' : 'M') + x.toFixed(2) + ',' + y.toFixed(2);
		}
		return output + 'Z';
	}

	function featurePath(feature) {
		var output = '';
		polygons(feature.geometry).forEach(function (polygon) {
			polygon.forEach(function (ring) {
				output += ringPath(ring, 0);
				var longitudes = ring.map(function (point) { return point[0]; });
				if (Math.max.apply(null, longitudes) - Math.min.apply(null, longitudes) > 180) {
					output += ringPath(ring, -1000);
				}
			});
		});
		return output;
	}

	function countryCode(feature) {
		var properties = feature.properties || {};
		return String(properties.iso_a2_eh || properties.ISO_A2_EH || properties.iso_a2 || '').toUpperCase();
	}

	function visibleCountryCount(data) {
		return data.countries.length;
	}

	function renderSummary(container, data) {
		var metrics = [];
		if (data.showReviewers) {
			metrics.push([data.totals.reviewers, data.labels.reviewers]);
			metrics.push([data.totals.reviews, data.labels.reviews]);
		}
		if (data.showEditors) metrics.push([data.totals.editors, data.labels.editors]);
		metrics.push([visibleCountryCount(data), data.labels.countries]);
		var html = '<div class="rc-insights__summary">';
		metrics.forEach(function (metric) {
			html += '<div class="rc-insights__metric"><span class="rc-insights__number">' +
				escapeHtml(metric[0]) + '</span><span class="rc-insights__label">' +
				escapeHtml(metric[1]) + '</span></div>';
		});
		container.insertAdjacentHTML('beforeend', html + '</div>');
	}

	function renderSide(container, data) {
		var maximum = 1;
		data.countries.forEach(function (country) {
			maximum = Math.max(maximum, (data.showReviewers ? country.reviewers : 0) + (data.showEditors ? country.editors : 0));
		});
		var html = '<aside class="rc-insights__side"><h3>' + escapeHtml(data.labels.countries) + '</h3><div class="rc-insights__bars">';
		data.countries.slice(0, 8).forEach(function (country) {
			var total = (data.showReviewers ? country.reviewers : 0) + (data.showEditors ? country.editors : 0);
			html += '<div class="rc-insights__bar"><div class="rc-insights__bar-head"><span class="rc-insights__bar-name">' +
				escapeHtml(country.name) + '</span><span class="rc-insights__bar-value">' + total +
				'</span></div><div class="rc-insights__track"><div class="rc-insights__fill" style="width:' +
				Math.max(5, total / maximum * 100).toFixed(1) + '%"></div></div></div>';
		});
		html += '</div><div class="rc-insights__legend">';
		if (data.showReviewers) html += '<span><i class="rc-insights__dot rc-insights__dot--reviewer"></i>' + escapeHtml(data.labels.reviewers) + '</span>';
		if (data.showEditors) html += '<span><i class="rc-insights__dot rc-insights__dot--editor"></i>' + escapeHtml(data.labels.editors) + '</span>';
		container.insertAdjacentHTML('beforeend', html + '</div></aside>');
	}

	function renderAccessibleTable(section, data) {
		var html = '<table class="rc-insights__table"><caption>' + escapeHtml(data.labels.countries) + '</caption><thead><tr><th>' +
			escapeHtml(data.labels.countries) + '</th>';
		if (data.showReviewers) html += '<th>' + escapeHtml(data.labels.reviewers) + '</th><th>' + escapeHtml(data.labels.reviews) + '</th>';
		if (data.showEditors) html += '<th>' + escapeHtml(data.labels.editors) + '</th>';
		html += '</tr></thead><tbody>';
		data.countries.forEach(function (country) {
			html += '<tr><th>' + escapeHtml(country.name) + '</th>';
			if (data.showReviewers) html += '<td>' + country.reviewers + '</td><td>' + country.reviews + '</td>';
			if (data.showEditors) html += '<td>' + country.editors + '</td>';
			html += '</tr>';
		});
		section.insertAdjacentHTML('beforeend', html + '</tbody></table>');
	}

	function renderMap(wrapper, geoJson, data) {
		var byCode = {};
		var maximum = 1;
		data.countries.forEach(function (country) {
			var total = (data.showReviewers ? country.reviewers : 0) + (data.showEditors ? country.editors : 0);
			byCode[country.code] = country;
			maximum = Math.max(maximum, total);
		});
		var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('class', 'rc-insights__map');
		svg.setAttribute('viewBox', '0 0 1000 500');
		svg.setAttribute('role', 'img');
		svg.setAttribute('aria-label', data.labels.countries);
		(geoJson.features || []).forEach(function (feature) {
			var code = countryCode(feature);
			var country = byCode[code];
			var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
			path.setAttribute('d', featurePath(feature));
			path.setAttribute('class', 'rc-insights__country' + (country ? ' is-active' : ''));
			if (country) {
				var total = (data.showReviewers ? country.reviewers : 0) + (data.showEditors ? country.editors : 0);
				path.style.fillOpacity = String(.4 + (total / maximum * .6));
				path.setAttribute('tabindex', '0');
				var details = [country.name];
				if (data.showReviewers) details.push(data.labels.reviewers + ': ' + country.reviewers, data.labels.reviews + ': ' + country.reviews);
				if (data.showEditors) details.push(data.labels.editors + ': ' + country.editors);
				var title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
				title.textContent = details.join(' — ');
				path.appendChild(title);
				path.setAttribute('aria-label', details.join(', '));
			}
			svg.appendChild(path);
		});
		wrapper.appendChild(svg);
	}

	function initialize(section) {
		var app = section.querySelector('.rc-insights__app');
		if (!app) return;
		var data;
		try { data = JSON.parse(app.getAttribute('data-insights')); } catch (error) { return; }
		addStyles();
		if (section.getAttribute('data-position') === 'bottom') {
			var page = section.closest('.page_index_journal');
			if (page) page.appendChild(section);
		}
		renderSummary(app, data);
		if (!data.countries.length) {
			app.insertAdjacentHTML('beforeend', '<div class="rc-insights__empty">' + escapeHtml(data.labels.noData) + '</div>');
			return;
		}
		var grid = document.createElement('div');
		grid.className = 'rc-insights__grid';
		var mapWrapper = document.createElement('div');
		mapWrapper.className = 'rc-insights__map-wrap';
		grid.appendChild(mapWrapper);
		app.appendChild(grid);
		renderSide(grid, data);
		renderAccessibleTable(section, data);
		fetch(section.getAttribute('data-map-url'), {credentials: 'same-origin'})
			.then(function (response) { if (!response.ok) throw new Error('Map unavailable'); return response.json(); })
			.then(function (geoJson) { renderMap(mapWrapper, geoJson, data); })
			.catch(function () { mapWrapper.innerHTML = '<div class="rc-insights__empty">' + escapeHtml(data.labels.noData) + '</div>'; });
	}

	function start() {
		var sections = document.querySelectorAll('.rc-insights');
		for (var i = 0; i < sections.length; i++) initialize(sections[i]);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
