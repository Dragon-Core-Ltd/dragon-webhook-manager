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
			'method'           => sanitize_key( $_POST['method'] ?? 'POST' ),
			'headers'          => $this->parse_headers( sanitize_textarea_field( wp_unslash( $_POST['headers'] ?? '' ) ) ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON template; stored for machine use and escaped on output.
			'payload_template' => wp_unslash( $_POST['payload_template'] ?? '' ),
			'is_active'        => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		// Validate required fields
		if ( empty( $data['name'] ) || empty( $data['trigger_event'] ) || empty( $data['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Name, trigger, and URL are required.', 'dragon-webhook-manager' ) ) );
		}

		// Validate URL
		if ( ! filter_var( $data['url'], FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'dragon-webhook-manager' ) ) );
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

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$webhook = $id ? $this->webhook->get( $id ) : null;

		if ( ! $webhook ) {
			// Test with form data
			$webhook = array(
				'id'               => 0,
				'name'             => 'Test',
				'trigger_event'    => sanitize_key( $_POST['trigger_event'] ?? 'post_published' ),
				'url'              => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
				'method'           => sanitize_key( $_POST['method'] ?? 'POST' ),
				'headers'          => wp_json_encode( $this->parse_headers( sanitize_textarea_field( wp_unslash( $_POST['headers'] ?? '' ) ) ) ),
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON template; stored for machine use and escaped on output.
				'payload_template' => wp_unslash( $_POST['payload_template'] ?? '{}' ),
			);
		}

		if ( empty( $webhook['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'URL is required.', 'dragon-webhook-manager' ) ) );
		}

		// Create sample context
		$context = $this->get_sample_context( $webhook['trigger_event'] );

		// Parse payload
		$payload = $this->payload->parse( $webhook['payload_template'], $context );

		// Deliver
		$result = $this->webhook->deliver( $webhook, $payload );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'       => __( 'Test webhook sent successfully!', 'dragon-webhook-manager' ),
					'response_code' => $result['response_code'],
					'response_body' => $result['response_body'],
					'duration_ms'   => $result['duration_ms'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message'       => $result['error_message'] ? $result['error_message'] : __( 'Webhook delivery failed.', 'dragon-webhook-manager' ),
					'response_code' => $result['response_code'],
					'response_body' => $result['response_body'],
					'duration_ms'   => $result['duration_ms'],
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

		// Re-deliver with original payload
		$result = $this->webhook->deliver( $webhook, $log['request_body'] );

		// Log the retry
		$new_log_id = $this->logger->log_start( $webhook, $log['request_body'] );
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

		$this->logger->clear_logs();

		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'dragon-webhook-manager' ) ) );
	}

	/**
	 * Parse headers from textarea format
	 */
	private function parse_headers( string $headers_text ): array {
		$headers = array();
		$lines   = explode( "\n", $headers_text );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$parts = explode( ':', $line, 2 );
			if ( count( $parts ) === 2 ) {
				$key             = trim( $parts[0] );
				$value           = trim( $parts[1] );
				$headers[ $key ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Get sample context for testing
	 */
	private function get_sample_context( string $trigger_event ): array {
		$context = array();

		// Create sample post
		$sample_post                = new \stdClass();
		$sample_post->ID            = 123;
		$sample_post->post_title    = 'Sample Post Title';
		$sample_post->post_content  = 'This is sample post content for testing webhooks.';
		$sample_post->post_excerpt  = 'Sample excerpt';
		$sample_post->post_type     = 'post';
		$sample_post->post_status   = 'publish';
		$sample_post->post_author   = 1;
		$sample_post->post_date     = current_time( 'mysql' );
		$sample_post->post_modified = current_time( 'mysql' );

		// Create sample user
		$sample_user                  = new \stdClass();
		$sample_user->ID              = 1;
		$sample_user->user_email      = 'test@example.com';
		$sample_user->user_login      = 'testuser';
		$sample_user->display_name    = 'Test User';
		$sample_user->first_name      = 'Test';
		$sample_user->last_name       = 'User';
		$sample_user->roles           = array( 'subscriber' );
		$sample_user->user_registered = current_time( 'mysql' );

		// Create sample comment
		$sample_comment                       = new \stdClass();
		$sample_comment->comment_ID           = 456;
		$sample_comment->comment_author       = 'Commenter Name';
		$sample_comment->comment_author_email = 'commenter@example.com';
		$sample_comment->comment_author_url   = 'https://example.com';
		$sample_comment->comment_content      = 'This is a sample comment.';
		$sample_comment->comment_date         = current_time( 'mysql' );
		$sample_comment->comment_post_ID      = 123;
		$sample_comment->comment_approved     = '1';

		// Add context based on trigger
		if ( in_array( $trigger_event, array( 'post_published', 'post_updated', 'post_trashed' ), true ) ) {
			$context['post'] = (object) $sample_post;
		}

		if ( in_array( $trigger_event, array( 'user_registered', 'user_login' ), true ) ) {
			$context['user'] = (object) $sample_user;
		}

		if ( in_array( $trigger_event, array( 'comment_submitted', 'comment_approved' ), true ) ) {
			$context['comment'] = (object) $sample_comment;
		}

		return $context;
	}
}
