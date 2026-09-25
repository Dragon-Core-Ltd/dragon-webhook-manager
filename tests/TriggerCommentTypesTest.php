<?php
/**
 * Comment triggers fire only for real comments unless the
 * dragonwebhookmanager_comment_types filter says otherwise.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Logger;
use DragonWebhookManager\Payload;
use DragonWebhookManager\Triggers;
use DragonWebhookManager\Webhook;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-payload.php';
require_once __DIR__ . '/../includes/class-triggers.php';

/**
 * Tests for the comment triggers' comment type gate.
 */
final class TriggerCommentTypesTest extends TestCase {

	private Webhook $webhook;

	private Triggers $triggers;

	protected function setUp(): void {
		$GLOBALS['wpdb']                              = new FakeWpdb();
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'blogname' => 'Shop' );
		$GLOBALS['dragonwebhookmanager_test_posts']   = array();

		$this->webhook = new class() extends Webhook {
			/**
			 * Trigger events delivered.
			 *
			 * @var array<int, string>
			 */
			public array $sent = array();

			public function get_by_trigger( string $trigger_event ): array {
				return array(
					array(
						'id'               => 1,
						'trigger_event'    => $trigger_event,
						'url'              => 'https://93.184.216.34/hook',
						'method'           => 'POST',
						'headers'          => '{}',
						'payload_template' => '{"event":"{{trigger_event}}"}',
					),
				);
			}

			public function deliver( array $webhook, string $payload ): array {
				unset( $payload );
				$this->sent[] = $webhook['trigger_event'];
				return array(
					'success'       => true,
					'response_code' => 200,
					'response_body' => 'ok',
					'duration_ms'   => 1,
					'error_message' => '',
				);
			}
		};

		$this->triggers = new Triggers( $this->webhook, new Payload(), new Logger() );
	}

	protected function tearDown(): void {
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
	}

	private function comment( int $id, string $type ): \WP_Comment {
		return new \WP_Comment(
			(object) array(
				'comment_ID'       => (string) $id,
				'comment_post_ID'  => '0',
				'comment_approved' => '1',
				'comment_type'     => $type,
			)
		);
	}

	/**
	 * Submit and approve one comment of the given type.
	 */
	private function fire_both( int $id, string $type ): void {
		$comment = $this->comment( $id, $type );
		$this->triggers->handle_comment_submitted( $id, $comment );
		$this->triggers->handle_comment_transition( 'approved', 'unapproved', $comment );
	}

	public function test_real_comments_fire_both_triggers(): void {
		$this->fire_both( 1, 'comment' );
		$this->fire_both( 2, '' );

		$this->assertSame(
			array( 'comment_submitted', 'comment_approved', 'comment_submitted', 'comment_approved' ),
			$this->webhook->sent
		);
	}

	public function test_product_reviews_fire_both_triggers(): void {
		$this->fire_both( 3, 'review' );

		$this->assertSame( array( 'comment_submitted', 'comment_approved' ), $this->webhook->sent );
	}

	public function test_internal_and_other_types_fire_nothing(): void {
		$id = 10;
		foreach ( array( 'order_note', 'webhook_delivery', 'action_log', 'note', 'pingback', 'trackback' ) as $type ) {
			$this->fire_both( $id++, $type );
		}

		$this->assertSame( array(), $this->webhook->sent );
	}

	public function test_the_filter_can_add_and_remove_types(): void {
		add_filter(
			'dragonwebhookmanager_comment_types',
			static function ( array $types, string $trigger_event ): array {
				$types[] = 'pingback';
				if ( 'comment_approved' === $trigger_event ) {
					$types = array_diff( $types, array( 'comment' ) );
				}
				return $types;
			},
			10,
			2
		);

		$this->fire_both( 20, 'pingback' );
		$this->fire_both( 21, 'comment' );

		$this->assertSame(
			array( 'comment_submitted', 'comment_approved', 'comment_submitted' ),
			$this->webhook->sent
		);
	}

	public function test_a_broken_filter_falls_back_to_comments(): void {
		add_filter( 'dragonwebhookmanager_comment_types', static fn() => null );

		$this->fire_both( 30, 'comment' );
		$this->fire_both( 31, 'order_note' );

		$this->assertSame( array( 'comment_submitted', 'comment_approved' ), $this->webhook->sent );
	}
}
