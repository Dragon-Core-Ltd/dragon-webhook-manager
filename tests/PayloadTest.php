<?php
/**
 * Payload rendering: built-in variables are byte-stable, add-on variables are
 * filled through dragonwebhookmanager_parse_variable, and the variable
 * reference lists add-on variables.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Payload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-payload.php';

/**
 * Tests for Payload::parse(), Payload::get_variable_reference() and Payload::sample_context().
 */
final class PayloadTest extends TestCase {

	private const TEMPLATE = '{"title":"{{post_title}}","id":{{post_id}},"author":"{{post_author_name}}","email":"{{post_author_email}}","status":"{{post_status}}","url":"{{post_url}}","excerpt":"{{post_excerpt}}","unknown":"{{not_a_var}}","post":"{{post}}","event":"{{trigger_event}}","site":"{{site_name}}","home":"{{site_url}}","admin":"{{admin_email}}"}';

	private const EXPECTED_POST = '{"title":"He said \"hi\" \\\\ back\/slash caf\u00e9\nline 2","id":42,"author":"Ann \"A\" O\'Neil","email":"ann@example.test","status":"publish","url":"https:\/\/example.test\/?p=42","excerpt":"Short <b>excerpt<\/b>","unknown":"{{not_a_var}}","post":"{{post}}","event":"{{trigger_event}}","site":"Site \"Q\" & Co","home":"https:\/\/example.test","admin":"admin@example.test"}';

	private const USER_TEMPLATE = '{"id":{{user_id}},"login":"{{user_login}}","name":"{{user_display_name}}","roles":"{{user_role}}","first":"{{user_first_name}}","user":"{{user}}","order_total":"{{order_total}}"}';

	private const EXPECTED_USER = '{"id":7,"login":"ann","name":"Ann \"A\" O\'Neil","roles":"editor, author","first":"Ann","user":"{{user}}","order_total":"{{order_total}}"}';

	protected function setUp(): void {
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
		$GLOBALS['dragonwebhookmanager_test_options'] = array(
			'blogname'    => 'Site "Q" & Co',
			'admin_email' => 'admin@example.test',
		);

		$GLOBALS['dragonwebhookmanager_test_users'] = array( 7 => $this->user() );
	}

	private function user(): \WP_User {
		$user               = new \WP_User();
		$user->ID           = 7;
		$user->user_login   = 'ann';
		$user->user_email   = 'ann@example.test';
		$user->display_name = 'Ann "A" O\'Neil';
		$user->first_name   = 'Ann';
		$user->user_pass    = '$P$secrethash';
		$user->roles        = array( 'editor', 'author' );
		return $user;
	}

	private function post(): \WP_Post {
		return new \WP_Post(
			(object) array(
				'ID'           => 42,
				'post_author'  => '7',
				'post_title'   => "He said \"hi\" \\ back/slash caf\u{e9}\nline 2",
				'post_excerpt' => 'Short <b>excerpt</b>',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
	}

	/**
	 * Stands in for the shipped Pro 1.0.12 callback: any context value is
	 * returned, arrays and non-WooCommerce objects JSON-encoded.
	 */
	private function add_shipped_pro_callback(): void {
		add_filter(
			'dragonwebhookmanager_parse_variable',
			static function ( $value, string $key, array $context ) {
				if ( null !== $value || ! isset( $context[ $key ] ) ) {
					return $value;
				}
				$val = $context[ $key ];
				return ( is_array( $val ) || is_object( $val ) ) ? wp_json_encode( $val ) : $val;
			}
		);
	}

	public function test_post_payload_bytes_are_pinned(): void {
		$payload = ( new Payload() )->parse( self::TEMPLATE, array( 'post' => $this->post() ) );

		$this->assertSame( self::EXPECTED_POST, $payload );
		$this->assertNotNull( json_decode( $payload ) );
	}

	public function test_post_payload_bytes_unchanged_with_an_add_on_filter(): void {
		$this->add_shipped_pro_callback();

		$payload = ( new Payload() )->parse( self::TEMPLATE, array( 'post' => $this->post() ) );

		$this->assertSame( self::EXPECTED_POST, $payload );
	}

	public function test_user_payload_bytes_are_pinned_and_never_expose_the_user_object(): void {
		$context = array( 'user' => $this->user() );

		$this->assertSame( self::EXPECTED_USER, ( new Payload() )->parse( self::USER_TEMPLATE, $context ) );

		$this->add_shipped_pro_callback();
		$payload = ( new Payload() )->parse( self::USER_TEMPLATE, $context );

		$this->assertSame( self::EXPECTED_USER, $payload );
		$this->assertStringNotContainsString( 'secrethash', $payload );
	}

	public function test_add_on_variables_are_filled_and_escaped(): void {
		$this->add_shipped_pro_callback();

		$context = array(
			'order'              => new \stdClass(),
			'order_total'        => '120.50',
			'order_id'           => 99,
			'billing_first_name' => 'Zoë "Z" \\ </script>',
			'line_items'         => array(
				array(
					'product_name' => 'Mug "big"',
					'quantity'     => 2,
				),
			),
		);

		$payload = ( new Payload() )->parse(
			'{"total":"{{order_total}}","id":{{order_id}},"name":"{{billing_first_name}}","items":"{{line_items}}","order":"{{order}}","missing":"{{order_tax}}","site":"{{site_name}}"}',
			$context
		);

		$this->assertSame(
			'{"total":"120.50","id":99,"name":"Zo\u00eb \"Z\" \\\\ <\/script>","items":"[{\"product_name\":\"Mug \\\\\"big\\\\\"\",\"quantity\":2}]","order":"{{order}}","missing":"{{order_tax}}","site":"Site \"Q\" & Co"}',
			$payload
		);

		$decoded = json_decode( $payload, true );
		$this->assertSame( 'Zoë "Z" \\ </script>', $decoded['name'] );
		$this->assertSame( 'Mug "big"', json_decode( $decoded['items'], true )[0]['product_name'] );
	}

	public function test_filter_is_not_consulted_for_built_in_variables(): void {
		$seen = array();
		add_filter(
			'dragonwebhookmanager_parse_variable',
			static function ( $value, string $key ) use ( &$seen ) {
				$seen[] = $key;
				return 'OVERRIDE';
			}
		);

		$payload = ( new Payload() )->parse( '{"s":"{{site_name}}","x":"{{extra}}"}', array() );

		$this->assertSame( array( 'extra' ), $seen );
		$this->assertSame( '{"s":"Site \"Q\" & Co","x":"OVERRIDE"}', $payload );
	}

	public function test_non_scalar_filter_results_leave_the_placeholder(): void {
		add_filter(
			'dragonwebhookmanager_parse_variable',
			static function ( $value, string $key ) {
				return 'arr' === $key ? array( 1 ) : new \stdClass();
			}
		);

		$payload = ( new Payload() )->parse( '["{{arr}}","{{obj}}"]', array() );

		$this->assertSame( '["{{arr}}","{{obj}}"]', $payload );
	}

	public function test_variable_reference_lists_add_on_variables_with_braces(): void {
		add_filter(
			'dragonwebhookmanager_template_variables',
			static function ( array $variables ): array {
				$variables['WooCommerce Orders'] = array(
					'order_total'   => 'Order total',
					'{{order_id}}'  => 'Order ID',
					'bad key!'      => 'Dropped',
					'order_number'  => array( 'not a label' ),
				);
				$variables['Broken'] = 'not a group';
				return $variables;
			}
		);

		$reference = Payload::get_variable_reference();

		$this->assertSame( array( 'Global', 'Post', 'User', 'Comment', 'WooCommerce Orders' ), array_keys( $reference ) );
		$this->assertSame(
			array(
				'{{order_total}}' => 'Order total',
				'{{order_id}}'    => 'Order ID',
			),
			$reference['WooCommerce Orders']
		);
		$this->assertSame( 'Site URL', $reference['Global']['{{site_url}}'] );
	}

	public function test_variable_reference_survives_a_filter_returning_garbage(): void {
		add_filter(
			'dragonwebhookmanager_template_variables',
			static function () {
				return null;
			}
		);

		$this->assertSame( array( 'Global', 'Post', 'User', 'Comment' ), array_keys( Payload::get_variable_reference() ) );
	}

	#[DataProvider( 'sample_triggers' )]
	public function test_sample_context_fills_every_built_in_variable_group( string $trigger, string $template, string $expected ): void {
		$payload = ( new Payload() )->parse( $template, Payload::sample_context( $trigger ) );

		$this->assertSame( $expected, $payload );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function sample_triggers(): array {
		return array(
			'post'    => array( 'post_updated', '{"t":"{{post_title}}","id":{{post_id}},"c":"{{comment_id}}"}', '{"t":"Sample Post Title","id":123,"c":"{{comment_id}}"}' ),
			'user'    => array( 'user_login', '{"e":"{{user_email}}","r":"{{user_role}}","f":"{{user_first_name}}"}', '{"e":"test@example.com","r":"subscriber","f":"Test"}' ),
			'comment' => array( 'comment_approved', '{"a":"{{comment_author}}","id":"{{comment_id}}","s":"{{comment_status}}"}', '{"a":"Commenter Name","id":"456","s":"1"}' ),
			'other'   => array( 'wc_order_paid', '{"t":"{{post_title}}"}', '{"t":"{{post_title}}"}' ),
		);
	}
}

