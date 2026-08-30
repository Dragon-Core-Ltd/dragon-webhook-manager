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

		$payload = $this->payload->parse( (string) ( $webhook['payload_template'] ?? '{}' ), (array) $context );

		$headers = json_decode( $webhook['headers'] ?? '{}', true );
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}

		/** This filter is documented in includes/class-triggers.php */
		$headers            = apply_filters( 'dragonwebhookmanager_webhook_headers', $headers, $webhook, $payload );
		$webhook['headers'] = wp_json_encode( $headers );

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

		return $this->logger->create( (array) $data );
	}

	/**
	 * Complete a log entry with the delivery outcome.
	 *
	 * @param int   $log_id Log ID.
	 * @param array $data   Outcome data.
	 */
	public function update_log( $log_id, $data ): void {
		$data = (array) $data;

		$this->logger->log_complete(
			(int) $log_id,
			(string) ( $data['status'] ?? 'failed' ),
			(int) ( $data['response_code'] ?? 0 ),
			(string) ( $data['response_body'] ?? '' ),
			(int) ( $data['duration_ms'] ?? 0 ),
			(string) ( $data['error_message'] ?? '' )
		);
	}
}
