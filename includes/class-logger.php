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

		add_action( 'dwm_cleanup_logs', [ $this, 'cleanup_old_logs' ] );
	}

	/**
	 * Start a log entry (before delivery)
	 */
	public function log_start( array $webhook, string $payload ): int {
		global $wpdb;

		$wpdb->insert(
			$this->table,
			[
				'webhook_id'      => $webhook['id'],
				'trigger_event'   => $webhook['trigger_event'],
				'request_url'     => $webhook['url'],
				'request_method'  => $webhook['method'],
				'request_headers' => $webhook['headers'],
				'request_body'    => $payload,
				'status'          => 'pending',
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $wpdb->insert_id;
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

		$wpdb->update(
			$this->table,
			[
				'status'        => $status,
				'response_code' => $response_code,
				'response_body' => $response_body,
				'duration_ms'   => $duration_ms,
				'error_message' => $error_message,
			],
			[ 'id' => $log_id ],
			[ '%s', '%d', '%s', '%d', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Get logs with pagination
	 */
	public function get_logs( int $limit = 50, int $offset = 0, ?int $webhook_id = null ): array {
		global $wpdb;

		$where = '';
		if ( $webhook_id ) {
			$where = $wpdb->prepare( 'WHERE l.webhook_id = %d', $webhook_id );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, w.name as webhook_name
				FROM {$this->table} l
				LEFT JOIN {$wpdb->prefix}dwm_webhooks w ON l.webhook_id = w.id
				{$where}
				ORDER BY l.created_at DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get log by ID
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Get statistics
	 */
	public function get_stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$success = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'success' )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$failed = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'failed' )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$avg_duration = (float) $wpdb->get_var(
			$wpdb->prepare( "SELECT AVG(duration_ms) FROM {$this->table} WHERE status = %s", 'success' )
		);

		// Today's count
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE DATE(created_at) = %s",
				gmdate( 'Y-m-d' )
			)
		);

		return [
			'total'        => $total,
			'success'      => $success,
			'failed'       => $failed,
			'success_rate' => $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 0,
			'avg_duration' => round( $avg_duration, 0 ),
			'today'        => $today,
		];
	}

	/**
	 * Delete old logs
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		$retention_days = (int) get_option( 'dwm_log_retention_days', 7 );

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);
	}

	/**
	 * Clear all logs
	 */
	public function clear_logs(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$this->table}" );
	}

	/**
	 * Delete logs for a webhook
	 */
	public function delete_for_webhook( int $webhook_id ): void {
		global $wpdb;

		$wpdb->delete(
			$this->table,
			[ 'webhook_id' => $webhook_id ],
			[ '%d' ]
		);
	}
}
