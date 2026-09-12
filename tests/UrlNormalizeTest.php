<?php
/**
 * Webhook URL normalisation: internationalised hosts are stored as punycode,
 * everything else is untouched.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Webhook::normalize_url().
 */
final class UrlNormalizeTest extends TestCase {

	public function test_ascii_urls_are_returned_byte_for_byte(): void {
		$urls = array(
			'https://api.example.com/hook',
			'https://api.example.com:8443/hook?a=1&b=2#frag',
			'http://user:pa%20ss@api.example.com/h%C3%BCk',
			'https://xn--bcher-kva.example/hook',
			'https://[2001:db8::1]:8080/hook',
			'https://203.0.113.9/hook',
		);
		foreach ( $urls as $url ) {
			$this->assertSame( $url, Webhook::normalize_url( $url ), $url );
		}
	}

	public function test_unicode_host_is_converted_to_punycode(): void {
		if ( ! function_exists( 'idn_to_ascii' ) ) {
			// normalize_url() deliberately refuses a non-ASCII host without intl,
			// so this case cannot be asserted on a box that lacks the extension.
			$this->markTestSkipped( 'The intl extension is not loaded.' );
		}

		$this->assertSame( 'https://xn--bcher-kva.example/hook', Webhook::normalize_url( 'https://bücher.example/hook' ) );
		$this->assertSame(
			'https://user:pw@xn--mnchen-3ya.example.com:8443/p/a?x=1#f',
			Webhook::normalize_url( 'https://user:pw@münchen.example.com:8443/p/a?x=1#f' )
		);
		$this->assertSame( 'https://xn--e1afmkfd.xn--p1ai/', Webhook::normalize_url( 'https://пример.рф/' ) );
	}

	public function test_invalid_urls_are_rejected(): void {
		$invalid = array(
			'',
			'not a url',
			'example.com/hook',
			'https:///hook',
			'https://exa mple.com/hook',
			'https://bücher.example/hü',
		);
		foreach ( $invalid as $url ) {
			$this->assertNull( Webhook::normalize_url( $url ), $url );
		}
	}
}
