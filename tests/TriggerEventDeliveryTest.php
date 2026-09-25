<?php
/**
 * The re-delivery path used by add-ons (Pro retries) renders
 * {{trigger_event}} from the webhook's own trigger.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Integration;
use DragonWebhookManager\Logger;
use DragonWebhookManager\Payload;
use DragonWebhookManager\Webhook;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-payload.php';
require_once __DIR__ . '/../includes/class-integration.php';

/**
 * Tests for Integration::deliver_webhook().
 */
final class TriggerEventDeliveryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'blogname' => 'Shop' );
	}

	public function test_redelivery_fills_trigger_event_from_the_webhook(): void {
		$webhook = new class() extends Webhook {
			/**
			 * Payload handed to deliver().
			 *
			 * @var string
			 */
			public string $sent = '';

			public function deliver( array $webhook, string $payload ): array {
				$this->sent = $payload;
				return array( 'success' => true );
			}
		};

		$integration = new Integration( $webhook, new Payload(), new Logger() );
		$integration->deliver_webhook(
			null,
			array(
				'trigger_event'    => 'comment_approved',
				'payload_template' => '{"event":"{{trigger_event}}","site":"{{site_name}}"}',
				'headers'          => '{}',
			),
			array()
		);

		$this->assertSame( '{"event":"comment_approved","site":"Shop"}', $webhook->sent );
	}
}
