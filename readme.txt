=== OSM Easy Points ===
Contributors: osmeasypoints
Tags: map, openstreetmap, leaflet, block, shortcode
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Interactive OpenStreetMap for any WordPress editor. Anyone can add points with text — no login, no permission needed.

== Description ==

OSM Easy Points adds a collaborative OpenStreetMap to your site. Visitors click the map, drop a pin, write a title and a short text — done. No account, no approval queue, no API key.

**Works with every editor**

* **Block editor (Gutenberg):** insert the "OSM Map" block, set center/zoom in the sidebar, see a live preview.
* **Classic editor / any page builder (Elementor, Divi, Bricks…):** use the `[osm_easy_points]` shortcode — shortcodes work everywhere.

**Features**

* OpenStreetMap tiles via Leaflet (no API key, no third-party account)
* Public, login-free point submission with title, text and optional name
* Points stored in your own database — GDPR-friendly, no external service
* Live map preview in the block editor
* Searchable points list panel on the map
* "Show my location" button
* Three map styles: standard OSM, satellite (Esri), terrain (OpenTopoMap)
* Icon pins: visitors pick shower / food / music / art / heart / clothes when adding a point
* URLs in point texts become clickable links automatically
* Admin dashboard with all points, one-click delete and CSV export
* Spam protection: honeypot field + per-visitor rate limiting
* Shortcode/block attributes to override every default per map

**Shortcode**

`[osm_easy_points]` — all defaults from the settings page.

`[osm_easy_points center="Vienna" zoom="13" height="480" edit="yes" limit="500"]`

* `center` — city or place name (e.g. `Vienna`, `Times Square, New York`); converted to coordinates via OpenStreetMap
* `lat` / `lng` — exact center (optional; overrides `center`)
* `zoom` — 1 (world) to 19 (house level)
* `height` — map height in pixels
* `edit` — `yes`/`no`, whether visitors can add points on this map
* `limit` — maximum number of points shown

== Installation ==

1. Upload the `osm-easy-points` folder to `/wp-content/plugins/` (or upload the zip via Plugins → Add New → Upload).
2. Activate the plugin.
3. Go to **OSM Points → Settings** in the admin sidebar (also under Settings → OSM Points) and type your city as the default map center.
4. Add the **OSM Map** block or the `[osm_easy_points]` shortcode to any post or page.

== Frequently Asked Questions ==

= Do visitors need to register? =

No. That's the point. Anyone can add a point — no login, no permission, no captcha (a hidden honeypot field and rate limiting keep bots out).

= Where are the points stored? =

In your own WordPress database (custom table). Nothing is sent to any external service. Map tiles are fetched from OpenStreetMap.org — see their usage policy if you expect heavy traffic.

= Can I turn off public editing? =

Yes — globally in the settings, or per map with `edit="no"` (shortcode) or the block's toggle.

= How do I moderate points? =

Open **OSM Points → Points** in the admin. You can inspect every point, jump to its location on openstreetmap.org, delete it, or export everything as CSV.

= Can I change the rate limit? =

Yes: `add_filter( 'oep_rate_limit_per_hour', function () { return 10; } );` (default 30 per visitor per hour; set to 0 to disable).

= Who can delete points? =

Only WordPress administrators (or anyone with the manage_options capability). Visitors never see a delete button, and the delete API rejects everyone else with 403 Forbidden. Developers can widen this with the oep_delete_capability filter.
== Changelog ==

= 1.1.3 =
* Hardening: deletion is strictly admin-only. The REST DELETE endpoint, the frontend popup button and the admin dashboard all share one capability check, now filterable via the oep_delete_capability filter. Rejected deletes show a clear message instead of failing silently.

= 1.1.2 =
* Fixed: on sites using "plain" permalinks, newly saved points disappeared after a page reload (the points list request was malformed and silently failed). Points now load reliably on every permalink style.
* Added: points are cached in the visitor's browser, so a temporary server or cache-plugin hiccup can no longer blank the map.
* Added: the map quietly refreshes every minute, so points from other visitors appear without reloading.
* Hardened: automatic database self-check guarantees the icon column exists before saving; the points API now sends no-cache headers so page-cache plugins can never serve a stale, empty point list.

= 1.1.1 =
* Four new icons: Sleep, Neutral, Care and Listen — the picker now has 11 choices.

= 1.1.0 =
* New: icon picker in the "Add a point" form — shower, food, music, art, heart, clothes. Each icon gets its own colored pin, and shows in popups, the points list and the admin dashboard.
* New: URLs in point texts are converted to clickable links automatically (safe: everything is escaped first, links open in a new tab with nofollow).

= 1.0.4 =
* Fancy custom pin icons everywhere: branded SVG in the admin menu and block inserter, and a shiny gradient marker on the map with a subtle drop animation.

= 1.0.3 =
* Fixed: the "Save Changes" button did not submit under its expected name, so settings were never stored. Saving a changed city name now also looks up its coordinates automatically.

= 1.0.2 =
* City-based centering: type a city or place name in the settings, the block sidebar or the shortcode (`center="…"`) — coordinates are looked up via OpenStreetMap Nominatim and cached for 30 days.

= 1.0.1 =
* Settings page is now also available under Settings → OSM Points.
* Added a "Settings" link on the Plugins page row.

= 1.0.0 =
* First release: shortcode, Gutenberg block, public point submission, admin dashboard, CSV export.
