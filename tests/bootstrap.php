<?php
/**
 * PHPUnit bootstrap.
 *
 * Webhook::is_blocked_ip() (the SSRF range check) is pure and WP-light; only
 * an ABSPATH guard is needed to load the class.
 *
 * @package DragonWebhookManager
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

require_once __DIR__ . '/../includes/class-webhook.php';
