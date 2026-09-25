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

	public function test_today_bounds_follow_the_site_day_not_the_utc_day(): void {
		// 2026-09-24 01:30 in New York is 05:30 UTC; the site day started at 04:00 UTC.
		$now = strtotime( '2026-09-24 05:30:00 UTC' );

		$this->assertSame(
			array( '2026-09-24 04:00:00', '2026-09-25 04:00:00' ),
			Logger::local_day_bounds( new \DateTimeZone( 'America/New_York' ), $now )
		);
	}

	public function test_today_bounds_cover_a_23_hour_dst_day(): void {
		// 2026-03-29 is the UK spring-forward day: 00:00 GMT to 23:00 UTC.
		$now = strtotime( '2026-03-29 12:00:00 UTC' );

		$this->assertSame(
			array( '2026-03-29 00:00:00', '2026-03-29 23:00:00' ),
			Logger::local_day_bounds( new \DateTimeZone( 'Europe/London' ), $now )
		);
	}

	public function test_stats_count_today_between_the_site_day_bounds(): void {
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'timezone_string' => 'Asia/Tokyo' );

		( new Logger() )->get_stats();

		$today = null;
		foreach ( $this->db->queries as $query ) {
			if ( str_contains( $query['q'], 'created_at >=' ) ) {
				$today = $query;
			}
		}
		$this->assertNotNull( $today, 'today is counted by a created_at range' );
		$this->assertSame( Logger::local_day_bounds( new \DateTimeZone( 'Asia/Tokyo' ), time() ), array_slice( $today['a'], 1 ) );
	}

	private function pending_row(): int {
		return ( new Logger() )->log_start(
			array(
				'id'            => 3,
				'trigger_event' => 'post_published',
				'url'           => 'https://example.test/hook',
				'method'        => 'POST',
			),
			'{}'
		);
	}

	public function test_log_complete_stores_a_body_cut_inside_a_character(): void {
		$id = $this->pending_row();
		// 64 KB cut through the middle of a 2-byte "é".
		$body = str_repeat( 'a', 10 ) . substr( 'é', 0, 1 );

		$this->assertTrue( ( new Logger() )->log_complete( $id, 'success', 200, $body, 12 ) );

		$row = $this->db->rows['wp_dwm_logs'][0];
		$this->assertSame( 'success', $row['status'] );
		$this->assertSame( 200, $row['response_code'] );
		$this->assertSame( str_repeat( 'a', 10 ) . "\u{FFFD}", $row['response_body'] );
	}

	public function test_log_complete_keeps_a_latin1_body_readable(): void {
		$id = $this->pending_row();

		( new Logger() )->log_complete( $id, 'failed', 500, "caf\xE9 &amp; <b>", 12, "bad \xFF" );

		$row = $this->db->rows['wp_dwm_logs'][0];
		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( "caf\u{FFFD} &amp; <b>", $row['response_body'] );
		$this->assertSame( "bad \u{FFFD}", $row['error_message'] );
	}

	public function test_log_complete_still_records_the_outcome_when_the_body_is_refused(): void {
		$id                = $this->pending_row();
		$this->db->refuse  = array( "\u{1F600}" );

		$this->assertTrue( ( new Logger() )->log_complete( $id, 'failed', 502, "smile \u{1F600}", 40, 'HTTP 502' ) );

		$row = $this->db->rows['wp_dwm_logs'][0];
		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( 502, $row['response_code'] );
		$this->assertSame( '', $row['response_body'] );
		$this->assertSame( 'HTTP 502', $row['error_message'] );
	}

	public function test_log_complete_reports_a_write_that_never_lands(): void {
		$id                      = $this->pending_row();
		$this->db->fail_writes   = true;

		$this->assertFalse( ( new Logger() )->log_complete( $id, 'success', 200, 'ok', 5 ) );
	}

	public function test_count_logs_counts_all_rows_or_one_webhook(): void {
		$this->db->insert( 'wp_dwm_logs', array( 'webhook_id' => 1 ) );
		$this->db->insert( 'wp_dwm_logs', array( 'webhook_id' => 2 ) );
		$this->db->insert( 'wp_dwm_logs', array( 'webhook_id' => 2 ) );

		$this->assertSame( 3, ( new Logger() )->count_logs() );
		$this->assertSame( 2, ( new Logger() )->count_logs( 2 ) );
	}

	public function test_logs_pagination_pages_through_every_row(): void {
		$this->assertSame( array( 'page' => 1, 'pages' => 3, 'offset' => 0 ), Admin::logs_pagination( 1, 250, 100 ) );
		$this->assertSame( array( 'page' => 3, 'pages' => 3, 'offset' => 200 ), Admin::logs_pagination( 3, 250, 100 ) );
		// Past the end, or nonsense, lands on a real page.
		$this->assertSame( array( 'page' => 3, 'pages' => 3, 'offset' => 200 ), Admin::logs_pagination( 9, 250, 100 ) );
		$this->assertSame( array( 'page' => 1, 'pages' => 1, 'offset' => 0 ), Admin::logs_pagination( 0, 0, 100 ) );
	}
}
