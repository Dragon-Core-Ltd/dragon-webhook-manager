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
}
