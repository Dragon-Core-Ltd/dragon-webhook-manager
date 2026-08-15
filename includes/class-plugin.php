<?php
/**
 * Main plugin class
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static ?Plugin $instance = null;

	private Webhook $webhook;
	private Triggers $triggers;
	private Payload $payload;
	private Logger $logger;
	private Admin $admin;
	private Ajax $ajax;
	private Integration $integration;

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		self::migrate_legacy_prefix();
		$this->init_components();
	}

	/**
	 * Move options and the cleanup schedule off the pre-1.0.4 three-letter (dwm_)
	 * prefix.
	 *
	 * The prefix was renamed to the namespace-derived `dragonwebhookmanager_` to
	 * satisfy the WordPress.org uniqueness rule. Option values are carried across
	 * once and the log-cleanup cron is re-pointed at the renamed hook. The
	 * webhooks and logs tables keep their names (matched by exact name), so
	 * configured webhooks and delivery history are untouched.
	 */
	private static function migrate_legacy_prefix(): void {
		// db_version is a schema marker managed by activation, not user data.
		delete_option( 'dwm_db_version' );

		$options = array( 'default_timeout', 'log_retention_days' );

		// Copy each legacy value onto the new name, then remove the legacy copy —
		// per option, so the delete only ever runs after a successful copy. (A
		// single shared guard would delete on a deactivate/reactivate cycle, where
		// activation re-stamps the new db_version before the copy could run.)
		foreach ( $options as $name ) {
			$legacy = get_option( 'dwm_' . $name, null );
			if ( null !== $legacy ) {
				update_option( 'dragonwebhookmanager_' . $name, $legacy );
				delete_option( 'dwm_' . $name );
			}
		}

		$legacy_cron = wp_next_scheduled( 'dwm_cleanup_logs' );
		if ( $legacy_cron ) {
			wp_unschedule_event( $legacy_cron, 'dwm_cleanup_logs' );
		}
		if ( ! wp_next_scheduled( 'dragonwebhookmanager_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'dragonwebhookmanager_cleanup_logs' );
		}
	}

	private function init_components(): void {
		$this->webhook  = new Webhook();
		$this->payload  = new Payload();
		$this->logger   = new Logger();
		$this->triggers = new Triggers( $this->webhook, $this->payload, $this->logger );
		$this->admin    = new Admin( $this->webhook, $this->logger );
		$this->ajax     = new Ajax( $this->webhook, $this->logger, $this->payload );

		// Pro integration hook API (used by Dragon Webhook Manager Pro).
		$this->integration = new Integration( $this->webhook, $this->payload, $this->logger );
	}

	public function activate(): void {
		$this->create_tables();
		$this->set_default_options();

		// Schedule log cleanup
		if ( ! wp_next_scheduled( 'dragonwebhookmanager_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'dragonwebhookmanager_cleanup_logs' );
		}

		flush_rewrite_rules();
	}

	public function deactivate(): void {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'dragonwebhookmanager_cleanup_logs' );

		flush_rewrite_rules();
	}

	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$webhooks_table = $wpdb->prefix . 'dwm_webhooks';
		$logs_table     = $wpdb->prefix . 'dwm_logs';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create webhooks table.
		$sql_webhooks = "CREATE TABLE {$webhooks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			trigger_event varchar(100) NOT NULL,
			url varchar(2048) NOT NULL,
			method varchar(10) NOT NULL DEFAULT 'POST',
			headers longtext,
			payload_template longtext,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_trigger (trigger_event),
			KEY idx_active (is_active)
		) $charset_collate;";
		dbDelta( $sql_webhooks );

		// Create logs table.
		$sql_logs = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			webhook_id bigint(20) unsigned NOT NULL,
			trigger_event varchar(100),
			request_url varchar(2048),
			request_method varchar(10),
			request_headers longtext,
			request_body longtext,
			response_code int,
			response_body longtext,
			duration_ms int,
			status varchar(20) NOT NULL DEFAULT 'pending',
			error_message text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_webhook (webhook_id),
			KEY idx_status (status),
			KEY idx_created (created_at)
		) $charset_collate;";
		dbDelta( $sql_logs );

		update_option( 'dragonwebhookmanager_db_version', DRAGONWEBHOOKMANAGER_VERSION );
	}

	private function set_default_options(): void {
		if ( false === get_option( 'dragonwebhookmanager_log_retention_days' ) ) {
			update_option( 'dragonwebhookmanager_log_retention_days', 7 );
		}
		if ( false === get_option( 'dragonwebhookmanager_default_timeout' ) ) {
			update_option( 'dragonwebhookmanager_default_timeout', 30 );
		}
	}

	// Component getters
	public function webhook(): Webhook {
		return $this->webhook;
	}

	public function triggers(): Triggers {
		return $this->triggers;
	}

	public function payload(): Payload {
		return $this->payload;
	}

	public function logger(): Logger {
		return $this->logger;
	}

	public function admin(): Admin {
		return $this->admin;
	}
}
