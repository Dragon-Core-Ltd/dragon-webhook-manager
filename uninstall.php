<?php
/**
 * Uninstall Dragon Webhook Manager
 *
 * Removes all plugin data when uninstalled through WordPress admin.
 *
 * @package DragonWebhookManager
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect the site owner's data: nothing is removed unless they explicitly
// opted in (the "Delete all data on uninstall" setting). Without the opt-in,
// tables and options survive so a reinstall picks up exactly where it left off.
if ( ! get_option( 'dragonwebhookmanager_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

// Drop all plugin tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing plugin's custom table on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'dwm_webhooks' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing plugin's custom table on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'dwm_logs' ) );

// Delete all plugin options (current namespace-derived prefix and the pre-1.0.4
// dwm_ prefix, in case an install was removed before the migration ran).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin options on uninstall.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dragonwebhookmanager\_%' OR option_name LIKE 'dwm\_%'" );

// Clear scheduled cron events (current and pre-1.0.4 hook names).
wp_clear_scheduled_hook( 'dragonwebhookmanager_cleanup_logs' );
wp_clear_scheduled_hook( 'dwm_cleanup_logs' );

// Delete any transients (both prefixes).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transients on uninstall.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%\_transient\_dragonwebhookmanager\_%' OR option_name LIKE '%\_transient\_dwm\_%'" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transients on uninstall.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%\_transient\_timeout\_dragonwebhookmanager\_%' OR option_name LIKE '%\_transient\_timeout\_dwm\_%'" );
