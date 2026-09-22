<?php
/**
 * OSM Easy Points — uninstall cleanup.
 * Removes the points table and all options. Warns users in the readme that
 * uninstalling deletes all points.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'osm_easy_points';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'oep_options' );
delete_option( 'oep_db_version' );

// Clear any rate-limit and geocode-cache transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_oep\\_%' OR option_name LIKE '\\_transient\\_timeout\\_oep\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
