# OSM Easy Points — WordPress + OpenStreetMap

A WordPress plugin that puts a **collaborative OpenStreetMap on any page**. Visitors add points with text — **no login, no permission needed**.

## Why this plugin

- **Works with any editor** — the `[osm_easy_points]` shortcode renders in Classic Editor, Elementor, Divi, Bricks, shortcode blocks… and there's a native **Gutenberg block** with live map preview.
- **Truly open editing** — anyone can click the map, drop a pin and write a title + text. No account, no approval, no captcha.
- **Icon pins** — visitors pick an icon when adding a point: standard, shower 🚿, food 🍕, music 🎵, art 🎨, heart ❤️, clothes 👕, sleep 😴, neutral 😐, care 🤲, listen 👂. Each icon gets its own colored pin, shown on the map, in popups, the points list and the admin table.
- **Your data stays yours** — points live in a custom table in the WordPress database. No API key, no external service, no tracking. The map also keeps a small browser-side cache, so a temporary server hiccup can never blank it, and it quietly refreshes every minute to pick up points added by others.
- **City-based centering** — type a city or place name instead of coordinates; converted via OSM Nominatim (cached 30 days, falls back to stored coordinates offline).
- **Clickable links** — URLs in point texts become real links automatically (escaped, open in a new tab, `nofollow`).
- **Built-in abuse protection** — honeypot field, per-visitor rate limiting (filterable), input sanitization, admin-only deletion.

## Quick start

1. Copy the `osm-easy-points` folder to `wp-content/plugins/` (or install the zip).
2. Activate **OSM Easy Points**.
3. Open the settings: **OSM Points → Settings** in the admin sidebar (a mirror also appears under *Settings → OSM Points*, and as a "Settings" link on the Plugins page). Type your city as the default map center — coordinates are looked up automatically.
4. Drop the **OSM Map** block or `[osm_easy_points]` into any post or page. Done.

## Usage

### Block editor
Insert the **OSM Map** block. Use the sidebar to set center, zoom, height, max points and whether visitors may add points. The editor shows a live map preview with your existing points.

### Shortcode (works everywhere)
```
[osm_easy_points]
[osm_easy_points center="Times Square, New York" zoom="14" height="520" edit="yes" limit="1000"]
```

| Attribute | Default      | Meaning                          |
|-----------|--------------|----------------------------------|
| `center`  | setting      | City / place name (e.g. `Vienna`), geocoded via OpenStreetMap |
| `lat`     | setting      | Center latitude (overrides `center`) |
| `lng`     | setting      | Center longitude (overrides `center`) |
| `zoom`    | setting      | 1 (world) – 19 (house level)     |
| `height`  | setting      | Map height in px                 |
| `edit`    | setting      | `yes`/`no` — public adding       |
| `limit`   | setting      | Max points shown                 |

### What visitors see
- Click the map → the add-point form opens at that spot with a preview pin.
- Title (optional), text, icon of your choice, optional display name → **Save point** → pin appears immediately.
- ☰ button: searchable list of all points, click to fly there.
- ◎ button: show my location.
- Admins (logged in) see a **Delete** button in every popup.

## Admin

- **OSM Points → Points** — table of all points with coordinates (linked to openstreetmap.org), text preview, delete action, **CSV export**.
- **OSM Points → Settings** — defaults + public-editing on/off.
- Uninstalling removes the table and all points (see `uninstall.php`).

## REST API

| Endpoint | Method | Auth            | Purpose        |
|----------|--------|-----------------|----------------|
| `/wp-json/osm-easy-points/v1/points` | GET  | public | List points (`?limit=`, `?bbox=s,w,n,e`) |
| `/wp-json/osm-easy-points/v1/points` | POST | public | Add a point (`lat`, `lng`, `name`, `text`, `icon`, `author`) |
| `/wp-json/osm-easy-points/v1/points/{id}` | DELETE | admin (nonce) | Remove a point |

## Technical notes

- **Leaflet 1.9.4** from unpkg; tile styles: OSM standard, Esri World Imagery, OpenTopoMap.
- DB table `{prefix}osm_easy_points`: lat/lng (DECIMAL 10,7), name, text, icon, author, salted-IP hash (rate limiting only — never displayed), created_at. The schema self-checks on every REST request, so upgrades can never leave the table half-migrated.
- Browser cache: after every successful load, the point list is mirrored to `localStorage` (key `oep_points_…`) and used as a fallback if a later request fails. Cleared implicitly when a fresh list arrives.
- Geocoding cache: transients `oep_geo_<md5>` (30 days). Deleted on uninstall.
- Rate limit: 30 points/hour per visitor, override with `oep_rate_limit_per_hour` filter.
- Text domain `osm-easy-points` — fully translatable.
- Requires WordPress 5.8+, PHP 7.2+.
