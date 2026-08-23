<?php
/**
 * Webhook delivery logging
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'dwm_logs';

		add_action( 'dragonwebhookmanager_cleanup_logs', array( $this, 'cleanup_old_logs' ) );
	}

	/**
	 * Start a log entry (before delivery)
	 */
	public function log_start( array $webhook, string $payload ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to plugin's custom table.
		$wpdb->insert(
			$this->table,
			array(
				'webhook_id'      => $webhook['id'],
				'trigger_event'   => $webhook['trigger_event'],
				'request_url'     => $webhook['url'],
				'request_method'  => $webhook['method'],
				'request_headers' => $this->redact_headers( $webhook['headers'] ?? '' ),
				'request_body'    => $payload,
				'status'          => 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Redact secret-bearing request headers before they are stored/displayed.
	 *
	 * Admin-configured Authorization / API-key headers would otherwise sit in
	 * the log table (and the logs-table DOM) in cleartext for every delivery.
	 * The HMAC signature header is left intact — it is not a secret.
	 *
	 * @param string|array $headers Header map or its JSON encoding.
	 * @return string JSON-encoded headers with secrets masked.
	 */
	private function redact_headers( $headers ): string {
		$decoded = is_array( $headers ) ? $headers : json_decode( (string) $headers, true );
		if ( ! is_array( $decoded ) ) {
			return is_string( $headers ) ? $headers : (string) wp_json_encode( array() );
		}

		foreach ( $decoded as $name => $value ) {
			if ( preg_match( '/authorization|cookie|api[-_]?key|token|secret|password/i', (string) $name ) ) {
				$decoded[ $name ] = '[redacted]';
			}
		}

		return (string) wp_json_encode( $decoded );
	}

	/**
	 * Complete a log entry (after delivery)
	 */
	public function log_complete(
		int $log_id,
		string $status,
		int $response_code,
		string $response_body,
		int $duration_ms,
		string $error_message = ''
	): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to plugin's custom table.
		$wpdb->update(
			$this->table,
			array(
				'status'        => $status,
				'response_code' => $response_code,
				'response_body' => $response_body,
				'duration_ms'   => $duration_ms,
				'error_message' => $error_message,
			),
			array( 'id' => $log_id ),
			array( '%s', '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get logs with pagination
	 */
	public function get_logs( int $limit = 50, int $offset = 0, ?int $webhook_id = null ): array {
		global $wpdb;

		$webhooks_table = $wpdb->prefix . 'dwm_webhooks';

		if ( $webhook_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT l.*, w.name as webhook_name
					FROM %i l
					LEFT JOIN %i w ON l.webhook_id = w.id
					WHERE l.webhook_id = %d
					ORDER BY l.created_at DESC
					LIMIT %d OFFSET %d',
					$this->table,
					$webhooks_table,
					$webhook_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT l.*, w.name as webhook_name
					FROM %i l
					LEFT JOIN %i w ON l.webhook_id = w.id
					ORDER BY l.created_at DESC
					LIMIT %d OFFSET %d',
					$this->table,
					$webhooks_table,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		return $results ? $results : array();
	}

	/**
	 * Get log by ID
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$result = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);

		return $result ? $result : null;
	}

	/**
	 * Get statistics
	 */
	public function get_stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$success = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->table, 'success' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$failed = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->table, 'failed' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$avg_duration = (float) $wpdb->get_var(
			$wpdb->prepare( 'SELECT AVG(duration_ms) FROM %i WHERE status = %s', $this->table, 'success' )
		);

		// Today's count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE DATE(created_at) = %s',
				$this->table,
				gmdate( 'Y-m-d' )
			)
		);

		return array(
			'total'        => $total,
			'success'      => $success,
			'failed'       => $failed,
			'success_rate' => $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 0,
			'avg_duration' => round( $avg_duration, 0 ),
			'today'        => $today,
		);
	}

	/**
	 * Delete old logs
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		$retention_days = (int) get_option( 'dragonwebhookmanager_log_retention_days', 7 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup of plugin's custom table.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$this->table,
				$retention_days
			)
		);
	}

	/**
	 * Clear all logs
	 */
	public function clear_logs(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-initiated truncate of plugin's custom table.
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $this->table ) );
	}

	/**
	 * Create a minimal log entry and return its ID.
	 *
	 * Used by the Pro integration (retry) to open a log row that a later
	 * update fills in with the delivery outcome. Only core columns are set;
	 * Pro-specific fields (retry linkage) are stored separately by Pro.
	 *
	 * @param array $data Log data: webhook_id, trigger_event, status.
	 * @return int New log ID (0 on failure).
	 */
	public function create( array $data ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to plugin's custom table.
		$wpdb->insert(
			$this->table,
			array(
				'webhook_id'    => (int) ( $data['webhook_id'] ?? 0 ),
				'trigger_event' => (string) ( $data['trigger_event'] ?? '' ),
				'status'        => (string) ( $data['status'] ?? 'pending' ),
			),
			array( '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete logs for a webhook
	 */
	public function delete_for_webhook( int $webhook_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to plugin's custom table.
		$wpdb->delete(
			$this->table,
			array( 'webhook_id' => $webhook_id ),
			array( '%d' )
		);
	}
}
