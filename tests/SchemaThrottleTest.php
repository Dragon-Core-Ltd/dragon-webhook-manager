<?php
/**
 * A failed table creation is retried at most every 10 minutes from
 * maybe_upgrade() and surfaced to administrators; success clears both.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Plugin::maybe_upgrade() retry throttle and admin notice.
 */
final class SchemaThrottleTest extends TestCase {

	private Plugin $plugin;

	protected function setUp(): void {
		$GLOBALS['wpdb']                                      = new FakeWpdb();
		$GLOBALS['dragonwebhookmanager_test_options']         = array();
		$GLOBALS['dragonwebhookmanager_test_transients']      = array();
		$GLOBALS['dragonwebhookmanager_test_dbdelta_calls']   = array();
		$GLOBALS['dragonwebhookmanager_test_dbdelta_creates'] = false;
		$GLOBALS['dragonwebhookmanager_test_can']             = true;

		$this->plugin = ( new \ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
	}

	public function test_failure_sets_throttle_and_records_missing_tables(): void {
		$this->plugin->maybe_upgrade();

		$this->assertNotFalse( get_transient( 'dragonwebhookmanager_schema_retry' ) );
		$failure = get_option( 'dragonwebhookmanager_schema_failure' );
		$this->assertSame( array( 'wp_dwm_webhooks', 'wp_dwm_logs' ), array_keys( $failure['tables'] ) );
		$this->assertEqualsWithDelta( time(), $failure['time'], 5 );
	}

	public function test_attempt_is_skipped_while_throttled(): void {
		$this->plugin->maybe_upgrade();
		$this->assertCount( 2, $GLOBALS['dragonwebhookmanager_test_dbdelta_calls'] );

		$this->plugin->maybe_upgrade();
		$this->assertCount( 2, $GLOBALS['dragonwebhookmanager_test_dbdelta_calls'] );
	}

	public function test_attempt_is_retried_after_throttle_expires(): void {
		$this->plugin->maybe_upgrade();
		$GLOBALS['dragonwebhookmanager_test_transients']['dragonwebhookmanager_schema_retry']['expires'] = time() - 1;

		$this->plugin->maybe_upgrade();

		$this->assertCount( 4, $GLOBALS['dragonwebhookmanager_test_dbdelta_calls'] );
	}

	public function test_activation_is_not_throttled(): void {
		$this->plugin->maybe_upgrade();
		$GLOBALS['dragonwebhookmanager_test_dbdelta_creates'] = true;

		$method = new \ReflectionMethod( Plugin::class, 'create_tables' );
		$method->setAccessible( true );
		$method->invoke( $this->plugin );

		$this->assertCount( 4, $GLOBALS['dragonwebhookmanager_test_dbdelta_calls'] );
		$this->assertFalse( get_option( 'dragonwebhookmanager_schema_failure' ) );
		$this->assertFalse( get_transient( 'dragonwebhookmanager_schema_retry' ) );
		$this->assertSame( DRAGONWEBHOOKMANAGER_VERSION, get_option( 'dragonwebhookmanager_db_version' ) );
	}

	private function notice(): string {
		ob_start();
		$this->plugin->schema_failure_notice();
		return (string) ob_get_clean();
	}

	public function test_notice_only_after_failure_and_only_for_admins(): void {
		$this->assertSame( '', $this->notice() );

		$this->plugin->maybe_upgrade();

		$html = $this->notice();
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'wp_dwm_webhooks', $html );
		$this->assertStringContainsString( 'database tables: wp_dwm_webhooks, wp_dwm_logs.', $html );

		$GLOBALS['dragonwebhookmanager_test_can'] = false;
		$this->assertSame( '', $this->notice() );
	}
}
