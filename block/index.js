/**
 * OSM Easy Points — Gutenberg block.
 * Registered server-side (PHP); this script renders the editor preview and
 * exposes the settings sidebar. No build step required.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var Disabled = wp.components.Disabled;

	// Branded location-pin icon for the block inserter and fallback placeholder.
	var pinIcon = el(
		'svg',
		{ width: 24, height: 24, viewBox: '0 0 24 24', 'aria-hidden': true },
		el('path', {
			fill: 'currentColor',
			fillRule: 'evenodd',
			d: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zM14.5 9a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z',
		})
	);

	var DEFAULTS = (window.oepSettings && window.oepSettings.defaults) || {
		name: 'Vienna, Austria',
		lat: 48.2082,
		lng: 16.3738,
		zoom: 13,
		height: 480,
		edit: true,
	};

function renderPreviewMap(node, attrs) {
		var lat = isFinite(parseFloat(attrs.lat)) ? parseFloat(attrs.lat) : DEFAULTS.lat;
		var lng = isFinite(parseFloat(attrs.lng)) ? parseFloat(attrs.lng) : DEFAULTS.lng;
		var zoom = parseInt(attrs.zoom, 10) || DEFAULTS.zoom;

		node.style.height = (parseInt(attrs.height, 10) || DEFAULTS.height) + 'px';
		node._oepMap = window.L.map(node, { scrollWheelZoom: false }).setView([lat, lng], zoom);
		window.L
			.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
				maxZoom: 19,
			})
			.addTo(node._oepMap);

		if (window.oepSettings && window.oepSettings.restUrl) {
			window
				.fetch(window.oepSettings.restUrl + '?limit=300', { credentials: 'omit' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					(data.points || []).slice(0, 300).forEach(function (p) {
						window.L
							.marker([p.lat, p.lng])
							.addTo(node._oepMap)
							.bindPopup(p.name || p.text || '');
					});
				})
				.catch(function () {});
		}
	}

	wp.blocks.registerBlockType('osm-easy-points/map', {
		title: __('OSM Map', 'osm-easy-points'),
		description: __(
			'Interactive OpenStreetMap where anyone can add points with text — no login needed.',
			'osm-easy-points'
		),
		icon: pinIcon,
		category: 'widgets',
		keywords: [__('map', 'osm-easy-points'), __('openstreetmap', 'osm-easy-points'), __('osm', 'osm-easy-points')],
		supports: {
			align: ['wide', 'full'],
			html: false,
		},
		attributes: {
			name: { type: 'string' },
			lat: { type: 'number' },
			lng: { type: 'number' },
			zoom: { type: 'number' },
			height: { type: 'number' },
			edit: { type: 'boolean', default: true },
			limit: { type: 'number' },
			editExplicit: { type: 'boolean', default: false },
		},

		edit: function (props) {
			var attrs = props.attributes;
			var blockProps = useBlockProps ? useBlockProps({ className: 'oep-block-wrap' }) : { className: 'oep-block-wrap' };

			function set(key, value, explicit) {
				var update = {};
				update[key] = value;
				if (explicit) {
					update.editExplicit = true;
				}
				props.setAttributes(update);
			}

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __('Map settings', 'osm-easy-points'), initialOpen: true },
					el(TextControl, {
						label: __('City / place name', 'osm-easy-points'),
						value: attrs.name != null ? attrs.name : (DEFAULTS.name || ''),
						onChange: function (v) { set('name', v); },
					}),
					el(
						'p',
						{ style: { color: '#666', fontSize: '12px', marginTop: '-4px' } },
						__('Just type a city or address — it is turned into map coordinates via OpenStreetMap.', 'osm-easy-points')
					),
					el(
						PanelBody,
						{ title: __('Exact coordinates (optional)', 'osm-easy-points'), initialOpen: false },
						el(TextControl, {
							label: __('Center latitude', 'osm-easy-points'),
							type: 'number',
							step: '0.0000001',
							value: attrs.lat != null ? attrs.lat : DEFAULTS.lat,
							onChange: function (v) { set('lat', parseFloat(v) || 0); },
						}),
						el(TextControl, {
							label: __('Center longitude', 'osm-easy-points'),
							type: 'number',
							step: '0.0000001',
							value: attrs.lng != null ? attrs.lng : DEFAULTS.lng,
							onChange: function (v) { set('lng', parseFloat(v) || 0); },
						})
					),
					el(TextControl, {
						label: __('Zoom (1–19)', 'osm-easy-points'),
						type: 'number',
						min: 1,
						max: 19,
						value: attrs.zoom != null ? attrs.zoom : DEFAULTS.zoom,
						onChange: function (v) { set('zoom', parseInt(v, 10) || DEFAULTS.zoom); },
					}),
					el(TextControl, {
						label: __('Height (px)', 'osm-easy-points'),
						type: 'number',
						min: 200,
						max: 1200,
						value: attrs.height != null ? attrs.height : DEFAULTS.height,
						onChange: function (v) { set('height', parseInt(v, 10) || DEFAULTS.height); },
					}),
					el(TextControl, {
						label: __('Max points shown', 'osm-easy-points'),
						type: 'number',
						min: 10,
						max: 5000,
						value: attrs.limit != null ? attrs.limit : (window.oepSettings ? window.oepSettings.defaults.limit || 500 : 500),
						onChange: function (v) { set('limit', parseInt(v, 10) || 500); },
					}),
					el(ToggleControl, {
						label: __('Let visitors add points (no login)', 'osm-easy-points'),
						checked: attrs.editExplicit ? !!attrs.edit : !!DEFAULTS.edit,
						onChange: function (v) { set('edit', !!v, true); },
					}),
					el(
						'p',
						{ style: { color: '#666', fontSize: '12px' } },
						__(
							'Tip: click the map on the live site to find coordinates, or use the shortcode [osm_easy_points] in classic editors.',
							'osm-easy-points'
						)
					)
				)
			);

		var mapRef = wp.element.useRef(null);

		wp.element.useEffect(
			function () {
				var node = mapRef.current;
				if (!node || !window.L) {
					return;
				}
				renderPreviewMap(node, attrs);
				return function () {
					if (node._oepMap) {
						node._oepMap.remove();
						node._oepMap = null;
					}
				};
			},
			[attrs.lat, attrs.lng, attrs.zoom, attrs.height]
		);

		var preview = el(
			'figure',
			{ style: { margin: 0 } },
			el('div', { className: 'oep-map oep-map-editor-preview', ref: mapRef })
		);

			var fallback = el(
				Placeholder,
				{ icon: pinIcon, label: __('OSM Map', 'osm-easy-points') },
				el('p', {}, __('Leaflet failed to load — the map will still render on the live site.', 'osm-easy-points'))
			);

			return el(
				Fragment,
				{},
				inspector,
				el('div', blockProps, window.L ? preview : fallback)
			);
		},

		save: function () {
			// Rendered server-side via render_callback (dynamic block).
			return null;
		},
	});
})(window.wp);
