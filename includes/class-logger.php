<?php
/**
 * Webhook delivery logging
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	/**
	 * Stored in place of a secret header value; translated at display.
	 */
	public const REDACTED = '[redacted]';

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
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Redact secret-bearing request headers before they are stored/displayed.
	 *
	 * Admin-configured Authorization / API-key headers would otherwise sit in
	 * the log table (and the logs-table DOM) in cleartext for every delivery.
	 * Signature headers are left intact - they are not secrets.
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
				$decoded[ $name ] = self::REDACTED;
			}
		}

		return (string) wp_json_encode( $decoded );
	}

	/**
	 * Complete a log entry (after delivery).
	 *
	 * Text is made valid UTF-8 first: a response body cut at the size cap can
	 * end inside a character, and a Latin-1 body is not UTF-8 at all, and
	 * wpdb refuses the whole update for either. If the database still refuses
	 * the text (a character the table's charset cannot hold), the outcome is
	 * written without it rather than leaving the row pending.
	 *
	 * @param int    $log_id        Log ID.
	 * @param string $status        success or failed.
	 * @param int    $response_code HTTP status, 0 when there was no response.
	 * @param string $response_body Response body.
	 * @param int    $duration_ms   Request duration.
	 * @param string $error_message Error message.
	 * @return bool Whether the outcome was written.
	 */
	public function log_complete(
		int $log_id,
		string $status,
		int $response_code,
		string $response_body,
		int $duration_ms,
		string $error_message = ''
	): bool {
		global $wpdb;

		$response_body = self::valid_utf8( $response_body );
		$error_message = self::valid_utf8( $error_message );

		$attempts = array(
			array( $response_body, $error_message ),
			array( '', $error_message ),
			array( '', '' ),
		);

		foreach ( $attempts as $attempt ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to plugin's custom table.
			$result = $wpdb->update(
				$this->table,
				array(
					'status'        => $status,
					'response_code' => $response_code,
					'response_body' => $attempt[0],
					'duration_ms'   => $duration_ms,
					'error_message' => $attempt[1],
				),
				array( 'id' => $log_id ),
				array( '%s', '%d', '%s', '%d', '%s' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Text as valid UTF-8, with each invalid byte sequence replaced by U+FFFD.
	 *
	 * @param string $text Text of unknown encoding.
	 * @return string
	 */
	public static function valid_utf8( string $text ): string {
		if ( '' === $text || 1 === preg_match( '//u', $text ) ) {
			return $text;
		}

		return htmlspecialchars_decode( htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ), ENT_NOQUOTES );
	}

	/**
	 * Number of log rows, for one webhook or all.
	 *
	 * @param int|null $webhook_id Webhook ID, or null for every row.
	 * @return int
	 */
	public function count_logs( ?int $webhook_id = null ): int {
		global $wpdb;

		if ( $webhook_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE webhook_id = %d', $this->table, $webhook_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table ) );
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

		// Today's count: the site's calendar day, as a range over the UTC
		// created_at column (which also lets the index serve it).
		list( $day_start, $day_end ) = self::local_day_bounds( wp_timezone(), time() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < %s',
				$this->table,
				$day_start,
				$day_end
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
	 * UTC bounds of the site-local calendar day containing a moment.
	 *
	 * Built from local midnight today and tomorrow, so a day that is 23 or 25
	 * hours long (a DST change) is bounded correctly.
	 *
	 * @param \DateTimeZone $timezone Site timezone.
	 * @param int           $now      Unix timestamp.
	 * @return array{0: string, 1: string} [start, end) as UTC 'Y-m-d H:i:s'.
	 */
	public static function local_day_bounds( \DateTimeZone $timezone, int $now ): array {
		$utc   = new \DateTimeZone( 'UTC' );
		$start = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $timezone )->setTime( 0, 0 );
		$end   = $start->modify( '+1 day' )->setTime( 0, 0 );

		return array(
			$start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Delete old logs
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		$retention_days = Plugin::sanitize_retention_days( get_option( 'dragonwebhookmanager_log_retention_days', 7 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup of plugin's custom table.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$this->table,
				gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Clear all logs.
	 *
	 * DELETE rather than TRUNCATE: TRUNCATE resets AUTO_INCREMENT, so new rows
	 * would reuse the IDs of cleared ones and inherit any add-on state keyed
	 * by log ID.
	 *
	 * @return bool Whether the delete ran (false on a database error).
	 */
	public function clear_logs(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-initiated clear of plugin's custom table.
		return false !== $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $this->table ) );
	}

	/**
	 * Create a minimal log entry and return its ID.
	 *
	 * Used by the integration API (`dragonwebhookmanager_create_log`) to open
	 * a log row that a later update fills in with the delivery outcome. Only
	 * core columns are set; callers keep any extra bookkeeping in their own
	 * storage.
	 *
	 * @param array $data Log data: webhook_id, trigger_event, status.
	 * @return int New log ID (0 on failure).
	 */
	public function create( array $data ): int {
		global $wpdb;

		$row    = array(
			'webhook_id'    => (int) ( $data['webhook_id'] ?? 0 ),
			'trigger_event' => (string) ( $data['trigger_event'] ?? '' ),
			'status'        => (string) ( $data['status'] ?? 'pending' ),
			'created_at'    => current_time( 'mysql', true ),
		);
		$format = array( '%d', '%s', '%s', '%s' );

		// Optional request details, so the row shows where it was sent.
		foreach ( array( 'request_url', 'request_method' ) as $column ) {
			if ( isset( $data[ $column ] ) && is_scalar( $data[ $column ] ) ) {
				$row[ $column ] = (string) $data[ $column ];
				$format[]       = '%s';
			}
		}
		if ( isset( $data['request_headers'] ) ) {
			$row['request_headers'] = $this->redact_headers( $data['request_headers'] );
			$format[]               = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to plugin's custom table.
		$wpdb->insert( $this->table, $row, $format );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Record the request a log row stands for: URL, method, (redacted)
	 * headers and body. Used for rows opened through the add-on API, which
	 * are created before the payload is rendered.
	 *
	 * @param int    $log_id  Log ID.
	 * @param array  $webhook Webhook as sent (headers already filtered).
	 * @param string $payload Request body as sent.
	 * @return bool Whether the row was written.
	 */
	public function fill_request( int $log_id, array $webhook, string $payload ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to plugin's custom table.
		$result = $wpdb->update(
			$this->table,
			array(
				'request_url'     => (string) ( $webhook['url'] ?? '' ),
				'request_method'  => (string) ( $webhook['method'] ?? '' ),
				'request_headers' => $this->redact_headers( $webhook['headers'] ?? '' ),
				'request_body'    => $payload,
			),
			array( 'id' => $log_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
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
