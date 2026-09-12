<?php
/**
 * Activation/upgrade bookkeeping must follow what actually landed in the
 * database: the schema stamp needs the tables, the legacy-option delete needs
 * the copy.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Plugin::create_tables() and Plugin::migrate_legacy_prefix().
 */
final class PluginUpgradeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdb']                                       = new FakeWpdb();
		$GLOBALS['dragonwebhookmanager_test_options']          = array();
		$GLOBALS['dragonwebhookmanager_test_readonly_options'] = array();
		$GLOBALS['dragonwebhookmanager_test_dbdelta_calls']    = array();
		$GLOBALS['dragonwebhookmanager_test_dbdelta_creates']  = true;
		$GLOBALS['dragonwebhookmanager_test_transients']       = array();
	}

	private function create_tables(): void {
		$plugin = ( new \ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( Plugin::class, 'create_tables' );
		$method->setAccessible( true );
		$method->invoke( $plugin );
	}

	private function migrate(): void {
		$method = new \ReflectionMethod( Plugin::class, 'migrate_legacy_prefix' );
		$method->setAccessible( true );
		$method->invoke( null );
	}

	public function test_db_version_stamped_when_both_tables_exist(): void {
		$this->create_tables();

		$this->assertCount( 2, $GLOBALS['dragonwebhookmanager_test_dbdelta_calls'] );
		$this->assertSame( DRAGONWEBHOOKMANAGER_VERSION, get_option( 'dragonwebhookmanager_db_version' ) );
	}

	public function test_db_version_not_stamped_when_a_table_is_missing(): void {
		$GLOBALS['dragonwebhookmanager_test_dbdelta_creates'] = false;

		$this->create_tables();

		$this->assertFalse( get_option( 'dragonwebhookmanager_db_version' ) );
	}

	public function test_legacy_option_removed_only_after_copy_lands(): void {
		$GLOBALS['dragonwebhookmanager_test_options']['dwm_default_timeout'] = '45';

		$this->migrate();

		$this->assertSame( '45', get_option( 'dragonwebhookmanager_default_timeout' ) );
		$this->assertNull( get_option( 'dwm_default_timeout', null ) );
	}

	public function test_legacy_option_kept_when_copy_fails(): void {
		$GLOBALS['dragonwebhookmanager_test_options']['dwm_default_timeout'] = '45';
		$GLOBALS['dragonwebhookmanager_test_readonly_options'][]             = 'dragonwebhookmanager_default_timeout';

		$this->migrate();

		$this->assertSame( '45', get_option( 'dwm_default_timeout' ), 'legacy value survives a failed copy for the next attempt' );
	}
}
