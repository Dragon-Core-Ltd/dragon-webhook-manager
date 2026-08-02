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
		$this->init_components();
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
		if ( ! wp_next_scheduled( 'dwm_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'dwm_cleanup_logs' );
		}

		flush_rewrite_rules();
	}

	public function deactivate(): void {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'dwm_cleanup_logs' );

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

		update_option( 'dwm_db_version', DWM_VERSION );
	}

	private function set_default_options(): void {
		if ( false === get_option( 'dwm_log_retention_days' ) ) {
			update_option( 'dwm_log_retention_days', 7 );
		}
		if ( false === get_option( 'dwm_default_timeout' ) ) {
			update_option( 'dwm_default_timeout', 30 );
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
