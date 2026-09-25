<?php
/**
 * AJAX handlers: method handling, Test/Retry header filtering, unsaved form
 * edits on Test, retry of add-on log rows, and Clear Logs.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Ajax;
use DragonWebhookManager\Logger;
use DragonWebhookManager\Payload;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Ajax.
 */
final class AjaxHandlersTest extends TestCase {

	private FakeWpdb $db;

	private Recording_Webhook $webhook;

	private Ajax $ajax;

	protected function setUp(): void {
		$this->db                                     = new FakeWpdb();
		$GLOBALS['wpdb']                              = $this->db;
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'blogname' => 'Shop' );
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
		$GLOBALS['dragonwebhookmanager_test_actions'] = array();
		$_POST                                        = array();

		$this->webhook = new Recording_Webhook();
		$this->ajax    = new Ajax( $this->webhook, new Logger(), new Payload() );
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Run a handler and return the JSON response it ended with.
	 */
	private function call( string $handler ): \Dragon_Test_Json_Response {
		try {
			$this->ajax->$handler();
		} catch ( \Dragon_Test_Json_Response $response ) {
			return $response;
		}
		$this->fail( $handler . ' returned without sending a JSON response' );
	}

	private function saved_webhook( array $overrides = array() ): int {
		$this->db->insert(
			'wp_dwm_webhooks',
			array_merge(
				array(
					'name'             => 'Saved',
					'trigger_event'    => 'post_published',
					'url'              => 'https://93.184.216.34/saved',
					'method'           => 'POST',
					'headers'          => '{"X-Saved":"1"}',
					'payload_template' => '{"saved":true}',
					'is_active'        => 1,
				),
				$overrides
			)
		);
		return $this->db->insert_id;
	}

	private function add_signing_filter(): void {
		add_filter(
			'dragonwebhookmanager_webhook_headers',
			static function ( array $headers, array $webhook, string $payload ): array {
				$headers['X-Signed'] = (int) ( $webhook['id'] ?? 0 ) . ':' . md5( $payload );
				return $headers;
			}
		);
	}

	public function test_save_keeps_put_and_patch(): void {
		foreach ( array( 'PUT', 'PATCH', 'put' ) as $method ) {
			$this->db->rows = array();
			$_POST          = array(
				'name'          => 'Hook',
				'trigger_event' => 'post_published',
				'url'           => 'https://example.test/hook',
				'method'        => $method,
			);

			$response = $this->call( 'handle_save_webhook' );

			$this->assertTrue( $response->success );
			$this->assertSame( strtoupper( $method ), $this->db->rows['wp_dwm_webhooks'][0]['method'], $method );
		}
	}

	public function test_save_refuses_internal_ip_literals_and_localhost(): void {
		$targets = array(
			'http://127.0.0.1:9/hook',
			'https://169.254.169.254/latest/meta-data',
			'http://[::1]/hook',
			'https://10.0.0.5/hook',
			'http://localhost:8080/hook',
			'http://api.localhost/hook',
		);
		foreach ( $targets as $url ) {
			$this->db->rows = array();
			$_POST          = array(
				'name'          => 'Hook',
				'trigger_event' => 'post_published',
				'url'           => $url,
			);

			$response = $this->call( 'handle_save_webhook' );

			$this->assertFalse( $response->success, $url );
			$this->assertSame( 'Requests to internal or private IP addresses are not allowed.', $response->data['message'], $url );
			$this->assertEmpty( $this->db->rows['wp_dwm_webhooks'] ?? array(), $url );
		}
	}

	public function test_save_keeps_hostnames_and_public_ip_literals(): void {
		// A DNS name is not resolved at save time; the resolved-address check runs at send.
		foreach ( array( 'https://hooks.example.test/in', 'https://93.184.216.34/hook' ) as $url ) {
			$this->db->rows = array();
			$_POST          = array(
				'name'          => 'Hook',
				'trigger_event' => 'post_published',
				'url'           => $url,
			);

			$this->assertTrue( $this->call( 'handle_save_webhook' )->success, $url );
		}
	}

	public function test_save_honours_the_internal_url_filter(): void {
		add_filter(
			'dragonwebhookmanager_is_internal_url',
			static function (): bool {
				return false;
			}
		);
		$_POST = array(
			'name'          => 'Hook',
			'trigger_event' => 'post_published',
			'url'           => 'http://127.0.0.1:9/hook',
		);

		$this->assertTrue( $this->call( 'handle_save_webhook' )->success );
	}

	public function test_save_falls_back_to_post_for_unknown_method(): void {
		$_POST = array(
			'name'          => 'Hook',
			'trigger_event' => 'post_published',
			'url'           => 'https://example.test/hook',
			'method'        => 'DELETE',
		);

		$this->call( 'handle_save_webhook' );

		$this->assertSame( 'POST', $this->db->rows['wp_dwm_webhooks'][0]['method'] );
	}

	public function test_save_keeps_percent_sequences_and_angle_brackets_in_header_values(): void {
		$_POST = array(
			'name'          => 'Hook',
			'trigger_event' => 'post_published',
			'url'           => 'https://example.test/hook',
			'headers'       => "Authorization: Bearer ab%2Fcd<ef>\nX-Bad Name: dropped\nX-Ok:  v1  \r\nX-Inject: a\x07b",
		);

		$this->call( 'handle_save_webhook' );

		$this->assertSame(
			array(
				'Authorization' => 'Bearer ab%2Fcd<ef>',
				'X-Ok'          => 'v1',
				'X-Inject'      => 'ab',
			),
			json_decode( $this->db->rows['wp_dwm_webhooks'][0]['headers'], true )
		);
	}

	/**
	 * WordPress adds magic quotes to $_POST, so the request is slashed here as
	 * core does. Every field must come out unslashed exactly once.
	 */
	public function test_save_unslashes_each_field_once_on_create(): void {
		$template = '{"text":"line one\nline two","quote":"say \"hi\"","path":"C:\\\\dir"}';
		$_POST    = wp_slash(
			array(
				'name'             => 'Back\\slash',
				'trigger_event'    => 'post_published',
				'url'              => 'https://example.test/hook',
				'headers'          => 'X-Path: a\\b',
				'payload_template' => $template,
			)
		);

		$this->assertTrue( $this->call( 'handle_save_webhook' )->success );

		$row = $this->db->rows['wp_dwm_webhooks'][0];
		$this->assertSame( $template, $row['payload_template'] );
		$this->assertNotNull( json_decode( $row['payload_template'] ), 'the stored template is still valid JSON' );
		$this->assertSame( 'Back\\slash', $row['name'] );
		$this->assertSame( array( 'X-Path' => 'a\\b' ), json_decode( $row['headers'], true ) );
	}

	public function test_save_unslashes_the_template_once_on_update(): void {
		$id       = $this->saved_webhook();
		$template = '{"text":"a\nb","quote":"\"q\"","slash":"\\\\"}';
		$_POST    = wp_slash(
			array(
				'id'               => (string) $id,
				'name'             => 'Saved',
				'trigger_event'    => 'post_published',
				'url'              => 'https://example.test/hook',
				'payload_template' => $template,
			)
		);

		$this->assertTrue( $this->call( 'handle_save_webhook' )->success );

		$this->assertSame( $template, $this->db->rows['wp_dwm_webhooks'][0]['payload_template'] );
	}

	public function test_unsaved_test_sends_an_uppercase_method(): void {
		$_POST = array(
			'url'           => 'https://93.184.216.34/hook',
			'method'        => 'put',
			'trigger_event' => 'post_published',
		);

		$this->assertTrue( $this->call( 'handle_test_webhook' )->success );
		$this->assertSame( 'PUT', $this->webhook->sent[0][0]['method'] );
	}

	public function test_test_applies_the_header_filter_for_a_saved_webhook(): void {
		$this->add_signing_filter();
		$id    = $this->saved_webhook();
		$_POST = array( 'id' => (string) $id );

		$this->call( 'handle_test_webhook' );

		list( $sent, $payload ) = $this->webhook->sent[0];
		$headers                = json_decode( $sent['headers'], true );
		$this->assertSame( $id . ':' . md5( $payload ), $headers['X-Signed'] );
		$this->assertSame( '1', $headers['X-Saved'] );
	}

	public function test_test_on_a_saved_webhook_uses_the_unsaved_form_edits(): void {
		$this->add_signing_filter();
		$id    = $this->saved_webhook();
		$_POST = array(
			'id'               => (string) $id,
			'url'              => 'https://93.184.216.34/edited',
			'method'           => 'PATCH',
			'trigger_event'    => 'post_published',
			'headers'          => 'X-Edited: yes',
			'payload_template' => '{"edited":true}',
		);

		$this->call( 'handle_test_webhook' );

		list( $sent, $payload ) = $this->webhook->sent[0];
		$this->assertSame( 'https://93.184.216.34/edited', $sent['url'] );
		$this->assertSame( 'PATCH', $sent['method'] );
		$this->assertSame( '{"edited":true}', $payload );
		$headers = json_decode( $sent['headers'], true );
		$this->assertSame( 'yes', $headers['X-Edited'] );
		$this->assertArrayNotHasKey( 'X-Saved', $headers );
		$this->assertSame( $id . ':' . md5( $payload ), $headers['X-Signed'], 'still signed with the saved webhook identity' );
	}

	public function test_test_with_edits_refuses_an_invalid_url(): void {
		$id    = $this->saved_webhook();
		$_POST = array(
			'id'  => (string) $id,
			'url' => 'javascript:alert(1)',
		);

		$this->assertFalse( $this->call( 'handle_test_webhook' )->success );
		$this->assertSame( array(), $this->webhook->sent );
	}

	public function test_retry_applies_the_header_filter_and_resends_the_stored_body(): void {
		$this->add_signing_filter();
		$id = $this->saved_webhook();
		$this->db->insert(
			'wp_dwm_logs',
			array(
				'webhook_id'   => $id,
				'request_body' => '{"original":1}',
				'status'       => 'failed',
			)
		);
		$_POST = array( 'log_id' => (string) $this->db->insert_id );

		$this->assertTrue( $this->call( 'handle_retry_delivery' )->success );

		list( $sent, $payload ) = $this->webhook->sent[0];
		$this->assertSame( '{"original":1}', $payload );
		$this->assertSame( $id . ':' . md5( $payload ), json_decode( $sent['headers'], true )['X-Signed'] );
	}

	public function test_retry_refuses_a_log_row_without_a_stored_body(): void {
		$id = $this->saved_webhook();
		$this->db->insert(
			'wp_dwm_logs',
			array(
				'webhook_id'   => $id,
				'request_body' => null,
				'status'       => 'failed',
			)
		);
		$_POST = array( 'log_id' => (string) $this->db->insert_id );

		$response = $this->call( 'handle_retry_delivery' );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'no stored request', $response->data['message'] );
		$this->assertSame( array(), $this->webhook->sent );
	}

	public function test_clear_logs_deletes_rows_and_announces_it(): void {
		$this->db->insert( 'wp_dwm_logs', array( 'webhook_id' => 1 ) );

		$this->assertTrue( $this->call( 'handle_clear_logs' )->success );

		$this->assertSame( 'DELETE FROM %i', end( $this->db->queries )['q'], 'DELETE keeps AUTO_INCREMENT, TRUNCATE resets it' );
		$this->assertSame( array(), $this->db->rows['wp_dwm_logs'] );
		$this->assertContains( array( 'dragonwebhookmanager_logs_cleared' ), $GLOBALS['dragonwebhookmanager_test_actions'] );
	}

	public function test_clear_logs_reports_a_failed_delete(): void {
		$this->db->query_result = false;

		$this->assertFalse( $this->call( 'handle_clear_logs' )->success );
		$this->assertNotContains( array( 'dragonwebhookmanager_logs_cleared' ), $GLOBALS['dragonwebhookmanager_test_actions'] );
	}
}
