<?php
/**
 * Add-ons extend a delivery log row's details through a filter; the pairs
 * they return must reach the details modal payload.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Admin;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Admin::log_details() and Admin::with_details().
 */
final class LogDetailsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['dragonwebhookmanager_test_filters'] = array();
	}

	public function test_filter_added_detail_appears_in_modal_payload(): void {
		add_filter(
			'dragonwebhookmanager_log_details',
			static function ( array $details, array $log ): array {
				$details['retry_status'] = 'Retry scheduled in 1 min (#' . $log['id'] . ')';
				return $details;
			},
			10,
			2
		);

		$rows = Admin::with_details( array( array( 'id' => 11, 'status' => 'failed' ) ) );

		$this->assertSame(
			array(
				array(
					'label' => 'Retry status',
					'value' => 'Retry scheduled in 1 min (#11)',
				),
			),
			$rows[0]['details']
		);
		$this->assertSame( 11, $rows[0]['id'], 'core columns are untouched' );
	}

	public function test_label_filter_can_supply_a_translated_label(): void {
		add_filter(
			'dragonwebhookmanager_log_details',
			static function ( array $details ): array {
				$details['retry_attempt'] = 'Attempt 2 of 3';
				$details['other_key']     = 'x';
				return $details;
			}
		);
		add_filter(
			'dragonwebhookmanager_log_detail_label',
			static function ( $label, $key ) {
				return 'retry_attempt' === $key ? 'Versuch' : $label;
			}
		);

		$details = Admin::log_details( array( 'id' => 5 ) );

		$this->assertSame( 'Versuch', $details[0]['label'] );
		$this->assertSame( 'Other key', $details[1]['label'], 'unclaimed keys keep the derived label' );
	}

	public function test_rows_carry_a_status_label_and_unknown_codes_fall_back(): void {
		$rows = Admin::with_details(
			array(
				array( 'id' => 1, 'status' => 'failed', 'duration_ms' => 1250 ),
				array( 'id' => 2, 'status' => 'queued' ),
			)
		);

		$this->assertSame( '1,250 ms', $rows[0]['duration_label'] );
		$this->assertSame( '0 ms', $rows[1]['duration_label'] );

		$this->assertSame( 'Failed', $rows[0]['status_label'] );
		$this->assertSame( 'failed', $rows[0]['status'], 'the stored code is untouched' );
		$this->assertSame( 'queued', $rows[1]['status_label'] );
	}

	public function test_no_short_prefixed_filter_is_offered(): void {
		// WordPress.org requires a prefix of 4 characters or more, and a human
		// reviewer pended another plugin in this fleet over a 3-letter one. This
		// name never shipped, so nothing can be listening for it.
		add_filter(
			'dwm_log_details',
			static function ( array $details ): array {
				$details['retry_attempt'] = 'Attempt 2 of 3';
				return $details;
			}
		);

		$this->assertSame( array(), Admin::log_details( array( 'id' => 3 ) ) );
	}

	public function test_non_scalar_values_and_bad_shapes_are_tolerated(): void {
		add_filter(
			'dragonwebhookmanager_log_details',
			static function () {
				return array(
					'meta'  => array( 'a' => 1 ),
					7       => 'unlabelled',
					'empty' => '',
				);
			}
		);

		$details = Admin::log_details( array( 'id' => 3 ) );

		$this->assertSame( '{"a":1}', $details[0]['value'] );
		$this->assertSame( 'Meta', $details[0]['label'] );
		$this->assertCount( 1, $details, 'entries without a string key or a value are dropped' );
	}

	public function test_no_filters_gives_empty_details(): void {
		$this->assertSame( array(), Admin::with_details( array( array( 'id' => 1 ) ) )[0]['details'] );
	}
}
