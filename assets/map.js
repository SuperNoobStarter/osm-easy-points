/**
 * OSM Easy Points — frontend map.
 * Vanilla JS + Leaflet. Auto-initializes every .oep-map on the page.
 * Works with shortcode output from any editor; no build step needed.
 */
(function () {
	'use strict';

	var TILESETS = {
		osm: {
			url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 19,
		},
		satellite: {
			url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
			attr: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
			maxZoom: 19,
		},
		topo: {
			url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
			attr: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, SRTM | Style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
			maxZoom: 17,
		},
	};

	var CFG = window.oepSettings || {};
	var T = CFG.i18n || {};
	var ICONS = CFG.icons || {}; // key -> { emoji, label, color }

	function iconInfo(key) {
		return (key && ICONS[key]) || null;
	}

	/** Turn raw point text into safe HTML: escape everything, then linkify URLs. */
	function linkify(raw) {
		var safe = esc(raw);
		return safe.replace(/(^|[\s(])((?:https?:\/\/|www\.)[^\s<"']+)/g, function (m, pre, url) {
			// Keep trailing punctuation out of the link ("…at example.com.").
			var trail = '';
			var core = url.replace(/[.,;:!?)\]]+$/, function (t) { trail = t; return ''; });
			var href = core.indexOf('http') === 0 ? core : 'https://' + core;
			var label = core.length > 60 ? core.slice(0, 57) + '…' : core;
			return pre + '<a href="' + href + '" target="_blank" rel="noopener noreferrer nofollow">' + label + '</a>' + trail;
		});
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function el(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		return tmp.firstElementChild;
	}

	function iconChip(key) {
		var info = iconInfo(key);
		return info ? '<span class="oep-popup-icon" title="' + esc(info.label) + '">' + info.emoji + '</span>' : '';
	}

	function iconPickerHtml() {
		var keys = Object.keys(ICONS);
		if (!keys.length) {
			return '';
		}
		var html = '<div class="oep-icon-picker" role="radiogroup" aria-label="Icon">';
		keys.forEach(function (key) {
			var info = ICONS[key];
			html += '<button type="button" class="oep-icon-choice" data-icon="' + esc(key) + '" title="' + esc(info.label) + '" aria-label="' + esc(info.label) + '">' + info.emoji + '</button>';
		});
		html += '</div>';
		return html;
	}

	function pointHtml(p, canDelete) {
		var title = p.name ? esc(p.name) : esc(T.untitled || 'Untitled point');
		var body = p.text ? '<p class="oep-popup-text">' + linkify(p.text) + '</p>' : '';
		var author = p.author
			? '<span class="oep-popup-author">' + esc(p.author) + '</span>'
			: '<span class="oep-popup-author oep-anon">' + esc(T.anonymous || 'Anonymous') + '</span>';
		var del = canDelete
			? '<button type="button" class="oep-popup-delete" data-id="' + p.id + '">' + esc(T.delete || 'Delete') + '</button>'
			: '';
		return (
			'<div class="oep-popup">' +
			iconChip(p.icon) +
			'<strong class="oep-popup-title">' + title + '</strong>' +
			body +
			'<div class="oep-popup-meta">' + author + ' · ' + esc(p.created || '') + '</div>' +
			del +
			'</div>'
		);
	}

	var iconSeq = 0;

	function makeIcon(hl, iconKey) {
		var uid = 'oepg' + (++iconSeq);
		var info = iconInfo(iconKey);
		var c1 = info ? shade(info.color, 34) : '#ff7a59';
		var c2 = info ? info.color : '#d3242c';
		var stroke = info ? shade(info.color, -28) : '#8f1d22';
		var glyph = info
			? '<text x="14" y="17.5" text-anchor="middle" font-size="11" class="oep-pin-emoji">' + info.emoji + '</text>'
			: '<circle cx="14" cy="12.8" r="4.8" fill="#fff"/><circle cx="14" cy="12.8" r="2.4" fill="#1a73e8"/>';
		return window.L.divIcon({
			className: 'oep-marker' + (info ? ' oep-marker-icon' : '') + (hl ? ' oep-marker-hl' : ''),
			html:
				'<span class="oep-pin"><svg viewBox="0 0 28 38" width="28" height="38" aria-hidden="true">' +
				'<defs><linearGradient id="' + uid + '" x1="0" y1="0" x2="0" y2="1">' +
				'<stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/>' +
				'</linearGradient></defs>' +
				'<path class="oep-pin-body" d="M14 1C7.4 1 2 6.3 2 12.8 2 21.6 14 37 14 37s12-15.4 12-24.2C26 6.3 20.6 1 14 1z" fill="url(#' + uid + ')" stroke="' + stroke + '" stroke-width="1.4" stroke-linejoin="round"/>' +
				glyph +
				'</svg></span>',
			iconSize: [28, 38],
			iconAnchor: [14, 37],
			popupAnchor: [0, -35],
		});
	}

	// Lighten (+) or darken (-) a hex color by a percentage.
	function shade(hex, percent) {
		var n = parseInt(hex.slice(1), 16);
		var t = percent < 0 ? 0 : 255;
		var p = Math.abs(percent) / 100;
		var r = (n >> 16) & 0xff, g = (n >> 8) & 0xff, b = n & 0xff;
		r = Math.round((t - r) * p + r);
		g = Math.round((t - g) * p + g);
		b = Math.round((t - b) * p + b);
		return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
	}

	function initMap(root) {
		if (!window.L || root.dataset.ready) {
			return;
		}
		root.dataset.ready = '1';

		var lat = parseFloat(root.dataset.lat);
		var lng = parseFloat(root.dataset.lng);
		var zoom = parseInt(root.dataset.zoom, 10) || 13;
		var height = parseInt(root.dataset.height, 10) || 480;
		var edit = root.dataset.edit !== '0';
		var limit = parseInt(root.dataset.limit, 10) || 500;
		var tileset = TILESETS[CFG.tileset] || TILESETS.osm;

		root.style.height = height + 'px';

		var map = L.map(root, { scrollWheelZoom: false }).setView([lat, lng], zoom);
		L.tileLayer(tileset.url, { attribution: tileset.attr, maxZoom: tileset.maxZoom }).addTo(map);

		// Controls column.
		var controls = el(
			'<div class="oep-controls">' +
				'<button type="button" class="oep-btn oep-btn-add" style="display:none;">' + esc(T.addPoint || 'Add a point') + '</button>' +
				'<button type="button" class="oep-btn oep-btn-locate" title="' + esc(T.myLocation || '') + '">◎</button>' +
				'<button type="button" class="oep-btn oep-btn-list" title="' + esc(T.showList || '') + '">☰</button>' +
			'</div>'
		);
		root.appendChild(controls);

		var form = el(
			'<form class="oep-form" novalidate>' +
				'<h3>' + esc(T.addPoint || 'Add a point') + '</h3>' +
				'<p class="oep-form-hint">' + esc(T.addingHint || 'Click the map to place your point') + '</p>' +
				'<div class="oep-coords"><span></span></div>' +
				'<input type="text" name="name" maxlength="120" placeholder="' + esc(T.namePh || '') + '" />' +
				'<textarea name="text" maxlength="2000" rows="3" placeholder="' + esc(T.textPh || '') + '"></textarea>' +
				iconPickerHtml() +
				'<input type="text" name="author" maxlength="60" placeholder="' + esc(T.authorPh || '') + '" />' +
				'<input type="text" name="hp" class="oep-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />' +
				'<div class="oep-form-actions">' +
					'<button type="submit" class="oep-btn oep-btn-primary">' + esc(T.save || 'Save point') + '</button>' +
					'<button type="button" class="oep-btn oep-btn-cancel">' + esc(T.cancel || 'Cancel') + '</button>' +
				'</div>' +
				'<p class="oep-form-msg" role="status"></p>' +
			'</form>'
		);
		root.appendChild(form);

		var panel = el('<div class="oep-panel"><input type="search" class="oep-search" placeholder="' + esc(T.searchPh || '') + '" /><ul class="oep-list"></ul><p class="oep-panel-empty">' + esc(T.noPoints || '') + '</p></div>');
		root.appendChild(panel);

		// Keep clicks/scrolls inside our overlays from reaching the map
		// (otherwise a click on the form would drop a new pin).
		[controls, form, panel].forEach(function (elm) {
			if (window.L.DomEvent && window.L.DomEvent.disableClickPropagation) {
				window.L.DomEvent.disableClickPropagation(elm);
			}
		});

		var addBtn = controls.querySelector('.oep-btn-add');
		var locateBtn = controls.querySelector('.oep-btn-locate');
		var listBtn = controls.querySelector('.oep-btn-list');
		var formMsg = form.querySelector('.oep-form-msg');
		var coordsSpan = form.querySelector('.oep-coords span');
		var searchInput = panel.querySelector('.oep-search');
		var listEl = panel.querySelector('.oep-list');
		var panelEmpty = panel.querySelector('.oep-panel-empty');

		var markers = {}; // id -> marker
		var points = []; // newest first
		var pending = null; // [lat,lng] waiting for form input
		var tempMarker = null;
		var selectedIcon = '';
		var CACHE_KEY = 'oep_points_' + (CFG.restUrl || '').replace(/[^a-z0-9]+/gi, '_');

		if (edit) {
			addBtn.style.display = '';
		}

		// --- icon picker ----------------------------------------------------

		function selectIconChoice(btn) {
			form.querySelectorAll('.oep-icon-choice').forEach(function (b) {
				b.classList.remove('oep-icon-selected');
				b.setAttribute('aria-checked', 'false');
			});
			if (btn) {
				btn.classList.add('oep-icon-selected');
				btn.setAttribute('aria-checked', 'true');
				selectedIcon = btn.getAttribute('data-icon') || '';
			}
		}

		form.addEventListener('click', function (ev) {
			var choice = ev.target.closest('.oep-icon-choice');
			if (choice) {
				ev.preventDefault();
				selectIconChoice(choice);
				if (tempMarker) {
					tempMarker.setIcon(makeIcon(true, selectedIcon));
				}
			}
		});

		// --- markers ------------------------------------------------------

		function popupFor(p) {
			return pointHtml(p, !!(CFG.canDelete && CFG.nonce));
		}

		function addMarker(p, hl) {
			if (markers[p.id]) {
				return;
			}
			var m = L.marker([p.lat, p.lng], { icon: makeIcon(hl, p.icon) }).addTo(map);
			m.bindPopup(popupFor(p));
			markers[p.id] = m;
			if (hl) {
				m.openPopup();
			}
		}

		function renderList() {
			var q = searchInput.value.trim().toLowerCase();
			listEl.innerHTML = '';
			var shown = 0;

			points.forEach(function (p) {
				var hay = ((p.name || '') + ' ' + (p.text || '') + ' ' + (p.author || '')).toLowerCase();
				if (q && hay.indexOf(q) === -1) {
					return;
				}
				shown++;
				var title = p.name ? esc(p.name) : esc(T.untitled || 'Untitled point');
				var li = el(
					'<li data-id="' + p.id + '">' +
						iconChip(p.icon) +
						'<strong>' + title + '</strong>' +
						(p.text ? '<span class="oep-li-text">' + esc(p.text.slice(0, 80)) + (p.text.length > 80 ? '…' : '') + '</span>' : '') +
					'</li>'
				);
				li.addEventListener('click', function () {
					map.setView([p.lat, p.lng], Math.max(map.getZoom(), 15));
					var mk = markers[p.id];
					if (mk) {
						mk.openPopup();
					}
				});
				listEl.appendChild(li);
			});

			panelEmpty.style.display = shown ? 'none' : '';
		}

		function setPoints(list) {
			points = list.slice(0, limit);
			var keep = {};
			points.forEach(function (p) {
				keep[p.id] = 1;
				addMarker(p);
			});
			Object.keys(markers).forEach(function (id) {
				if (!keep[id]) {
					map.removeLayer(markers[id]);
					delete markers[id];
				}
			});
			renderList();
		}

		/**
		 * Build the GET list URL correctly on every permalink style.
		 *
		 * On "plain" permalinks the REST URL already contains a question mark
		 * (`/?rest_route=/osm-easy-points/v1/points`). Naively appending another
		 * `?limit=…` produces a second `?`, WordPress mis-parses the route and
		 * answers 404 — points were saved fine but the map came back empty on
		 * every reload. Using URLSearchParams (which merges onto the existing
		 * query string) fixes that; the string concat is the fallback for the
		 * handful of browsers without it.
		 */
		function listUrl() {
			try {
				var u = new URL(CFG.restUrl, window.location.href);
				u.searchParams.set('limit', limit);
				u.searchParams.set('_', Date.now());
				return u.toString();
			} catch (e) {
				var sep = CFG.restUrl.indexOf('?') === -1 ? '?' : '&';
				return CFG.restUrl + sep + 'limit=' + limit + '&_=' + Date.now();
			}
		}

		function load() {
			fetch(listUrl(), { credentials: 'omit' })
				.then(function (r) {
					if (!r.ok) {
						throw new Error('http ' + r.status);
					}
					return r.json();
				})
				.then(function (data) {
					var list = (data && data.points) || [];
					try {
						// Cache what we saw so the wall never comes back blank if
						// a later request fails (flaky host, cache plugin hiccup…).
						localStorage.setItem(CACHE_KEY, JSON.stringify({ t: Date.now(), points: list.slice(0, 200) }));
					} catch (e) { /* storage full/blocked — ignore */ }
					setPoints(list);
				})
				.catch(function () {
					// Server unreachable: fall back to the last good list so
					// visitors still see the points instead of an empty map.
					var cached = null;
					try {
						cached = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
					} catch (e) { /* ignore */ }
					if (cached && cached.points && cached.points.length) {
						setPoints(cached.points);
					}
				});
			// Gentle auto-refresh: picks up points added by other visitors
			// without anyone needing to reload. Pauses while the add form is
				// open so we never yank the map around under someone typing.
			setInterval(function () {
				if (!form.classList.contains('oep-open')) {
					load();
				}
			}, 60000);
		}

		// --- form flow ----------------------------------------------------

		function openForm(latlng) {
			pending = latlng;
			coordsSpan.textContent = latlng[0].toFixed(5) + ', ' + latlng[1].toFixed(5);
			form.classList.add('oep-open');
			panel.classList.remove('oep-open');
			var nameInput = form.querySelector('input[name="name"]');
			setTimeout(function () { nameInput.focus(); }, 50);
			// Keep a previously chosen icon (e.g. picked before clicking the map);
			// just re-sync the highlight with the current selection.
			var current = form.querySelector('.oep-icon-choice[data-icon="' + (selectedIcon || '') + '"]');
			selectIconChoice(current || form.querySelector('.oep-icon-choice'));

			if (tempMarker) {
				map.removeLayer(tempMarker);
			}
			tempMarker = L.marker(latlng, { icon: makeIcon(true, selectedIcon), interactive: false }).addTo(map);
		}

		function closeForm() {
			pending = null;
			form.classList.remove('oep-open');
			if (tempMarker) {
				map.removeLayer(tempMarker);
				tempMarker = null;
			}
		}

		function msg(text, ok) {
			formMsg.textContent = text || '';
			formMsg.classList.toggle('oep-msg-ok', !!ok);
			formMsg.classList.toggle('oep-msg-err', !ok);
		}

		addBtn.addEventListener('click', function () {
			if (form.classList.contains('oep-open')) {
				closeForm();
				return;
			}
			msg('');
			// No location chosen yet — next map click will open the form.
			pending = pending || false;
			panel.classList.remove('oep-open');
			coordsSpan.textContent = '';
			form.classList.add('oep-open');
			form.classList.add('oep-picking');
			setTimeout(function () { form.querySelector('input[name="name"]').focus(); }, 50);
		});

		map.on('click', function (e) {
			if (!edit) {
				return;
			}
			if (form.classList.contains('oep-picking') || !form.classList.contains('oep-open')) {
				form.classList.remove('oep-picking');
				openForm([e.latlng.lat, e.latlng.lng]);
			}
		});

		form.querySelector('.oep-btn-cancel').addEventListener('click', closeForm);

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			if (!pending) {
				msg(T.addingHint || 'Click the map to place your point', false);
				form.classList.add('oep-picking');
				return;
			}

			var btn = form.querySelector('button[type="submit"]');
			btn.disabled = true;
			var saveLabel = btn.textContent;
			btn.textContent = T.saving || 'Saving…';

			var payload = {
				lat: pending[0],
				lng: pending[1],
				name: form.querySelector('input[name="name"]').value,
				text: form.querySelector('textarea[name="text"]').value,
				icon: selectedIcon || '',
				author: form.querySelector('input[name="author"]').value,
				hp: form.querySelector('input[name="hp"]').value,
			};

			var headers = { 'Content-Type': 'application/json' };
			if (CFG.nonce) {
				headers['X-WP-Nonce'] = CFG.nonce;
			}

			fetch(CFG.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: headers,
				body: JSON.stringify(payload),
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { ok: r.ok, data: data };
					});
				})
				.then(function (res) {
					if (!res.ok) {
						var m = (res.data && res.data.message) || T.error || 'Error';
						if (res.data && res.data.code === 'oep_rate_limited') {
							m = T.slowDown || m;
						}
						msg(m, false);
						return;
					}
				points.unshift(res.data);
				addMarker(res.data, true);
				renderList();
				closeForm();
				form.reset();
				selectIconChoice(form.querySelector('.oep-icon-choice'));
				selectedIcon = '';
					msg(T.thanks || 'Thanks!', true);
					setTimeout(function () { msg(''); }, 4000);
				})
				.catch(function () {
					msg(T.error || 'Error', false);
				})
				.finally(function () {
					btn.disabled = false;
					btn.textContent = saveLabel;
				});
		});

		// --- misc controls --------------------------------------------------

		locateBtn.addEventListener('click', function () {
			if (!navigator.geolocation) {
				return;
			}
			map.locate({ setView: true, maxZoom: 16 });
		});

		map.on('locationfound', function (e) {
			L.circleMarker(e.latlng, { radius: 8, color: '#136AEC', fillOpacity: 0.4 })
				.bindPopup(esc(T.myLocation || ''))
				.addTo(map);
		});

		listBtn.addEventListener('click', function () {
			panel.classList.toggle('oep-open');
			form.classList.remove('oep-open');
			if (panel.classList.contains('oep-open')) {
				renderList();
			}
		});

		searchInput.addEventListener('input', renderList);

		root.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.oep-popup-delete');
			if (!btn) {
				return;
			}
			if (!window.confirm(T.confirmDelete || 'Delete?')) {
				return;
			}
			var id = btn.getAttribute('data-id');
			var delHeaders = {};
			if (CFG.nonce) {
				delHeaders['X-WP-Nonce'] = CFG.nonce;
			}
			fetch(CFG.restUrl + '/' + id, {
				method: 'DELETE',
				headers: delHeaders,
			})
				.then(function (r) {
					if (!r.ok) throw new Error('failed');
					return r.json();
				})
				.then(function () {
					if (markers[id]) {
						map.removeLayer(markers[id]);
						delete markers[id];
					}
					points = points.filter(function (p) { return String(p.id) !== String(id); });
					renderList();
					map.fire('oep:deleted');
				})
				.catch(function () { /* noop */ });
		});

		// Keep Leaflet happy inside flexible layouts.
		window.addEventListener('resize', function () {
			map.invalidateSize();
		});
		if (window.ResizeObserver) {
			new ResizeObserver(function () {
				map.invalidateSize();
			}).observe(root);
		}

		load();
	}

	function boot() {
		document.querySelectorAll('.oep-map').forEach(initMap);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
