<?php
/**
 * Test doubles shared across test files.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

use DragonWebhookManager\Webhook;

/**
 * Webhook double that records deliveries instead of sending them.
 */
class Recording_Webhook extends Webhook {

	/**
	 * Every deliver() call as [webhook, payload].
	 *
	 * @var array<int, array>
	 */
	public array $sent = array();

	public function deliver( array $webhook, string $payload ): array {
		$this->sent[] = array( $webhook, $payload );
		return array(
			'success'       => true,
			'response_code' => 200,
			'response_body' => 'ok',
			'duration_ms'   => 5,
			'error_message' => '',
		);
	}
}
