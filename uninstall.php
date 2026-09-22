<?php
/**
 * OSM Easy Points - uninstall cleanup.
 *
 * SAFE BY DEFAULT: deleting the plugin does NOT delete your points or
 * settings. You can safely delete + reinstall the plugin to update it
 * without losing any data.
 *
 * To wipe everything when the plugin is deleted, add this line to
 * wp-config.php above the "That's all, stop editing!" line:
 *
 *   define( 'OEP_WIPE_ON_UNINSTALL', true );
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'OEP_WIPE_ON_UNINSTALL' ) || ! OEP_WIPE_ON_UNINSTALL ) {
	return; // Keep points and settings - safe reinstalls.
}

global $wpdb;

$table = $wpdb->prefix . 'osm_easy_points';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'oep_options' );
delete_option( 'oep_db_version' );

// Clear any rate-limit and geocode-cache transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_oep\\_%' OR option_name LIKE '\\_transient\\_timeout\\_oep\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
