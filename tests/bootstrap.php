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
require_once __DIR__ . '/../includes/class-webhook.php';
