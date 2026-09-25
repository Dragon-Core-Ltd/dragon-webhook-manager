<?php
/**
 * Log rows opened through the add-on API (Pro auto-retries) record what was
 * sent, so the details modal and a manual Retry have the request to work with.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Integration;
use DragonWebhookManager\Logger;
use DragonWebhookManager\Payload;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Integration create_log -> deliver_webhook -> update_log.
 */
final class AddonLogRequestTest extends TestCase {

	private FakeWpdb $db;

	protected function setUp(): void {
		$this->db                                     = new FakeWpdb();
		$GLOBALS['wpdb']                              = $this->db;
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'blogname' => 'Shop' );
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
	}

	public function test_addon_log_row_records_the_request_that_was_sent(): void {
		$webhook = new Recording_Webhook();
		$this->db->insert(
			'wp_dwm_webhooks',
			array(
				'name'             => 'Hook',
				'trigger_event'    => 'post_published',
				'url'              => 'https://93.184.216.34/hook',
				'method'           => 'PUT',
				'headers'          => '{"Authorization":"Bearer x"}',
				'payload_template' => '{"site":"{{site_name}}"}',
				'is_active'        => 1,
			)
		);
		$webhook_id = $this->db->insert_id;
		add_filter(
			'dragonwebhookmanager_webhook_headers',
			static function ( array $headers ): array {
				$headers['X-Webhook-Signature'] = 'sha256=abc';
				return $headers;
			}
		);

		$integration = new Integration( $webhook, new Payload(), new Logger() );
		$log_id      = $integration->create_log(
			0,
			array(
				'webhook_id'    => $webhook_id,
				'trigger_event' => 'post_published',
				'status'        => 'pending',
			)
		);
		$row = $this->db->rows['wp_dwm_logs'][ array_key_last( $this->db->rows['wp_dwm_logs'] ) ];
		$this->assertSame( 'https://93.184.216.34/hook', $row['request_url'] );
		$this->assertSame( 'PUT', $row['request_method'] );

		$integration->deliver_webhook( null, $webhook->get( $webhook_id ), array() );
		$integration->update_log( $log_id, array( 'status' => 'failed' ) );

		$row = null;
		foreach ( $this->db->rows['wp_dwm_logs'] as $candidate ) {
			if ( (int) $candidate['id'] === $log_id ) {
				$row = $candidate;
			}
		}
		$this->assertSame( '{"site":"Shop"}', $row['request_body'] );
		$this->assertSame( '{"Authorization":"[redacted]","X-Webhook-Signature":"sha256=abc"}', $row['request_headers'] );
		$this->assertSame( 'failed', $row['status'] );
	}
}
