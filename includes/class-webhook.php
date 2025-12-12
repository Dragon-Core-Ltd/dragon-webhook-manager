<?php
/**
 * Webhook model and delivery logic
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhook {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'dwm_webhooks';
	}

	/**
	 * Get all webhooks
	 */
	public function get_all( bool $active_only = false ): array {
		global $wpdb;

		$where = $active_only ? 'WHERE is_active = 1' : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Get webhooks by trigger event
	 */
	public function get_by_trigger( string $trigger_event ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE trigger_event = %s AND is_active = 1",
				$trigger_event
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get single webhook
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
	 * Create webhook
	 */
	public function create( array $data ): int|false {
		global $wpdb;

		// Check free limit (Pro can override via filter).
		$max_webhooks = apply_filters( 'dwm_max_webhooks', DWM_MAX_WEBHOOKS_FREE );
		if ( $this->count() >= $max_webhooks ) {
			return false;
		}

		$result = $wpdb->insert(
			$this->table,
			[
				'name'             => sanitize_text_field( $data['name'] ),
				'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
				'trigger_event'    => sanitize_key( $data['trigger_event'] ),
				'url'              => esc_url_raw( $data['url'] ),
				'method'           => in_array( $data['method'] ?? 'POST', [ 'POST', 'PUT', 'PATCH' ], true ) ? $data['method'] : 'POST',
				'headers'          => wp_json_encode( $data['headers'] ?? [] ),
				'payload_template' => wp_unslash( $data['payload_template'] ?? '' ), // Don't use wp_kses_post on JSON/template data
				'is_active'        => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update webhook
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$update_data = [];
		$format      = [];

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$format[]            = '%s';
		}
		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$format[]                   = '%s';
		}
		if ( isset( $data['trigger_event'] ) ) {
			$update_data['trigger_event'] = sanitize_key( $data['trigger_event'] );
			$format[]                     = '%s';
		}
		if ( isset( $data['url'] ) ) {
			$update_data['url'] = esc_url_raw( $data['url'] );
			$format[]           = '%s';
		}
		if ( isset( $data['method'] ) ) {
			$update_data['method'] = in_array( $data['method'], [ 'POST', 'PUT', 'PATCH' ], true ) ? $data['method'] : 'POST';
			$format[]              = '%s';
		}
		if ( isset( $data['headers'] ) ) {
			$update_data['headers'] = wp_json_encode( $data['headers'] );
			$format[]               = '%s';
		}
		if ( isset( $data['payload_template'] ) ) {
			$update_data['payload_template'] = wp_unslash( $data['payload_template'] ); // Don't use wp_kses_post on JSON/template data
			$format[]                        = '%s';
		}
		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = (int) $data['is_active'];
			$format[]                 = '%d';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table,
			$update_data,
			[ 'id' => $id ],
			$format,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete webhook
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Toggle webhook active status
	 */
	public function toggle( int $id ): bool {
		$webhook = $this->get( $id );

		if ( ! $webhook ) {
			return false;
		}

		return $this->update( $id, [ 'is_active' => $webhook['is_active'] ? 0 : 1 ] );
	}

	/**
	 * Count webhooks
	 */
	public function count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Deliver webhook
	 */
	public function deliver( array $webhook, string $payload ): array {
		// SSRF protection: Block requests to internal/private IPs.
		if ( $this->is_internal_url( $webhook['url'] ) ) {
			return [
				'success'       => false,
				'response_code' => 0,
				'response_body' => '',
				'duration_ms'   => 0,
				'error_message' => __( 'Requests to internal or private IP addresses are not allowed.', 'dragon-webhook-manager' ),
			];
		}

		$headers = json_decode( $webhook['headers'] ?? '{}', true ) ?: [];

		// Ensure Content-Type is set
		if ( ! isset( $headers['Content-Type'] ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$timeout = (int) get_option( 'dwm_default_timeout', 30 );

		$start_time = microtime( true );

		$response = wp_remote_request(
			$webhook['url'],
			[
				'method'  => $webhook['method'] ?? 'POST',
				'headers' => $headers,
				'body'    => $payload,
				'timeout' => $timeout,
			]
		);

		$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [
				'success'       => false,
				'response_code' => 0,
				'response_body' => '',
				'duration_ms'   => $duration_ms,
				'error_message' => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		return [
			'success'       => $response_code >= 200 && $response_code < 300,
			'response_code' => $response_code,
			'response_body' => $response_body,
			'duration_ms'   => $duration_ms,
			'error_message' => $response_code >= 400 ? "HTTP {$response_code}" : '',
		];
	}

	/**
	 * Check if URL targets an internal/private IP address (SSRF protection).
	 */
	private function is_internal_url( string $url ): bool {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['host'] ) ) {
			return true; // Invalid URL, block it.
		}

		$host = strtolower( $parsed['host'] );

		// Block localhost variants.
		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) ) {
			return true;
		}

		// Resolve hostname to IP.
		$ip = gethostbyname( $host );
		if ( $ip === $host ) {
			// Could not resolve, check if it's already an IP.
			$ip = $host;
		}

		// Validate IP and block private/reserved ranges.
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			// Block private and reserved IP ranges.
			if ( ! filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			) ) {
				return true;
			}
		}

		return false;
	}
}
