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

global $wpdb;

// Drop all plugin tables.
$tables = [
	$wpdb->prefix . 'dwm_webhooks',
	$wpdb->prefix . 'dwm_logs',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dwm\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'dwm_cleanup_logs' );

// Delete any transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%\_transient\_dwm\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%\_transient\_timeout\_dwm\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
