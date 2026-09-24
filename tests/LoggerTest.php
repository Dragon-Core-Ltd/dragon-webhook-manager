<?php
/**
 * Log rows are timestamped in UTC by PHP, cleanup compares against a UTC
 * cutoff, and the stored redaction marker is shown translated.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Admin;
use DragonWebhookManager\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Logger writes and Admin::display_headers().
 */
final class LoggerTest extends TestCase {

	private FakeWpdb $db;

	protected function setUp(): void {
		$this->db                                     = new FakeWpdb();
		$GLOBALS['wpdb']                              = $this->db;
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'gmt_offset' => 5 );
	}

	public function test_log_start_writes_a_utc_created_at(): void {
		$before = gmdate( 'Y-m-d H:i:s' );
		( new Logger() )->log_start(
			array(
				'id'            => 3,
				'trigger_event' => 'post_published',
				'url'           => 'https://example.test/hook',
				'method'        => 'POST',
				'headers'       => '{"Authorization":"Bearer abc","X-Id":"1"}',
			),
			'{}'
		);
		$after = gmdate( 'Y-m-d H:i:s' );

		$row = $this->db->rows['wp_dwm_logs'][0];
		$this->assertGreaterThanOrEqual( $before, $row['created_at'] );
		$this->assertLessThanOrEqual( $after, $row['created_at'] );
		$this->assertSame( '{"Authorization":"[redacted]","X-Id":"1"}', $row['request_headers'] );
	}

	public function test_create_writes_a_utc_created_at(): void {
		$id = ( new Logger() )->create( array( 'webhook_id' => 3, 'trigger_event' => 'post_published' ) );

		$this->assertSame( 1, $id );
		$this->assertLessThanOrEqual( 5, abs( strtotime( $this->db->rows['wp_dwm_logs'][0]['created_at'] . ' UTC' ) - time() ) );
	}

	public function test_display_headers_translates_only_the_marker(): void {
		$this->assertSame(
			'{"Authorization":"[redacted]","X-Id":"[redacted] no"}',
			Admin::display_headers( '{"Authorization":"[redacted]","X-Id":"[redacted] no"}' )
		);
		$this->assertSame( 'not json', Admin::display_headers( 'not json' ) );
	}

	public function test_with_details_passes_headers_through_display(): void {
		$rows = Admin::with_details(
			array(
				array(
					'id'              => 1,
					'status'          => 'failed',
					'request_headers' => '{"Token":"[redacted]"}',
				),
			)
		);

		$this->assertSame( '{"Token":"[redacted]"}', $rows[0]['request_headers'] );
	}
}
