<?php
/**
 * Integration API for add-ons: re-delivery and logging hooks.
 *
 * Other plugins re-deliver and log through a set of `dragonwebhookmanager_*`
 * hooks rather than reaching into this plugin's classes directly. This class
 * implements those hooks against the custom-table storage, so callers never
 * need to assume webhooks/logs are posts.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the add-on hook API.
 */
class Integration {

	/**
	 * Webhook model.
	 *
	 * @var Webhook
	 */
	private Webhook $webhook;

	/**
	 * Payload renderer.
	 *
	 * @var Payload
	 */
	private Payload $payload;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Last request sent per webhook ID by deliver_webhook(), as [webhook, payload].
	 *
	 * @var array<int, array>
	 */
	private array $sent = array();

	/**
	 * Constructor.
	 *
	 * @param Webhook $webhook Webhook model.
	 * @param Payload $payload Payload renderer.
	 * @param Logger  $logger  Logger.
	 */
	public function __construct( Webhook $webhook, Payload $payload, Logger $logger ) {
		$this->webhook = $webhook;
		$this->payload = $payload;
		$this->logger  = $logger;

		add_filter( 'dragonwebhookmanager_get_webhook', array( $this, 'get_webhook' ), 10, 2 );
		add_filter( 'dragonwebhookmanager_deliver_webhook', array( $this, 'deliver_webhook' ), 10, 3 );
		add_filter( 'dragonwebhookmanager_create_log', array( $this, 'create_log' ), 10, 2 );
		add_action( 'dragonwebhookmanager_update_log', array( $this, 'update_log' ), 10, 2 );
	}

	/**
	 * Resolve a webhook by ID.
	 *
	 * @param mixed $value      Short-circuit value.
	 * @param int   $webhook_id Webhook ID.
	 * @return array|null
	 */
	public function get_webhook( $value, $webhook_id ) {
		if ( null !== $value ) {
			return $value;
		}

		return $this->webhook->get( (int) $webhook_id );
	}

	/**
	 * Render the payload and deliver a webhook through the safe delivery path.
	 *
	 * Re-applies `dragonwebhookmanager_webhook_headers` so filtered headers
	 * (for example a request signature) are added to the retried request,
	 * exactly as on the original delivery.
	 *
	 * @param mixed $result  Short-circuit value.
	 * @param array $webhook Webhook data.
	 * @param array $context Trigger context for template variables.
	 * @return array Delivery result from Webhook::deliver().
	 */
	public function deliver_webhook( $result, $webhook, $context ) {
		if ( null !== $result ) {
			return $result;
		}

		$payload = $this->payload->parse( (string) ( $webhook['payload_template'] ?? '{}' ), (array) $context, (string) ( $webhook['trigger_event'] ?? '' ) );

		$webhook = Webhook::with_filtered_headers( (array) $webhook, $payload );

		// Kept for update_log(), which records what was sent on the log row
		// the caller opened through create_log() before this delivery.
		$this->sent[ (int) ( $webhook['id'] ?? 0 ) ] = array( $webhook, $payload );

		return $this->webhook->deliver( $webhook, $payload );
	}

	/**
	 * Open a log entry.
	 *
	 * @param mixed $value Short-circuit value.
	 * @param array $data  Log data.
	 * @return int Log ID.
	 */
	public function create_log( $value, $data ) {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}

		$data = (array) $data;

		// Record where the request goes, so the row reads like one the free
		// plugin opened itself. The body and final headers are added by
		// update_log() once the delivery has been made.
		$webhook = empty( $data['webhook_id'] ) ? null : $this->webhook->get( (int) $data['webhook_id'] );
		if ( is_array( $webhook ) ) {
			$data += array(
				'request_url'     => (string) ( $webhook['url'] ?? '' ),
				'request_method'  => (string) ( $webhook['method'] ?? '' ),
				'request_headers' => (string) ( $webhook['headers'] ?? '' ),
			);
		}

		return $this->logger->create( $data );
	}

	/**
	 * Complete a log entry with the delivery outcome.
	 *
	 * @param int   $log_id Log ID.
	 * @param array $data   Outcome data.
	 */
	public function update_log( $log_id, $data ): void {
		$data   = (array) $data;
		$log_id = (int) $log_id;

		// Fill in the request deliver_webhook() sent for this row's webhook,
		// when the row does not hold one yet.
		$log = $log_id ? $this->logger->get( $log_id ) : null;
		if ( is_array( $log ) && null === ( $log['request_body'] ?? null ) ) {
			$webhook_id = (int) ( $log['webhook_id'] ?? 0 );
			if ( isset( $this->sent[ $webhook_id ] ) ) {
				list( $sent_webhook, $payload ) = $this->sent[ $webhook_id ];
				unset( $this->sent[ $webhook_id ] );
				$this->logger->fill_request( $log_id, $sent_webhook, $payload );
			}
		}

		$this->logger->log_complete(
			$log_id,
			(string) ( $data['status'] ?? 'failed' ),
			(int) ( $data['response_code'] ?? 0 ),
			(string) ( $data['response_body'] ?? '' ),
			(int) ( $data['duration_ms'] ?? 0 ),
			(string) ( $data['error_message'] ?? '' )
		);
	}
}
