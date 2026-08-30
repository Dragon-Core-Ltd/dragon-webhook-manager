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
	public function get_all( bool $active_only = false, int $limit = 500 ): array {
		global $wpdb;

		// A hard LIMIT keeps the dashboard render bounded on any install.
		$limit = max( 1, $limit );

		if ( $active_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
			$results = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE is_active = 1 ORDER BY created_at DESC LIMIT %d', $this->table, $limit ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
			$results = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d', $this->table, $limit ),
				ARRAY_A
			);
		}

		return $results ? $results : array();
	}

	/**
	 * Get webhooks by trigger event
	 */
	public function get_by_trigger( string $trigger_event ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE trigger_event = %s AND is_active = 1',
				$this->table,
				$trigger_event
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get single webhook
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
	 * Create webhook
	 */
	public function create( array $data ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to plugin's custom table.
		$result = $wpdb->insert(
			$this->table,
			array(
				'name'             => sanitize_text_field( $data['name'] ),
				'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
				'trigger_event'    => sanitize_key( $data['trigger_event'] ),
				'url'              => esc_url_raw( $data['url'] ),
				'method'           => in_array( $data['method'] ?? 'POST', array( 'POST', 'PUT', 'PATCH' ), true ) ? $data['method'] : 'POST',
				'headers'          => wp_json_encode( $data['headers'] ?? array() ),
				'payload_template' => wp_unslash( $data['payload_template'] ?? '' ), // Don't use wp_kses_post on JSON/template data
				'is_active'        => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update webhook
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$update_data = array();
		$format      = array();

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
			$update_data['method'] = in_array( $data['method'], array( 'POST', 'PUT', 'PATCH' ), true ) ? $data['method'] : 'POST';
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to plugin's custom table.
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete webhook
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to plugin's custom table.
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
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

		return $this->update( $id, array( 'is_active' => $webhook['is_active'] ? 0 : 1 ) );
	}

	/**
	 * Count webhooks
	 */
	public function count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read; results are always current.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table ) );
	}

	/**
	 * Deliver webhook
	 */
	public function deliver( array $webhook, string $payload ): array {
		$url = (string) ( $webhook['url'] ?? '' );

		// SSRF protection: resolve and validate the target up front.
		$target  = self::resolve_target( $url );
		$blocked = (bool) apply_filters( 'dragonwebhookmanager_is_internal_url', $target['blocked'], $url );

		if ( $blocked ) {
			return array(
				'success'       => false,
				'response_code' => 0,
				'response_body' => '',
				'duration_ms'   => 0,
				'error_message' => __( 'Requests to internal or private IP addresses are not allowed.', 'dragon-webhook-manager' ),
			);
		}

		$headers = json_decode( $webhook['headers'] ?? '{}', true );
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}

		// Ensure Content-Type is set
		if ( ! isset( $headers['Content-Type'] ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$timeout = Plugin::sanitize_timeout( get_option( 'dragonwebhookmanager_default_timeout', 30 ) );

		$start_time = microtime( true );

		// Pin the connection to the exact address we validated so a DNS change
		// (rebinding) between the check and the request cannot redirect it to an
		// internal host. Scoped to this one request via add/remove_action.
		$pin    = $target['pin'];
		$pinner = null;
		if ( is_string( $pin ) && function_exists( 'curl_setopt' ) ) {
			// Applied unconditionally within the add/remove window below: this
			// deliver() makes exactly one synchronous request, so the only
			// http_api_curl that fires is ours. (Matching on the URL argument
			// is unreliable — WP may normalize it before re-dispatching the
			// back-compat action through the Requests hook.)
			$pinner = static function ( $handle ) use ( $pin ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- CURLOPT_RESOLVE has no wp_remote_* equivalent; it pins the connection to the pre-validated IP to prevent DNS-rebinding SSRF.
				curl_setopt( $handle, CURLOPT_RESOLVE, array( $pin ) );
			};
			add_action( 'http_api_curl', $pinner );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'             => $webhook['method'] ?? 'POST',
				'headers'            => $headers,
				'body'               => $payload,
				'timeout'            => $timeout,
				// Do not follow redirects: a 30x to an internal host would be
				// re-resolved unpinned and reopen the SSRF hole.
				'redirection'        => 0,
				// A misbehaving endpoint returning a huge body would otherwise be
				// read into memory and stored per delivery; cap it.
				'limit_response_size' => 64 * KB_IN_BYTES,
			)
		);

		if ( null !== $pinner ) {
			remove_action( 'http_api_curl', $pinner, 10 );
		}

		$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'       => false,
				'response_code' => 0,
				'response_body' => '',
				'duration_ms'   => $duration_ms,
				'error_message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Keep only a bounded slice of the response for the log, regardless of
		// what the transport returned.
		if ( strlen( $response_body ) > 64 * KB_IN_BYTES ) {
			$response_body = substr( $response_body, 0, 64 * KB_IN_BYTES );
		}

		if ( $response_code >= 300 && $response_code < 400 ) {
			// Redirects are intentionally not followed (SSRF), so explain the
			// non-2xx result rather than logging a blank failure.
			$error_message = sprintf(
				/* translators: %d: HTTP status code. */
				__( 'HTTP %d: the endpoint redirected, and redirects are not followed. Use the final URL directly.', 'dragon-webhook-manager' ),
				$response_code
			);
		} elseif ( $response_code >= 400 ) {
			$error_message = "HTTP {$response_code}";
		} else {
			$error_message = '';
		}

		return array(
			'success'       => $response_code >= 200 && $response_code < 300,
			'response_code' => $response_code,
			'response_body' => $response_body,
			'duration_ms'   => $duration_ms,
			'error_message' => $error_message,
		);
	}

	/**
	 * Resolve a webhook URL and decide whether it may be delivered.
	 *
	 * Resolves the host to every IPv4 and IPv6 address and blocks the request
	 * if any of them is a loopback, link-local (incl. cloud metadata), private,
	 * or reserved address — a hostname is only as safe as its riskiest record.
	 * An unresolvable host is blocked (fail closed). On success it returns a
	 * CURLOPT_RESOLVE pin string ("host:port:ip") so the caller can force the
	 * connection to the exact address that was validated, defeating DNS
	 * rebinding between this check and the request.
	 *
	 * @param string $url Target URL.
	 * @return array{blocked: bool, pin: string|null}
	 */
	public static function resolve_target( string $url ): array {
		$deny = array(
			'blocked' => true,
			'pin'     => null,
		);

		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['host'] ) ) {
			return $deny;
		}

		$scheme = strtolower( (string) ( $parsed['scheme'] ?? 'https' ) );
		$host   = strtolower( trim( (string) $parsed['host'], '[]' ) );
		$port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( 'http' === $scheme ? 80 : 443 );

		// Block localhost variants by name.
		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) ) {
			return $deny;
		}

		// An IP literal has no DNS to rebind: validate it, but there is nothing
		// to pin (and an IPv6 literal cannot be expressed as a host:port:ip pin).
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_blocked_ip( $host )
				? $deny
				: array(
					'blocked' => false,
					'pin'     => null,
				);
		}

		// Resolve the hostname to every IPv4 and IPv6 address.
		$ips = array();

		$v4 = gethostbynamel( $host );
		if ( is_array( $v4 ) ) {
			$ips = array_merge( $ips, $v4 );
		}

		$aaaa = dns_get_record( $host, DNS_AAAA );
		if ( is_array( $aaaa ) ) {
			foreach ( $aaaa as $record ) {
				if ( ! empty( $record['ipv6'] ) ) {
					$ips[] = $record['ipv6'];
				}
			}
		}

		// Fail closed: an unresolvable host must not slip through, or a host
		// that resolves only at request time would be an SSRF bypass.
		if ( empty( $ips ) ) {
			return $deny;
		}

		$safe = null;
		foreach ( $ips as $ip ) {
			if ( self::is_blocked_ip( $ip ) ) {
				return $deny; // Any blocked address blocks the whole host.
			}
			if ( null === $safe ) {
				$safe = $ip;
			}
		}

		// CURLOPT_RESOLVE wants IPv6 literals in square brackets.
		$pin_ip = ( false !== strpos( (string) $safe, ':' ) ) ? '[' . $safe . ']' : $safe;

		return array(
			'blocked' => false,
			'pin'     => $host . ':' . $port . ':' . $pin_ip,
		);
	}

	/**
	 * Whether an IP address is in a loopback, link-local, private, or reserved range.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return bool
	 */
	public static function is_blocked_ip( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return true; // Not a valid IP: fail closed.
		}

		return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}
}
