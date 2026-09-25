<?php
/**
 * The default Content-Type is added only when the webhook sets none, in any
 * letter case.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Webhook::with_default_content_type().
 */
final class ContentTypeHeaderTest extends TestCase {

	public function test_a_user_content_type_in_any_case_is_kept_alone(): void {
		foreach ( array( 'content-type', 'CONTENT-TYPE', 'Content-type' ) as $name ) {
			$this->assertSame(
				array(
					'X-A' => '1',
					$name => 'text/plain',
				),
				Webhook::with_default_content_type(
					array(
						'X-A' => '1',
						$name => 'text/plain',
					)
				),
				$name
			);
		}
	}

	public function test_json_is_the_default(): void {
		$this->assertSame(
			array(
				'X-A'          => '1',
				'Content-Type' => 'application/json',
			),
			Webhook::with_default_content_type( array( 'X-A' => '1' ) )
		);
		$this->assertSame( array( 'Content-Type' => 'application/json' ), Webhook::with_default_content_type( array() ) );
	}
}
