<?php
/**
 * AJAX handlers
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax {

	private Webhook $webhook;
	private Logger $logger;
	private Payload $payload;

	public function __construct( Webhook $webhook, Logger $logger, Payload $payload ) {
		$this->webhook = $webhook;
		$this->logger  = $logger;
		$this->payload = $payload;

		add_action( 'wp_ajax_dragonwebhookmanager_save_webhook', array( $this, 'handle_save_webhook' ) );
		add_action( 'wp_ajax_dragonwebhookmanager_delete_webhook', array( $this, 'handle_delete_webhook' ) );
		add_action( 'wp_ajax_dragonwebhookmanager_toggle_webhook', array( $this, 'handle_toggle_webhook' ) );
		add_action( 'wp_ajax_dragonwebhookmanager_test_webhook', array( $this, 'handle_test_webhook' ) );
		add_action( 'wp_ajax_dragonwebhookmanager_retry_delivery', array( $this, 'handle_retry_delivery' ) );
		add_action( 'wp_ajax_dragonwebhookmanager_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	/**
	 * Save webhook (create or update)
	 */
	public function handle_save_webhook(): void {
		check_ajax_referer( 'dragonwebhookmanager_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dragon-webhook-manager' ) ) );
		}

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'trigger_event'    => sanitize_key( $_POST['trigger_event'] ?? '' ),
			'url'              => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'method'           => Webhook::sanitize_method( wp_unslash( $_POST['method'] ?? 'POST' ) ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parse_headers_text() validates names as tokens and strips control characters from values; the text sanitizers would corrupt credentials.
			'headers'          => Webhook::parse_headers_text( (string) wp_unslash( $_POST['headers'] ?? '' ) ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON template; stored for machine use and escaped on output.
			'payload_template' => wp_unslash( $_POST['payload_template'] ?? '' ),
			'is_active'        => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		// Validate required fields
		if ( empty( $data['name'] ) || empty( $data['trigger_event'] ) || empty( $data['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Name, trigger, and URL are required.', 'dragon-webhook-manager' ) ) );
		}

		// Validate URL; an internationalised host is stored in its ASCII form.
		$normalized_url = Webhook::normalize_url( $data['url'] );
		if ( null === $normalized_url ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'dragon-webhook-manager' ) ) );
		}
		$data['url'] = $normalized_url;

		// Refuse internal addresses at save; delivery re-checks resolved DNS.
		if ( (bool) apply_filters( 'dragonwebhookmanager_is_internal_url', Webhook::is_internal_literal( $normalized_url ), $normalized_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Requests to internal or private IP addresses are not allowed.', 'dragon-webhook-manager' ) ) );
		}

		if ( $id ) {
			$result = $this->webhook->update( $id, $data );
		} else {
			$result = $this->webhook->create( $data );
		}

		if ( false === $result ) {
			$message = $id
				? __( 'Failed to update webhook.', 'dragon-webhook-manager' )
				: __( 'Failed to create webhook.', 'dragon-webhook-manager' );
			wp_send_json_error( array( 'message' => $message ) );
		}

		$webhook_id = $id ? $id : (int) $result;

		/**
		 * Fires after a webhook is created or updated.
		 *
		 * Add-ons persist their own per-webhook settings on this action,
		 * keyed by webhook ID in their own metadata store. They read the raw
		 * request fields they own from $_POST directly; the nonce and
		 * capability were already verified above.
		 *
		 * @param int   $webhook_id Saved webhook ID.
		 * @param array $data       Sanitized core webhook data that was stored.
		 */
		do_action( 'dragonwebhookmanager_webhook_saved', $webhook_id, $data );

		wp_send_json_success(
			array(
				'message'    => __( 'Webhook saved successfully.', 'dragon-webhook-manager' ),
				'webhook_id' => $webhook_id,
			)
		);
	}

	/**
	 * Delete webhook
	 */
	public function handle_delete_webhook(): void {
		check_ajax_referer( 'dragonwebhookmanager_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dragon-webhook-manager' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'dragon-webhook-manager' ) ) );
		}

		// Delete associated logs first
		$this->logger->delete_for_webhook( $id );

		$result = $this->webhook->delete( $id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete webhook.', 'dragon-webhook-manager' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Webhook deleted.', 'dragon-webhook-manager' ) ) );
	}

	/**
	 * Toggle webhook active status
	 */
	public function handle_toggle_webhook(): void {
		check_ajax_referer( 'dragonwebhookmanager_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dragon-webhook-manager' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'dragon-webhook-manager' ) ) );
		}

		$result = $this->webhook->toggle( $id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle webhook.', 'dragon-webhook-manager' ) ) );
		}

		$webhook = $this->webhook->get( $id );

		wp_send_json_success(
			array(
				'message'   => __( 'Webhook status updated.', 'dragon-webhook-manager' ),
				'is_active' => $webhook['is_active'],
			)
		);
	}

	/**
	 * Test webhook with sample data
	 */
	public function handle_test_webhook(): void {
		check_ajax_referer( 'dragonwebhookmanager_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dragon-webhook-manager' ) ) );
		}

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$saved = $id ? $this->webhook->get( $id ) : null;

		// The edit form posts its fields with the test, so what is tested is
		// what is on screen, saved or not. A saved webhook keeps its identity
		// (ID, name) so add-ons such as request signing still apply.
		if ( ! $saved || isset( $_POST['url'] ) ) {
			// normalize_url() returns null for a URL it refuses. Keep the raw input
			// so a refused URL is reported as invalid rather than as missing: the
			// user did supply one.
			$raw_url        = trim( (string) wp_unslash( $_POST['url'] ?? '' ) );
			$normalized_url = Webhook::normalize_url( esc_url_raw( $raw_url ) );

			if ( '' !== $raw_url && null === $normalized_url ) {
				wp_send_json_error( array( 'message' => __( 'That URL could not be used. Enter a full https:// address.', 'dragon-webhook-manager' ) ) );
			}

			$form = array(
				'trigger_event'    => sanitize_key( $_POST['trigger_event'] ?? ( $saved['trigger_event'] ?? 'post_published' ) ),
				'url'              => (string) $normalized_url,
				'method'           => Webhook::sanitize_method( wp_unslash( $_POST['method'] ?? 'POST' ) ),
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parse_headers_text() validates names as tokens and strips control characters from values; the text sanitizers would corrupt credentials.
				'headers'          => wp_json_encode( Webhook::parse_headers_text( (string) wp_unslash( $_POST['headers'] ?? '' ) ) ),
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON template; stored for machine use and escaped on output.
				'payload_template' => wp_unslash( $_POST['payload_template'] ?? '{}' ),
			);

			$webhook = $saved
				? array_merge( $saved, $form )
				: array_merge(
					array(
						'id'   => 0,
						'name' => 'Test',
					),
					$form
				);
		} else {
			$webhook = $saved;
		}

		if ( empty( $webhook['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'URL is required.', 'dragon-webhook-manager' ) ) );
		}

		// Create sample context
		$context = Payload::sample_context( (string) $webhook['trigger_event'] );

		// Parse payload
		$payload = $this->payload->parse( $webhook['payload_template'], $context, (string) $webhook['trigger_event'] );

		// Deliver with the same filtered headers a triggered delivery gets.
		$result = $this->webhook->deliver( Webhook::with_filtered_headers( $webhook, $payload ), $payload );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'       => __( 'Test webhook sent successfully!', 'dragon-webhook-manager' ),
					'response_code' => $result['response_code'],
					'response_body' => $result['response_body'],
					'duration_ms'   => $result['duration_ms'],
					'duration'      => Admin::duration_label( (int) $result['duration_ms'] ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message'       => $result['error_message'] ? $result['error_message'] : __( 'Webhook delivery failed.', 'dragon-webhook-manager' ),
					'response_code' => $result['response_code'],
					'response_body' => $result['response_body'],
					'duration_ms'   => $result['duration_ms'],
					'duration'      => Admin::duration_label( (int) $result['duration_ms'] ),
				)
			);
		}
	}

	/**
	 * Retry a failed delivery
	 */
	public function handle_retry_delivery(): void {
		check_ajax_referer( 'dragonwebhookmanager_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dragon-webhook-manager' ) ) );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		$log = $this->logger->get( $log_id );

		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log not found.', 'dragon-webhook-manager' ) ) );
		}

		$webhook = $this->webhook->get( $log['webhook_id'] );

		if ( ! $webhook ) {
			wp_send_json_error( array( 'message' => __( 'Webhook not found.', 'dragon-webhook-manager' ) ) );
		}

		// Rows opened by an add-on before this version recorded no request, so
		// there is nothing to resend.
		if ( ! is_string( $log['request_body'] ?? null ) ) {
			wp_send_json_error( array( 'message' => __( 'This log entry has no stored request, so it cannot be resent. Use Test on the webhook instead.', 'dragon-webhook-manager' ) ) );
		}

		// Re-deliver the original payload with freshly filtered headers (a
		// signature covers this body and a current timestamp).
		$payload = $log['request_body'];
		$webhook = Webhook::with_filtered_headers( $webhook, $payload );
		$result  = $this->webhook->deliver( $webhook, $payload );

		// Log the retry
		$new_log_id = $this->logger->log_start( $webhook, $payload );
		$this->logger->log_complete(
			$new_log_id,
			$result['success'] ? 'success' : 'failed',
			$result['response_code'],
			$result['response_body'],
			$result['duration_ms'],
			$result['error_message']
		);

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'       => __( 'Retry successful!', 'dragon-webhook-manager' ),
					'response_code' => $result['response_code'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message'       => $result['error_message'] ? $result['error_message'] : __( 'Retry failed.', 'dragon-webhook-manager' ),
					'response_code' => $result['response_code'],
				)
			);
		}
	}

	/**
	 * Clear all logs
	 */
	public function handle_clear_logs(): void {
		check_ajax_referer( 'dragonwebhookmanager_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dragon-webhook-manager' ) ) );
		}

		if ( ! $this->logger->clear_logs() ) {
			wp_send_json_error( array( 'message' => __( 'The logs could not be cleared. Check the database error log.', 'dragon-webhook-manager' ) ) );
		}

		/**
		 * Fires after every delivery log row was deleted from the logs screen.
		 *
		 * Add-ons that keep state keyed by log ID drop it here.
		 */
		do_action( 'dragonwebhookmanager_logs_cleared' );

		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'dragon-webhook-manager' ) ) );
	}
}
