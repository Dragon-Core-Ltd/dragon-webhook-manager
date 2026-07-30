<?php
/**
 * Plugin Name: Dragon Webhook Manager
 * Plugin URI: https://dragoncore.ltd/plugins/dragon-webhook-manager
 * Description: Visual interface for creating outgoing webhooks on any WordPress event. Build automations without code.
 * Version: 1.0.0
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
define( 'DWM_VERSION', '1.0.0' );
define( 'DWM_PLUGIN_FILE', __FILE__ );
define( 'DWM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DWM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DWM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Free version limits
define( 'DWM_MAX_WEBHOOKS_FREE', 10 );

// Load classes
require_once DWM_PLUGIN_DIR . 'includes/class-plugin.php';
require_once DWM_PLUGIN_DIR . 'includes/class-webhook.php';
require_once DWM_PLUGIN_DIR . 'includes/class-triggers.php';
require_once DWM_PLUGIN_DIR . 'includes/class-payload.php';
require_once DWM_PLUGIN_DIR . 'includes/class-logger.php';
require_once DWM_PLUGIN_DIR . 'includes/class-admin.php';
require_once DWM_PLUGIN_DIR . 'includes/class-ajax.php';

/**
 * Plugin activation
 */
function dwm_activate(): void {
	Plugin::get_instance()->activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\dwm_activate' );

/**
 * Plugin deactivation
 */
function dwm_deactivate(): void {
	Plugin::get_instance()->deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\dwm_deactivate' );

/**
 * Initialize plugin
 */
function dwm_init(): void {
	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\dwm_init' );

/**
 * Add settings link to plugin row
 *
 * @param array $links Plugin action links.
 * @return array Modified links.
 */
function dwm_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'tools.php?page=dragon-webhook-manager' ),
		__( 'Settings', 'dragon-webhook-manager' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . DWM_PLUGIN_BASENAME, __NAMESPACE__ . '\dwm_plugin_action_links' );
