<?php
/**
 * SSRF IP-range guard tests.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Webhook::is_blocked_ip(), the SSRF address filter used to stop
 * webhook delivery to internal targets (loopback, link-local/metadata,
 * private, and reserved ranges).
 */
final class SsrfTest extends TestCase {

	public function test_dangerous_addresses_are_blocked(): void {
		$blocked = array(
			'127.0.0.1',        // loopback.
			'127.0.0.53',       // loopback range.
			'169.254.169.254',  // cloud metadata (link-local).
			'169.254.0.1',      // link-local.
			'10.0.0.5',         // private.
			'192.168.1.10',     // private.
			'172.16.5.5',       // private.
			'0.0.0.0',          // reserved.
			'::1',              // IPv6 loopback.
			'fe80::1',          // IPv6 link-local.
			'fd00::1',          // IPv6 unique-local.
			'not-an-ip',        // invalid: fail closed.
			'',                 // invalid: fail closed.
		);

		foreach ( $blocked as $ip ) {
			$this->assertTrue( Webhook::is_blocked_ip( $ip ), $ip );
		}
	}

	public function test_public_addresses_are_allowed(): void {
		$allowed = array(
			'8.8.8.8',          // Google DNS.
			'1.1.1.1',          // Cloudflare DNS.
			'93.184.216.34',    // example.com.
			'2606:4700:4700::1111', // Cloudflare IPv6.
		);

		foreach ( $allowed as $ip ) {
			$this->assertFalse( Webhook::is_blocked_ip( $ip ), $ip );
		}
	}

	public function test_resolve_target_blocks_internal_and_malformed_urls(): void {
		$blocked = array(
			'https://127.0.0.1/hook',
			'http://[::1]:8080/hook',
			'https://169.254.169.254/latest/meta-data',
			'https://10.0.0.5/hook',
			'https://localhost/hook',
			'https://sub.localhost/hook',
			'not-a-url',
			'',
			'https:///no-host',
		);

		foreach ( $blocked as $url ) {
			$this->assertTrue( Webhook::resolve_target( $url )['blocked'], $url );
		}
	}

	public function test_resolve_target_allows_public_ip_literals_without_pinning(): void {
		// A literal IP has no DNS to rebind, so it is validated but not pinned.
		$https = Webhook::resolve_target( 'https://93.184.216.34/hook' );
		$this->assertFalse( $https['blocked'] );
		$this->assertNull( $https['pin'] );

		$v6 = Webhook::resolve_target( 'https://[2606:4700:4700::1111]/hook' );
		$this->assertFalse( $v6['blocked'] );
		$this->assertNull( $v6['pin'] );
	}
}
