<?php
/**
 * Plugin Name: Dragon Webhook Manager
 * Plugin URI: https://dragoncore.ltd/plugins/dragon-webhook-manager
 * Description: Visual interface for creating outgoing webhooks on any WordPress event. Build automations without code.
 * Version: 1.0.5
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Dragon Core
 * Author URI: https://dragoncore.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dragon-webhook-manager
 * Domain Path: /languages
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'DRAGONWEBHOOKMANAGER_VERSION', '1.0.5' );
define( 'DRAGONWEBHOOKMANAGER_PLUGIN_FILE', __FILE__ );
define( 'DRAGONWEBHOOKMANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRAGONWEBHOOKMANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DRAGONWEBHOOKMANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Free version limits
define( 'DRAGONWEBHOOKMANAGER_MAX_WEBHOOKS_FREE', 10 );

// Load classes
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-plugin.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-webhook.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-triggers.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-payload.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-logger.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-admin.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-ajax.php';
require_once DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'includes/class-integration.php';

/**
 * Plugin activation
 */
function dragonwebhookmanager_activate(): void {
	Plugin::get_instance()->activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\dragonwebhookmanager_activate' );

/**
 * Plugin deactivation
 */
function dragonwebhookmanager_deactivate(): void {
	Plugin::get_instance()->deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\dragonwebhookmanager_deactivate' );

/**
 * Initialize plugin
 */
function dragonwebhookmanager_init(): void {
	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\dragonwebhookmanager_init' );

/**
 * Add settings link to plugin row
 *
 * @param array $links Plugin action links.
 * @return array Modified links.
 */
function dragonwebhookmanager_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'tools.php?page=dragon-webhook-manager' ),
		__( 'Settings', 'dragon-webhook-manager' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . DRAGONWEBHOOKMANAGER_PLUGIN_BASENAME, __NAMESPACE__ . '\dragonwebhookmanager_plugin_action_links' );
