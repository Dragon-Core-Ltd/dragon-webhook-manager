<?php
/**
 * Trigger and variable labels are built at read time, and the trigger
 * registry keeps the key => label/category/hook shape add-ons extend.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Payload;
use DragonWebhookManager\Triggers;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-payload.php';
require_once __DIR__ . '/../includes/class-logger.php';
require_once __DIR__ . '/../includes/class-triggers.php';

/**
 * Tests for Triggers::get_triggers(), get_triggers_grouped() and Payload::get_variable_reference().
 */
final class TriggerLabelsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
	}

	public function test_constant_holds_codes_only(): void {
		foreach ( Triggers::TRIGGERS as $key => $trigger ) {
			$this->assertArrayNotHasKey( 'label', $trigger, $key );
			$this->assertMatchesRegularExpression( '/^[a-z_]+$/', $trigger['category'], $key );
		}
	}

	public function test_builtin_triggers_keep_keys_and_gain_labels(): void {
		$triggers = Triggers::get_triggers();

		$this->assertSame( array_keys( Triggers::TRIGGERS ), array_keys( $triggers ) );
		$this->assertSame(
			array(
				'label'    => 'Post Published',
				'category' => 'Content',
				'hook'     => 'transition_post_status',
			),
			$triggers['post_published']
		);
		$this->assertSame( 'Comment Approved', Triggers::get_label( 'comment_approved' ) );
		$this->assertSame( 'unknown_event', Triggers::get_label( 'unknown_event' ) );
	}

	public function test_filter_receives_labelled_triggers_and_can_add_more(): void {
		add_filter(
			'dragonwebhookmanager_triggers',
			function ( array $triggers ): array {
				$this->assertSame( 'User Login', $triggers['user_login']['label'] );
				$triggers['wc_order_paid'] = array(
					'label'    => 'Order Paid',
					'category' => 'WooCommerce Orders',
					'hook'     => 'woocommerce_payment_complete',
				);
				return $triggers;
			}
		);

		$grouped = Triggers::get_triggers_grouped();

		$this->assertSame( array( 'label' => 'Order Paid' ), $grouped['WooCommerce Orders']['wc_order_paid'] );
		$this->assertSame( array( 'label' => 'User Registered' ), $grouped['User']['user_registered'] );
	}

	public function test_a_bad_filter_return_falls_back_to_labelled_builtins(): void {
		add_filter(
			'dragonwebhookmanager_triggers',
			static function (): bool {
				return false;
			}
		);
		$this->assertSame( 'Post Trashed', Triggers::get_triggers()['post_trashed']['label'] );

		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
		add_filter(
			'dragonwebhookmanager_triggers',
			static function (): array {
				return array();
			}
		);
		$this->assertSame( 'Post Trashed', Triggers::get_triggers()['post_trashed']['label'] );
	}

	public function test_variable_reference_keeps_placeholders(): void {
		$reference = Payload::get_variable_reference();

		$this->assertSame( array( 'Global', 'Post', 'User', 'Comment' ), array_keys( $reference ) );
		$this->assertSame( 'User roles', $reference['User']['{{user_role}}'] );
		$this->assertSame( 'Site URL', $reference['Global']['{{site_url}}'] );
	}
}
