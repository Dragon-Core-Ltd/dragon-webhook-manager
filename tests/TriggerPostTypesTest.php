<?php
/**
 * Post triggers fire only for viewable post types unless the
 * dragonwebhookmanager_post_types filter says otherwise.
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
 * Tests for the post triggers' post type gate.
 */
final class TriggerPostTypesTest extends TestCase {

	private Webhook $webhook;

	private Triggers $triggers;

	private array $saved_types;

	protected function setUp(): void {
		$GLOBALS['wpdb']                              = new FakeWpdb();
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
		$GLOBALS['dragonwebhookmanager_test_options'] = array( 'blogname' => 'Shop' );
		$GLOBALS['dragonwebhookmanager_test_posts']   = array();
		$this->saved_types                            = $GLOBALS['dragonwebhookmanager_test_post_types'];

		$GLOBALS['dragonwebhookmanager_test_post_types'] += array(
			'oembed_cache'      => (object) array( 'name' => 'oembed_cache', 'public' => false, 'publicly_queryable' => false, '_builtin' => true ),
			'wp_template'       => (object) array( 'name' => 'wp_template', 'public' => false, 'publicly_queryable' => false, '_builtin' => true ),
			'wp_global_styles'  => (object) array( 'name' => 'wp_global_styles', 'public' => false, 'publicly_queryable' => false, '_builtin' => true ),
			'wp_navigation'     => (object) array( 'name' => 'wp_navigation', 'public' => false, 'publicly_queryable' => false, '_builtin' => true ),
			'book'              => (object) array( 'name' => 'book', 'public' => true, 'publicly_queryable' => true, '_builtin' => false ),
			'private_note'      => (object) array( 'name' => 'private_note', 'public' => false, 'publicly_queryable' => false, '_builtin' => false ),
		);

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
		$GLOBALS['dragonwebhookmanager_test_post_types'] = $this->saved_types;
		$GLOBALS['dragonwebhookmanager_test_filters']    = array();
	}

	private function post( int $id, string $type, string $status = 'publish' ): \WP_Post {
		$post = new \WP_Post(
			(object) array(
				'ID'          => $id,
				'post_type'   => $type,
				'post_status' => $status,
				'post_title'  => 'T' . $id,
			)
		);
		$GLOBALS['dragonwebhookmanager_test_posts'][ $id ] = $post;
		return $post;
	}

	/**
	 * Publish, update and trash one post of the given type.
	 */
	private function fire_all( int $id, string $type ): void {
		$post = $this->post( $id, $type );
		$this->triggers->handle_post_transition( 'publish', 'draft', $post );
		$this->triggers->handle_post_updated( $id, $post, $post );
		$this->triggers->handle_post_trashed( $id );
	}

	public function test_viewable_types_fire_every_post_trigger(): void {
		$this->fire_all( 10, 'post' );
		$this->fire_all( 11, 'page' );
		$this->fire_all( 12, 'book' );

		$this->assertSame(
			array_merge( ...array_fill( 0, 3, array( 'post_published', 'post_updated', 'post_trashed' ) ) ),
			$this->webhook->sent
		);
	}

	public function test_internal_types_fire_nothing(): void {
		$id = 20;
		foreach ( array( 'oembed_cache', 'wp_template', 'wp_global_styles', 'wp_navigation', 'private_note' ) as $type ) {
			$this->fire_all( $id++, $type );
		}

		$this->assertSame( array(), $this->webhook->sent );
	}

	public function test_the_filter_can_add_and_remove_types(): void {
		add_filter(
			'dragonwebhookmanager_post_types',
			static function ( array $types, string $trigger_event ): array {
				$types[] = 'private_note';
				if ( 'post_trashed' === $trigger_event ) {
					$types = array_diff( $types, array( 'post' ) );
				}
				return $types;
			}
		);

		$this->fire_all( 30, 'private_note' );
		$this->fire_all( 31, 'post' );

		$this->assertSame(
			array( 'post_published', 'post_updated', 'post_trashed', 'post_published', 'post_updated' ),
			$this->webhook->sent
		);
	}

	public function test_a_broken_filter_falls_back_to_viewable_types(): void {
		add_filter( 'dragonwebhookmanager_post_types', static fn() => null );

		$this->fire_all( 40, 'post' );
		$this->fire_all( 41, 'wp_template' );

		$this->assertSame( array( 'post_published', 'post_updated', 'post_trashed' ), $this->webhook->sent );
	}
}
