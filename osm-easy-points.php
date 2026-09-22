<?php
/**
 * Plugin Name:       OSM Easy Points
 * Plugin URI:        https://github.com/osm-easy-points
 * Description:       Interactive OpenStreetMap for any editor (shortcode or block). Anyone can add points with text — no login, no permission needed.
 * Version:           1.1.2
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            OSM Easy Points
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       osm-easy-points
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OEP_VERSION', '1.1.2' );
define( 'OEP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OEP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OEP_REST_NS', 'osm-easy-points/v1' );
define( 'OEP_TABLE', 'osm_easy_points' );

/* -------------------------------------------------------------------------
 * Activation / DB
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'oep_activate' );

function oep_table_name() {
	global $wpdb;
	return $wpdb->prefix . OEP_TABLE;
}

function oep_create_table() {
	global $wpdb;

	$table   = oep_table_name();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		lat DECIMAL(10,7) NOT NULL,
		lng DECIMAL(10,7) NOT NULL,
		name VARCHAR(120) NOT NULL DEFAULT '',
		text TEXT NULL,
		icon VARCHAR(20) NOT NULL DEFAULT '',
		author VARCHAR(60) NOT NULL DEFAULT '',
		ip_hash VARCHAR(32) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY lat (lat),
		KEY lng (lng)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'oep_db_version', OEP_VERSION );
}

function oep_activate() {
	oep_create_table();
	add_option( 'oep_options', oep_default_options() );
}

// Existing installs: add the icon column if it is missing.
add_action( 'admin_init', 'oep_maybe_upgrade_db' );

function oep_maybe_upgrade_db() {
	if ( get_option( 'oep_db_version' ) === OEP_VERSION ) {
		return;
	}
	oep_create_table();
}

/**
 * The icon palette shown in the "Add a point" form. Filter with
 * 'oep_point_icons' to add/remove/translate entries.
 */
function oep_point_icons() {
	$icons = array(
		''        => array( 'emoji' => '',        'label' => __( 'Standard', 'osm-easy-points' ), 'color' => '#d3242c' ),
		'shower'  => array( 'emoji' => '🚿',      'label' => __( 'Shower', 'osm-easy-points' ),   'color' => '#1a9bd7' ),
		'food'    => array( 'emoji' => '🍕',      'label' => __( 'Food', 'osm-easy-points' ),     'color' => '#e8762d' ),
		'music'   => array( 'emoji' => '🎵',      'label' => __( 'Music', 'osm-easy-points' ),    'color' => '#8e44ad' ),
		'art'     => array( 'emoji' => '🎨',      'label' => __( 'Art', 'osm-easy-points' ),      'color' => '#d81b7a' ),
		'heart'   => array( 'emoji' => '❤️',      'label' => __( 'Heart', 'osm-easy-points' ),    'color' => '#e0245e' ),
		'clothes' => array( 'emoji' => '👕',      'label' => __( 'Clothes', 'osm-easy-points' ),  'color' => '#0e9f6e' ),
		'sleep'   => array( 'emoji' => '😴',      'label' => __( 'Sleep', 'osm-easy-points' ),    'color' => '#4f46e5' ),
		'neutral' => array( 'emoji' => '😐',      'label' => __( 'Neutral', 'osm-easy-points' ),  'color' => '#6b7280' ),
		'care'    => array( 'emoji' => '🤲',      'label' => __( 'Care', 'osm-easy-points' ),     'color' => '#0d9488' ),
		'listen'  => array( 'emoji' => '👂',      'label' => __( 'Listen', 'osm-easy-points' ),   'color' => '#ca8a04' ),
	);

	/**
	 * Filters the icon palette.
	 *
	 * @param array $icons key => array( emoji, label, color ).
	 */
	return apply_filters( 'oep_point_icons', $icons );
}

function oep_sanitize_icon( $value ) {
	$key = sanitize_key( (string) $value );
	$icons = oep_point_icons();
	return isset( $icons[ $key ] ) ? $key : '';
}

add_action( 'plugins_loaded', 'oep_maybe_upgrade' );

function oep_maybe_upgrade() {
	if ( get_option( 'oep_db_version' ) !== OEP_VERSION ) {
		oep_create_table();
	}
}

/**
 * Columns the REST layer reads and writes, in SELECT order.
 * Single source of truth so the GET endpoint and the schema checks below
 * can never drift apart (that mismatch was one cause of "saved points
 * vanish after reload").
 */
function oep_point_columns() {
	return array( 'id', 'lat', 'lng', 'name', 'text', 'icon', 'author', 'created_at' );
}

/**
 * Explicit self-heal for the icon column: dbDelta is notoriously picky and
 * can silently skip new columns (formatting quirks, exotic MySQL setups…).
 * A missing column would break INSERTs entirely — points would be "saved"
 * without ever landing in the database. This guarantees the column exists
 * before the REST layer needs it.
 */
add_action( 'rest_api_init', 'oep_ensure_icon_column', 0 );

function oep_ensure_icon_column() {
	global $wpdb;

	$table = oep_table_name();
	static $checked = false;
	if ( $checked ) {
		return;
	}
	$checked = true;

	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
	if ( ! empty( $columns ) && ! in_array( 'icon', (array) $columns, true ) ) {
		$charset = $wpdb->get_charset_collate();
		// No user input involved — the table name comes from our own constants.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN icon VARCHAR(20) NOT NULL DEFAULT '' {$charset}" );
		update_option( 'oep_db_version', OEP_VERSION );
	}
}

/* -------------------------------------------------------------------------
 * Options
 * ---------------------------------------------------------------------- */

function oep_default_options() {
	return array(
		'center_name'  => 'Vienna, Austria',
		'lat'          => 48.2082,
		'lng'          => 16.3738,
		'zoom'         => 13,
		'height'       => 480,
		'tileset'      => 'osm',
		'edit_enabled' => 1,
		'max_points'   => 500,
	);
}

function oep_get_options() {
	$options = get_option( 'oep_options', array() );
	$options = wp_parse_args( is_array( $options ) ? $options : array(), oep_default_options() );

	$options['lat']          = max( -90.0, min( 90.0, (float) $options['lat'] ) );
	$options['lng']          = max( -180.0, min( 180.0, (float) $options['lng'] ) );
	$options['center_name']  = isset( $options['center_name'] ) ? sanitize_text_field( (string) $options['center_name'] ) : '';
	$options['zoom']         = max( 1, min( 19, (int) $options['zoom'] ) );
	$options['height']       = max( 200, min( 1200, (int) $options['height'] ) );
	$options['tileset']      = array_key_exists( $options['tileset'], oep_tilesets() ) ? $options['tileset'] : 'osm';
	$options['edit_enabled'] = empty( $options['edit_enabled'] ) ? 0 : 1;
	$options['max_points']   = max( 10, min( 5000, (int) $options['max_points'] ) );

	return $options;
}

/**
 * Geocode a free-text place name ("Vienna", "Brandenburg Gate, Berlin") to
 * coordinates using OSM Nominatim — no API key. Results are cached in a
 * transient for 30 days so page loads never hit the service twice.
 *
 * @return array{lat: float, lng: float, display: string}|WP_Error
 */
function oep_geocode( $query ) {
	$query = trim( (string) $query );
	if ( '' === $query ) {
		return new WP_Error( 'oep_geocode_empty', __( 'Please enter a place name.', 'osm-easy-points' ) );
	}

	$cache_key = 'oep_geo_' . md5( mb_strtolower( $query ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' . rawurlencode( $query ),
		array(
			'timeout' => 10,
			'headers' => array( 'User-Agent' => 'osm-easy-points/' . OEP_VERSION . ' (WordPress plugin)' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'oep_geocode_failed', __( 'Could not reach the OpenStreetMap search service. Please try again or use exact coordinates instead.', 'osm-easy-points' ) );
	}

	$results = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $results ) || empty( $results[0]['lat'] ) || empty( $results[0]['lon'] ) ) {
		return new WP_Error( 'oep_geocode_not_found', __( 'Place not found. Try a better-known name or add the country, e.g. “Springfield, Illinois, USA”.', 'osm-easy-points' ) );
	}

	$found = array(
		'lat'     => (float) $results[0]['lat'],
		'lng'     => (float) $results[0]['lon'],
		'display' => (string) $results[0]['display_name'],
	);
	set_transient( $cache_key, $found, 30 * DAY_IN_SECONDS );
	return $found;
}

function oep_tilesets() {
	return array(
		'osm'       => __( 'OpenStreetMap (standard)', 'osm-easy-points' ),
		'satellite' => __( 'Satellite (Esri World Imagery)', 'osm-easy-points' ),
		'topo'      => __( 'Terrain (OpenTopoMap)', 'osm-easy-points' ),
	);
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

/**
 * Enqueue on every frontend page: map styles enqueued during content render
 * (wp_head already sent) would be silently dropped, so we load up front.
 */
add_action( 'wp_enqueue_scripts', 'oep_enqueue_map_assets' );

add_action( 'init', 'oep_register_assets' );

function oep_register_assets() {
	wp_register_style( 'oep-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_register_script( 'oep-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

	wp_register_style( 'oep-map', OEP_PLUGIN_URL . 'assets/map.css', array( 'oep-leaflet' ), OEP_VERSION );
	wp_register_script( 'oep-map', OEP_PLUGIN_URL . 'assets/map.js', array( 'oep-leaflet' ), OEP_VERSION, true );

	wp_localize_script( 'oep-map', 'oepSettings', oep_frontend_data() );
}

function oep_enqueue_map_assets() {
	wp_enqueue_style( 'oep-map' );
	wp_enqueue_script( 'oep-map' );
}

function oep_frontend_data() {
	$o = oep_get_options();

	return array(
		'restUrl'  => esc_url_raw( rest_url( OEP_REST_NS . '/points' ) ),
		// Only logged-in users get a nonce: anonymous visitors don't need one
		// (their requests are unauthenticated), and cached pages would serve
		// stale nonces that make WordPress reject their submissions.
		'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'canDelete' => current_user_can( 'manage_options' ) ? 1 : 0,
		'tileset'  => $o['tileset'],
		'icons'    => oep_point_icons(),
		'defaults' => array(
			'name'   => $o['center_name'],
			'lat'    => $o['lat'],
			'lng'    => $o['lng'],
			'zoom'   => $o['zoom'],
			'height' => $o['height'],
		),
		'i18n'     => array(
			'addPoint'      => __( 'Add a point', 'osm-easy-points' ),
			'addingHint'    => __( 'Click the map to place your point', 'osm-easy-points' ),
			'cancel'        => __( 'Cancel', 'osm-easy-points' ),
			'save'          => __( 'Save point', 'osm-easy-points' ),
			'saving'        => __( 'Saving…', 'osm-easy-points' ),
			'namePh'        => __( 'Title (optional)', 'osm-easy-points' ),
			'textPh'        => __( 'What is here? Tell the world…', 'osm-easy-points' ),
			'authorPh'      => __( 'Your name (optional)', 'osm-easy-points' ),
			'thanks'        => __( 'Thanks! Your point is on the map.', 'osm-easy-points' ),
			'error'         => __( 'Could not save the point. Please try again.', 'osm-easy-points' ),
			'slowDown'      => __( 'You are adding points too fast. Please wait a moment.', 'osm-easy-points' ),
			'pointsLabel'   => __( 'points', 'osm-easy-points' ),
			'searchPh'      => __( 'Search points…', 'osm-easy-points' ),
			'myLocation'    => __( 'Show my location', 'osm-easy-points' ),
			'untitled'      => __( 'Untitled point', 'osm-easy-points' ),
			'anonymous'     => __( 'Anonymous', 'osm-easy-points' ),
			'delete'        => __( 'Delete', 'osm-easy-points' ),
			'confirmDelete' => __( 'Delete this point permanently?', 'osm-easy-points' ),
			'deleted'       => __( 'Point deleted.', 'osm-easy-points' ),
			'noPoints'      => __( 'No points yet — be the first!', 'osm-easy-points' ),
			'newPoint'      => __( 'New point', 'osm-easy-points' ),
			'showList'      => __( 'Points', 'osm-easy-points' ),
		),
	);
}

/* -------------------------------------------------------------------------
 * Shortcode: [osm_easy_points]
 * ---------------------------------------------------------------------- */

add_shortcode( 'osm_easy_points', 'oep_shortcode' );

function oep_shortcode( $atts ) {
	$o = oep_get_options();

	$a = shortcode_atts(
		array(
			'center' => '',
			'lat'    => $o['lat'],
			'lng'    => $o['lng'],
			'zoom'   => $o['zoom'],
			'height' => $o['height'],
			'edit'   => $o['edit_enabled'] ? 'yes' : 'no',
			'limit'  => $o['max_points'],
		),
		$atts,
		'osm_easy_points'
	);

	// center="Vienna" (or any place name) wins over the defaults, but an
	// explicitly given lat/lng in the shortcode wins over the place name.
	$place = trim( (string) $a['center'] );
	if ( '' !== $place && ! isset( $atts['lat'] ) && ! isset( $atts['lng'] ) ) {
		$geo = oep_geocode( $place );
		if ( ! is_wp_error( $geo ) ) {
			$a['lat'] = $geo['lat'];
			$a['lng'] = $geo['lng'];
		}
	}

	oep_enqueue_map_assets();

	static $instance = 0;
	$instance++;

	$data  = ' data-lat="' . esc_attr( (float) $a['lat'] ) . '"';
	$data .= ' data-lng="' . esc_attr( (float) $a['lng'] ) . '"';
	$data .= ' data-zoom="' . esc_attr( (int) $a['zoom'] ) . '"';
	$data .= ' data-height="' . esc_attr( (int) $a['height'] ) . '"';
	$data .= ' data-edit="' . esc_attr( filter_var( $a['edit'], FILTER_VALIDATE_BOOLEAN ) ? '1' : '0' ) . '"';
	$data .= ' data-limit="' . esc_attr( (int) $a['limit'] ) . '"';
	$data .= ' data-instance="' . (int) $instance . '"';

	return '<div class="oep-map"' . $data . '><noscript>' . esc_html__( 'The map requires JavaScript.', 'osm-easy-points' ) . '</noscript></div>';
}

/* -------------------------------------------------------------------------
 * Gutenberg block (server-registered, editor script enqueued separately)
 * ---------------------------------------------------------------------- */

add_action( 'init', 'oep_register_block' );

function oep_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type(
		'osm-easy-points/map',
		array(
			'editor_script'   => 'oep-block-editor',
			'render_callback' => 'oep_block_render',			'attributes'      => array(
			'name'      => array( 'type' => 'string' ),
			'lat'       => array( 'type' => 'number' ),
				'lng'       => array( 'type' => 'number' ),
				'zoom'      => array( 'type' => 'number' ),
				'height'    => array( 'type' => 'number' ),
				'edit'      => array( 'type' => 'boolean', 'default' => true ),
				'limit'     => array( 'type' => 'number' ),
				'editExplicit' => array( 'type' => 'boolean', 'default' => false ),
			),
			'supports'        => array(
				'align' => array( 'wide', 'full' ),
				'html'  => false,
			),
		)
	);
}

add_action( 'enqueue_block_editor_assets', 'oep_enqueue_block_editor_assets' );

function oep_enqueue_block_editor_assets() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_enqueue_style( 'oep-leaflet' );
	wp_enqueue_script( 'oep-leaflet' );

	$data            = oep_frontend_data();
	$data['defaults']['edit'] = (bool) oep_get_options()['edit_enabled'];

	wp_enqueue_script(
		'oep-block-editor',
		OEP_PLUGIN_URL . 'block/index.js',
		array( 'oep-leaflet', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
		OEP_VERSION,
		true
	);
	wp_localize_script( 'oep-block-editor', 'oepSettings', $data );
}

function oep_block_render( $attributes ) {
	$o = oep_get_options();

	$defaults = array(
		'lat'    => $o['lat'],
		'lng'    => $o['lng'],
		'zoom'   => $o['zoom'],
		'height' => $o['height'],
		'edit'   => (bool) $o['edit_enabled'],
		'limit'  => $o['max_points'],
	);

	foreach ( $defaults as $key => $value ) {
		if ( isset( $attributes[ $key ] ) && '' !== $attributes[ $key ] ) {
			$defaults[ $key ] = $attributes[ $key ];
		}
	}

	// A place name typed into the block sidebar wins over the defaults, but
	// explicit lat/lng attributes win over the place name.
	$place = isset( $attributes['name'] ) ? trim( (string) $attributes['name'] ) : '';
	if ( '' !== $place && ! isset( $attributes['lat'] ) && ! isset( $attributes['lng'] ) ) {
		$geo = oep_geocode( $place );
		if ( ! is_wp_error( $geo ) ) {
			$defaults['lat'] = $geo['lat'];
			$defaults['lng'] = $geo['lng'];
		}
	}

	if ( ! empty( $attributes['editExplicit'] ) ) {
		$defaults['edit'] = ! empty( $attributes['edit'] );
	}

	oep_enqueue_map_assets();

	static $instance = 0;
	$instance++;

	$data  = ' data-lat="' . esc_attr( (float) $defaults['lat'] ) . '"';
	$data .= ' data-lng="' . esc_attr( (float) $defaults['lng'] ) . '"';
	$data .= ' data-zoom="' . esc_attr( (int) $defaults['zoom'] ) . '"';
	$data .= ' data-height="' . esc_attr( (int) $defaults['height'] ) . '"';
	$data .= ' data-edit="' . ( ! empty( $defaults['edit'] ) ? '1' : '0' ) . '"';
	$data .= ' data-limit="' . esc_attr( (int) $defaults['limit'] ) . '"';
	$data .= ' data-instance="b' . (int) $instance . '"';

	return '<div class="oep-map"' . $data . '><noscript>' . esc_html__( 'The map requires JavaScript.', 'osm-easy-points' ) . '</noscript></div>';
}

/* -------------------------------------------------------------------------
 * REST API — public read, public write (no login needed), admin-only delete
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', 'oep_register_rest' );

function oep_register_rest() {
	register_rest_route(
		OEP_REST_NS,
		'/points',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'oep_rest_get_points',
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'default'           => 500,
						'sanitize_callback' => 'absint',
					),
					'bbox'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'oep_rest_add_point',
				'permission_callback' => '__return_true',
				'args'                => array(
					'lat'    => array(
						'required'          => true,
						'validate_callback' => 'oep_validate_lat',
					),
					'lng'    => array(
						'required'          => true,
						'validate_callback' => 'oep_validate_lng',
					),
					'name'   => array(
						'required'          => false,
						'sanitize_callback' => 'oep_sanitize_name',
					),
					'text'   => array(
						'required'          => false,
						'sanitize_callback' => 'oep_sanitize_point_text',
					),
					'author' => array(
						'required'          => false,
						'sanitize_callback' => 'oep_sanitize_author',
					),
					'icon'   => array(
						'required'          => false,
						'sanitize_callback' => 'oep_sanitize_icon',
					),
				),
			),
		)
	);

	register_rest_route(
		OEP_REST_NS,
		'/points/(?P<id>\d+)',
		array(
			'methods'             => 'DELETE',
			'callback'            => 'oep_rest_delete_point',
			'permission_callback' => 'oep_can_delete_points',
		)
	);
}

function oep_validate_lat( $value ) {
	return is_numeric( $value ) && (float) $value >= -90 && (float) $value <= 90;
}

function oep_validate_lng( $value ) {
	return is_numeric( $value ) && (float) $value >= -180 && (float) $value <= 180;
}

function oep_sanitize_name( $value ) {
	return mb_substr( sanitize_text_field( (string) $value ), 0, 120 );
}

function oep_sanitize_point_text( $value ) {
	return mb_substr( sanitize_textarea_field( (string) $value ), 0, 2000 );
}

function oep_sanitize_author( $value ) {
	return mb_substr( sanitize_text_field( (string) $value ), 0, 60 );
}

function oep_can_delete_points() {
	return current_user_can( 'manage_options' );
}

function oep_client_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return md5( $ip . wp_salt( 'nonce' ) );
}

function oep_is_rate_limited() {
	$limit = (int) apply_filters( 'oep_rate_limit_per_hour', 30 );
	if ( $limit <= 0 ) {
		return false;
	}

	$key   = 'oep_rl_' . oep_client_ip_hash();
	$count = (int) get_transient( $key );

	if ( $count >= $limit ) {
		return true;
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

function oep_point_to_rest( $row ) {
	return array(
		'id'      => (int) $row->id,
		'lat'     => (float) $row->lat,
		'lng'     => (float) $row->lng,
		'name'    => (string) $row->name,
		'text'    => (string) $row->text,
		'icon'    => isset( $row->icon ) ? (string) $row->icon : '',
		'author'  => (string) $row->author,
		'created' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ),
	);
}

function oep_rest_get_points( $request ) {
	global $wpdb;

	$table = oep_table_name();
	$limit = min( 5000, max( 1, (int) $request->get_param( 'limit' ) ) );

	$where  = '';
	$params = array();

	$bbox = (string) $request->get_param( 'bbox' );
	if ( $bbox ) {
		$parts = array_map( 'trim', explode( ',', $bbox ) );
		if ( 4 === count( $parts ) && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) && is_numeric( $parts[2] ) && is_numeric( $parts[3] ) ) {
			$where   = ' WHERE lat >= %f AND lat <= %f AND lng >= %f AND lng <= %f';
			$params  = array( (float) $parts[0], (float) $parts[2], (float) $parts[1], (float) $parts[3] );
		}
	}

	$cols     = implode( ', ', oep_point_columns() );
	$sql      = "SELECT {$cols} FROM {$table}{$where} ORDER BY id DESC LIMIT %d";
	$params[] = $limit;
	$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	$points = array();
	foreach ( (array) $rows as $row ) {
		$points[] = oep_point_to_rest( $row );
	}

	// Freshness headers: the point list must never be cached anywhere
	// (browser, proxy, page-cache plugin) — a cached list is exactly the
	// "my points disappeared after reload" experience.
	if ( ! headers_sent() ) {
		nocache_headers();
	}

	return rest_ensure_response(
		array(
			'points' => $points,
			'total'  => count( $points ),
		)
	);
}

function oep_rest_add_point( $request ) {
	// Honeypot: real users never fill this hidden field.
	if ( '' !== trim( (string) $request->get_param( 'hp' ) ) ) {
		return new WP_Error( 'oep_spam', __( 'Submission rejected.', 'osm-easy-points' ), array( 'status' => 400 ) );
	}

	if ( oep_is_rate_limited() ) {
		return new WP_Error( 'oep_rate_limited', __( 'Too many points from your network. Please try again later.', 'osm-easy-points' ), array( 'status' => 429 ) );
	}

	$lat  = (float) $request->get_param( 'lat' );
	$lng  = (float) $request->get_param( 'lng' );
	$name = (string) $request->get_param( 'name' );
	$text = (string) $request->get_param( 'text' );
	$who  = (string) $request->get_param( 'author' );
	$icon = (string) $request->get_param( 'icon' );

	if ( '' === $name && '' === $text ) {
		return new WP_Error( 'oep_empty', __( 'Please add a title or some text for your point.', 'osm-easy-points' ), array( 'status' => 400 ) );
	}

	global $wpdb;
	$table = oep_table_name();

	$inserted = $wpdb->insert(
		$table,
		array(
			'lat'        => $lat,
			'lng'        => $lng,
			'name'       => $name,
			'text'       => $text,
			'icon'       => $icon,
			'author'     => $who,
			'ip_hash'    => oep_client_ip_hash(),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		return new WP_Error( 'oep_db_error', __( 'Could not save the point.', 'osm-easy-points' ), array( 'status' => 500 ) );
	}

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT " . implode( ', ', oep_point_columns() ) . " FROM {$table} WHERE id = %d", $wpdb->insert_id ) );

	$response = rest_ensure_response( oep_point_to_rest( $row ) );
	$response->set_status( 201 );
	return $response;
}

function oep_rest_delete_point( $request ) {
	global $wpdb;
	$table = oep_table_name();

	$deleted = $wpdb->delete( $table, array( 'id' => (int) $request['id'] ), array( '%d' ) );

	if ( ! $deleted ) {
		return new WP_Error( 'oep_not_found', __( 'Point not found.', 'osm-easy-points' ), array( 'status' => 404 ) );
	}

	return rest_ensure_response( array( 'deleted' => true ) );
}

/* -------------------------------------------------------------------------
 * Admin: points dashboard + settings + CSV export
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'oep_admin_menu' );

function oep_admin_menu() {
	// Custom location-pin icon (inherits the admin color scheme via currentColor).
	$icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zM14.5 9a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z"/></svg>';

	add_menu_page(
		__( 'OSM Easy Points', 'osm-easy-points' ),
		__( 'OSM Points', 'osm-easy-points' ),
		'manage_options',
		'osm-easy-points',
		'oep_render_admin_points',
		'data:image/svg+xml;base64,' . base64_encode( $icon_svg ),
		58
	);

	add_submenu_page(
		'osm-easy-points',
		__( 'Points', 'osm-easy-points' ),
		__( 'Points', 'osm-easy-points' ),
		'manage_options',
		'osm-easy-points',
		'oep_render_admin_points'
	);

	add_submenu_page(
		'osm-easy-points',
		__( 'Settings', 'osm-easy-points' ),
		__( 'Settings', 'osm-easy-points' ),
		'manage_options',
		'osm-easy-points-settings',
		'oep_render_admin_settings'
	);

	// Mirror the settings page under the standard "Settings" menu so it's
	// easy to find (same slug → same page as the top-level entry).
	add_options_page(
		__( 'OSM Easy Points — Settings', 'osm-easy-points' ),
		__( 'OSM Points', 'osm-easy-points' ),
		'manage_options',
		'osm-easy-points-settings',
		'oep_render_admin_settings'
	);
}

/**
 * "Settings" quick link on the Plugins list row.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'oep_plugin_action_links' );

function oep_plugin_action_links( $links ) {
	$url = admin_url( 'admin.php?page=osm-easy-points-settings' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'osm-easy-points' ) . '</a>' );
	return $links;
}

add_action( 'admin_init', 'oep_admin_handle_actions' );

function oep_admin_handle_actions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Delete a single point from the dashboard.
	if ( isset( $_GET['page'], $_GET['oep_action'] ) && 'osm-easy-points' === $_GET['page'] && 'delete' === $_GET['oep_action'] ) {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'oep_delete_' . $id );

		global $wpdb;
		$wpdb->delete( oep_table_name(), array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( add_query_arg( 'oep_msg', 'deleted', admin_url( 'admin.php?page=osm-easy-points' ) ) );
		exit;
	}

	// Save settings.
	if ( isset( $_POST['oep_save_settings'] ) ) {
		check_admin_referer( 'oep_save_settings' );

		$old_options = oep_get_options();

		$name     = isset( $_POST['center_name'] ) ? sanitize_text_field( wp_unslash( $_POST['center_name'] ) ) : '';
		$lat_post = isset( $_POST['lat'] ) && '' !== (string) $_POST['lat'] ? (float) $_POST['lat'] : (float) $old_options['lat'];
		$lng_post = isset( $_POST['lng'] ) && '' !== (string) $_POST['lng'] ? (float) $_POST['lng'] : (float) $old_options['lng'];

		// If the place name changed but the coordinates stayed untouched, look
		// the new name up server-side — saving should "just work" without the
		// "Find coordinates" button. Cached in oep_geocode(), so repeats are free.
		$geo_failed = false;
		if ( '' !== $name && $name !== $old_options['center_name']
			&& (float) $old_options['lat'] === $lat_post && (float) $old_options['lng'] === $lng_post ) {
			$geo = oep_geocode( $name );
			if ( is_wp_error( $geo ) ) {
				$geo_failed = true; // Keep the previous coordinates.
			} else {
				$lat_post = $geo['lat'];
				$lng_post = $geo['lng'];
			}
		}

		$options                 = $old_options;
		$options['center_name']  = $name;
		$options['lat']          = $lat_post;
		$options['lng']          = $lng_post;
		$options['zoom']         = isset( $_POST['zoom'] ) ? (int) $_POST['zoom'] : $options['zoom'];
		$options['height']       = isset( $_POST['height'] ) ? (int) $_POST['height'] : $options['height'];
		$options['tileset']      = isset( $_POST['tileset'] ) ? sanitize_key( $_POST['tileset'] ) : $options['tileset'];
		$options['edit_enabled'] = empty( $_POST['edit_enabled'] ) ? 0 : 1;
		$options['max_points']   = isset( $_POST['max_points'] ) ? (int) $_POST['max_points'] : $options['max_points'];

		update_option( 'oep_options', $options, false );

		$msg = $geo_failed ? 'saved_geocode_failed' : 'saved';
		wp_safe_redirect( add_query_arg( 'oep_msg', $msg, admin_url( 'admin.php?page=osm-easy-points-settings' ) ) );
		exit;
	}
}

add_action( 'admin_post_oep_export_csv', 'oep_admin_export_csv' );

function oep_admin_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'osm-easy-points' ) );
	}
	check_admin_referer( 'oep_export_csv' );

	global $wpdb;
	$table = oep_table_name();
	$rows  = $wpdb->get_results( "SELECT id, lat, lng, name, text, icon, author, created_at FROM {$table} ORDER BY id ASC" );

	$filename = 'osm-easy-points-' . gmdate( 'Ymd-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
	fputcsv( $out, array( 'id', 'lat', 'lng', 'name', 'icon', 'author', 'text', 'created_at' ) );

	foreach ( (array) $rows as $row ) {
		fputcsv(
			$out,
			array(						$row->id,
						$row->lat,
						$row->lng,
						$row->name,
						isset( $row->icon ) ? $row->icon : '',
						$row->author,
						$row->text,
						$row->created_at,
			)
		);
	}

	fclose( $out );
	exit;
}

function oep_admin_notices_inline() {
	if ( isset( $_GET['oep_msg'] ) ) {
		$msg = sanitize_key( $_GET['oep_msg'] );
		if ( 'deleted' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Point deleted.', 'osm-easy-points' ) . '</p></div>';			} elseif ( 'saved' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'osm-easy-points' ) . '</p></div>';
			} elseif ( 'saved_geocode_failed' === $msg ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Settings saved, but the place name could not be looked up — the previous coordinates were kept. Try "Find coordinates" or exact values.', 'osm-easy-points' ) . '</p></div>';
			}
	}
}

function oep_render_admin_points() {
	global $wpdb;
	$table = oep_table_name();

	$rows = $wpdb->get_results( "SELECT id, lat, lng, name, text, icon, author, created_at FROM {$table} ORDER BY id DESC LIMIT 200" );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'OSM Easy Points — Points', 'osm-easy-points' ); ?></h1>
		<?php oep_admin_notices_inline(); ?>

		<p>
			<span class="description">
				<?php
				printf(
					/* translators: %s: number of points */
					esc_html( _n( '%s point on the map.', '%s points on the map.', $total, 'osm-easy-points' ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</span>
			&nbsp;|&nbsp;
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oep_export_csv' ), 'oep_export_csv' ) ); ?>"><?php esc_html_e( 'Export all as CSV', 'osm-easy-points' ); ?></a>
		</p>

		<h2 style="margin-top:1.5em;"><?php esc_html_e( 'Add the map to any post or page', 'osm-easy-points' ); ?></h2>
		<p>
			<code>[osm_easy_points]</code>&nbsp;
			<button type="button" class="button button-small" onclick="var i=document.getElementById('oep-sc-help');i.style.display='block';"><?php esc_html_e( 'More options', 'osm-easy-points' ); ?></button>
		</p>
		<div id="oep-sc-help" style="display:none;background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:640px;">
			<p><code>[osm_easy_points lat="48.2082" lng="16.3738" zoom="13" height="480" edit="yes" limit="500"]</code></p>
			<p><?php esc_html_e( 'All attributes are optional — they override the defaults from the settings page. Use the “OSM Map” block in the block editor for point-and-click setup.', 'osm-easy-points' ); ?></p>
		</div>

		<table class="widefat striped" style="margin-top:1.5em;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'osm-easy-points' ); ?></th>
					<th><?php esc_html_e( 'Icon', 'osm-easy-points' ); ?></th>
					<th><?php esc_html_e( 'Title', 'osm-easy-points' ); ?></th>
					<th><?php esc_html_e( 'Text', 'osm-easy-points' ); ?></th>
					<th><?php esc_html_e( 'Author', 'osm-easy-points' ); ?></th>
					<th><?php esc_html_e( 'Coordinates', 'osm-easy-points' ); ?></th>
					<th><?php esc_html_e( 'Date', 'osm-easy-points' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No points yet. Share a page with the map and watch them appear!', 'osm-easy-points' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$delete_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'       => 'osm-easy-points',
									'oep_action' => 'delete',
									'id'         => (int) $row->id,
								),
								admin_url( 'admin.php' )
							),
							'oep_delete_' . (int) $row->id
						);
						?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
						<td><?php
							$icons = oep_point_icons();
							$ikey  = isset( $row->icon ) ? (string) $row->icon : '';
							if ( '' !== $ikey && isset( $icons[ $ikey ] ) ) {
								echo '<span style="font-size:16px;" title="' . esc_attr( $icons[ $ikey ]['label'] ) . '">' . esc_html( $icons[ $ikey ]['emoji'] ) . '</span>';
							} else {
								echo '—';
							}
						?></td>
							<td><strong><?php echo esc_html( '' !== $row->name ? $row->name : __( 'Untitled point', 'osm-easy-points' ) ); ?></strong></td>
							<td><?php echo esc_html( wp_trim_words( (string) $row->text, 18 ) ); ?></td>
							<td><?php echo esc_html( '' !== $row->author ? $row->author : __( 'Anonymous', 'osm-easy-points' ) ); ?></td>
							<td><a href="https://www.openstreetmap.org/?mlat=<?php echo esc_attr( $row->lat ); ?>&amp;mlon=<?php echo esc_attr( $row->lng ); ?>#map=17/<?php echo esc_attr( $row->lat ); ?>/<?php echo esc_attr( $row->lng ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row->lat . ', ' . $row->lng ); ?></a></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) ); ?></td>
							<td><a class="oep-delete-link" style="color:#b32d2e;" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this point permanently?', 'osm-easy-points' ) ); ?>');"><?php esc_html_e( 'Delete', 'osm-easy-points' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Showing the 200 most recent points. Use CSV export for the full list.', 'osm-easy-points' ); ?></p>
	</div>
	<?php
}

function oep_render_admin_settings() {
	$o        = oep_get_options();
	$tilesets = oep_tilesets();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'OSM Easy Points — Settings', 'osm-easy-points' ); ?></h1>
		<?php oep_admin_notices_inline(); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'oep_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="oep-center-name"><?php esc_html_e( 'City / place name', 'osm-easy-points' ); ?></label></th>
					<td>
						<input id="oep-center-name" name="center_name" type="text" value="<?php echo esc_attr( $o['center_name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Vienna, Austria', 'osm-easy-points' ); ?>" />
						<button type="button" class="button" id="oep-geolookup"><?php esc_html_e( 'Find coordinates', 'osm-easy-points' ); ?></button>
						<p class="description" id="oep-geolookup-result"><?php esc_html_e( 'Just type a city (or any address) — the exact coordinates are filled in automatically via OpenStreetMap.', 'osm-easy-points' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Exact coordinates (filled automatically)', 'osm-easy-points' ); ?></th>
					<td>
						<label for="oep-lat" style="margin-right:6px;"><?php esc_html_e( 'Latitude', 'osm-easy-points' ); ?></label>
						<input id="oep-lat" name="lat" type="number" step="0.0000001" min="-90" max="90" value="<?php echo esc_attr( $o['lat'] ); ?>" class="small-text" style="width:9em;" />
						<label for="oep-lng" style="margin:0 6px 0 14px;"><?php esc_html_e( 'Longitude', 'osm-easy-points' ); ?></label>
						<input id="oep-lng" name="lng" type="number" step="0.0000001" min="-180" max="180" value="<?php echo esc_attr( $o['lng'] ); ?>" class="small-text" style="width:9em;" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="oep-zoom"><?php esc_html_e( 'Default zoom', 'osm-easy-points' ); ?></label></th>
					<td>
						<input id="oep-zoom" name="zoom" type="number" min="1" max="19" value="<?php echo esc_attr( $o['zoom'] ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( '1 = world, 19 = house level.', 'osm-easy-points' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="oep-height"><?php esc_html_e( 'Default map height (px)', 'osm-easy-points' ); ?></label></th>
					<td><input id="oep-height" name="height" type="number" min="200" max="1200" value="<?php echo esc_attr( $o['height'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="oep-tileset"><?php esc_html_e( 'Default map style', 'osm-easy-points' ); ?></label></th>
					<td>
						<select id="oep-tileset" name="tileset">
							<?php foreach ( $tilesets as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $o['tileset'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Public editing', 'osm-easy-points' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="edit_enabled" value="1" <?php checked( $o['edit_enabled'], 1 ); ?> />
							<?php esc_html_e( 'Allow anyone (no login) to add points on the map', 'osm-easy-points' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'You can still override this per map with edit="yes" / edit="no" in the shortcode or the block setting.', 'osm-easy-points' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="oep-max-points"><?php esc_html_e( 'Max points shown per map', 'osm-easy-points' ); ?></label></th>
					<td><input id="oep-max-points" name="max_points" type="number" min="10" max="5000" value="<?php echo esc_attr( $o['max_points'] ); ?>" class="small-text" /></td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Changes', 'osm-easy-points' ), 'primary', 'oep_save_settings' ); ?>
		</form>

		<script>
		(function () {
			var btn = document.getElementById('oep-geolookup');
			if (!btn) { return; }
			var input = document.getElementById('oep-center-name');
			var latInput = document.getElementById('oep-lat');
			var lngInput = document.getElementById('oep-lng');
			var out = document.getElementById('oep-geolookup-result');
			var original = out.textContent;
			btn.addEventListener('click', function () {
				var q = input.value.replace(/^\s+|\s+$/g, '');
				if (!q) { return; }
				btn.disabled = true;
				out.textContent = '<?php echo esc_js( __( 'Searching…', 'osm-easy-points' ) ); ?>';
				window.fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
					.then(function (r) { return r.json(); })
					.then(function (results) {
						if (!results || !results.length) {
							out.textContent = '<?php echo esc_js( __( 'Place not found — try adding the country, e.g. “Springfield, Illinois, USA”.', 'osm-easy-points' ) ); ?>';
							return;
						}
						latInput.value = parseFloat(results[0].lat).toFixed(7);
						lngInput.value = parseFloat(results[0].lon).toFixed(7);
						out.textContent = results[0].display_name;
					})
					.catch(function () {
						out.textContent = '<?php echo esc_js( __( 'Search failed — check your connection and try again.', 'osm-easy-points' ) ); ?>';
					})
					.finally(function () { btn.disabled = false; });
			});
		})();
		</script>

		<hr />
		<h2><?php esc_html_e( 'How it works', 'osm-easy-points' ); ?></h2>
		<ul style="list-style:disc;padding-left:20px;">
			<li><?php esc_html_e( 'Points are stored in your own WordPress database — no external service, no API key.', 'osm-easy-points' ); ?></li>
			<li><?php esc_html_e( 'Anyone can add points without an account. Submissions are sanitized and rate-limited (30 per visitor per hour by default — filter “oep_rate_limit_per_hour” to change).', 'osm-easy-points' ); ?></li>
			<li><?php esc_html_e( 'Use [osm_easy_points] in any editor (Classic, Gutenberg, Elementor, page builders…) or the “OSM Map” block.', 'osm-easy-points' ); ?></li>
		</ul>
	</div>
	<?php
}
