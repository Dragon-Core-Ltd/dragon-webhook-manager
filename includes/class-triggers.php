<?php
/**
 * WordPress hook triggers
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Triggers {

	private Webhook $webhook;
	private Payload $payload;
	private Logger $logger;

	/**
	 * Built-in triggers: event key => category code and WordPress hook.
	 *
	 * Holds codes only; labels are translated at read time. Additional
	 * triggers can be registered through the `dragonwebhookmanager_triggers`
	 * filter; read the full list with get_triggers() rather than this constant.
	 */
	public const TRIGGERS = array(
		'post_published'    => array(
			'category' => 'content',
			'hook'     => 'transition_post_status',
		),
		'post_updated'      => array(
			'category' => 'content',
			'hook'     => 'post_updated',
		),
		'post_trashed'      => array(
			'category' => 'content',
			'hook'     => 'wp_trash_post',
		),
		'user_registered'   => array(
			'category' => 'user',
			'hook'     => 'user_register',
		),
		'user_login'        => array(
			'category' => 'user',
			'hook'     => 'wp_login',
		),
		'comment_submitted' => array(
			'category' => 'comment',
			'hook'     => 'wp_insert_comment',
		),
		'comment_approved'  => array(
			'category' => 'comment',
			'hook'     => 'transition_comment_status',
		),
	);

	/**
	 * Built-in trigger definitions with translated labels and categories.
	 *
	 * Built on each call so translations are only requested after the text
	 * domain has loaded.
	 *
	 * @return array<string, array{label: string, category: string, hook: string}>
	 */
	private static function builtin_triggers(): array {
		$labels = array(
			'post_published'    => __( 'Post Published', 'dragon-webhook-manager' ),
			'post_updated'      => __( 'Post Updated', 'dragon-webhook-manager' ),
			'post_trashed'      => __( 'Post Trashed', 'dragon-webhook-manager' ),
			'user_registered'   => __( 'User Registered', 'dragon-webhook-manager' ),
			'user_login'        => __( 'User Login', 'dragon-webhook-manager' ),
			'comment_submitted' => __( 'Comment Submitted', 'dragon-webhook-manager' ),
			'comment_approved'  => __( 'Comment Approved', 'dragon-webhook-manager' ),
		);

		$categories = array(
			'content' => __( 'Content', 'dragon-webhook-manager' ),
			'user'    => __( 'User', 'dragon-webhook-manager' ),
			'comment' => __( 'Comment', 'dragon-webhook-manager' ),
		);

		$triggers = array();
		foreach ( self::TRIGGERS as $key => $trigger ) {
			$triggers[ $key ] = array(
				'label'    => $labels[ $key ] ?? $key,
				'category' => $categories[ $trigger['category'] ] ?? $trigger['category'],
				'hook'     => $trigger['hook'],
			);
		}

		return $triggers;
	}

	public function __construct( Webhook $webhook, Payload $payload, Logger $logger ) {
		$this->webhook = $webhook;
		$this->payload = $payload;
		$this->logger  = $logger;

		$this->register_hooks();

		// Triggers registered by other plugins dispatch through this action.
		add_action( 'dragonwebhookmanager_trigger_fired', array( $this, 'dispatch' ), 10, 2 );
	}

	private function register_hooks(): void {
		// Post transitions (publish)
		add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 10, 3 );

		// Post updated
		add_action( 'post_updated', array( $this, 'handle_post_updated' ), 10, 3 );

		// Post trashed
		add_action( 'wp_trash_post', array( $this, 'handle_post_trashed' ), 10, 1 );

		// User registered
		add_action( 'user_register', array( $this, 'handle_user_registered' ) );

		// User login
		add_action( 'wp_login', array( $this, 'handle_user_login' ), 10, 2 );

		// Comment inserted
		add_action( 'wp_insert_comment', array( $this, 'handle_comment_submitted' ), 10, 2 );

		// Comment status transition
		add_action( 'transition_comment_status', array( $this, 'handle_comment_transition' ), 10, 3 );
	}

	/**
	 * Handle post status transitions (for post_published)
	 */
	public function handle_post_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		// Only trigger on publish
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Skip revisions and auto-drafts
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$this->dispatch( 'post_published', array( 'post' => $post ) );
	}

	/**
	 * Handle post updated
	 */
	public function handle_post_updated( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		// Skip revisions and auto-drafts
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Only for published posts
		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		$this->dispatch(
			'post_updated',
			array(
				'post'        => $post_after,
				'post_before' => $post_before,
			)
		);
	}

	/**
	 * Handle post trashed
	 */
	public function handle_post_trashed( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$this->dispatch( 'post_trashed', array( 'post' => $post ) );
	}

	/**
	 * Handle user registration
	 */
	public function handle_user_registered( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->dispatch( 'user_registered', array( 'user' => $user ) );
	}

	/**
	 * Handle user login
	 */
	public function handle_user_login( string $user_login, \WP_User $user ): void {
		$this->dispatch( 'user_login', array( 'user' => $user ) );
	}

	/**
	 * Handle comment submitted
	 */
	public function handle_comment_submitted( int $comment_id, \WP_Comment $comment ): void {
		$this->dispatch( 'comment_submitted', array( 'comment' => $comment ) );
	}

	/**
	 * Handle comment status transition (for comment_approved)
	 */
	public function handle_comment_transition( string $new_status, string $old_status, \WP_Comment $comment ): void {
		// Only trigger when approved
		if ( 'approved' !== $new_status || 'approved' === $old_status ) {
			return;
		}

		$this->dispatch( 'comment_approved', array( 'comment' => $comment ) );
	}

	/**
	 * Dispatch webhooks for a trigger
	 */
	public function dispatch( string $trigger_event, array $context ): void {
		$webhooks = $this->webhook->get_by_trigger( $trigger_event );

		if ( empty( $webhooks ) ) {
			return;
		}

		foreach ( $webhooks as $webhook ) {
			$this->execute_webhook( $webhook, $context );
		}
	}

	/**
	 * Execute a single webhook
	 */
	private function execute_webhook( array $webhook, array $context ): void {
		// Filterable: return false to skip this delivery.
		$should_deliver = apply_filters( 'dragonwebhookmanager_should_deliver', true, $webhook, $context );
		if ( ! $should_deliver ) {
			return;
		}

		// Parse payload template
		$payload = $this->payload->parse( $webhook['payload_template'] ?? '{}', $context );

		// Filterable request headers (for example to add a signature).
		$webhook_headers = json_decode( $webhook['headers'] ?? '{}', true );
		if ( ! is_array( $webhook_headers ) ) {
			$webhook_headers = array();
		}

		$webhook_headers    = apply_filters( 'dragonwebhookmanager_webhook_headers', $webhook_headers, $webhook, $payload );
		$webhook['headers'] = wp_json_encode( $webhook_headers );

		// Start log
		$log_id = $this->logger->log_start( $webhook, $payload );

		// Deliver
		$result = $this->webhook->deliver( $webhook, $payload );

		// Complete log
		$this->logger->log_complete(
			$log_id,
			$result['success'] ? 'success' : 'failed',
			$result['response_code'],
			$result['response_body'],
			$result['duration_ms'],
			$result['error_message']
		);

		// Fires on a failed delivery so listeners can schedule a re-delivery.
		if ( ! $result['success'] ) {
			do_action( 'dragonwebhookmanager_delivery_failed', $log_id, $webhook, $context );
		} else {
			/**
			 * Fires when a webhook was delivered and the endpoint accepted it.
			 *
			 * @param int   $log_id  Log row id.
			 * @param array $webhook Webhook definition.
			 * @param array $context Trigger context.
			 */
			do_action( 'dragonwebhookmanager_delivery_succeeded', $log_id, $webhook, $context );
		}
	}

	/**
	 * Get all available triggers, including any registered by other plugins.
	 *
	 * @return array<string, array{label: string, category: string, hook?: string}>
	 */
	public static function get_triggers(): array {
		/**
		 * Filters the trigger definitions.
		 *
		 * @param array $triggers Trigger definitions keyed by trigger event.
		 */
		$builtin  = self::builtin_triggers();
		$triggers = apply_filters( 'dragonwebhookmanager_triggers', $builtin );
		if ( ! is_array( $triggers ) ) {
			return $builtin;
		}

		$clean = array();
		foreach ( $triggers as $key => $trigger ) {
			if ( ! is_string( $key ) || '' === $key || ! is_array( $trigger ) ) {
				continue;
			}
			$trigger['label']    = isset( $trigger['label'] ) && is_scalar( $trigger['label'] ) ? (string) $trigger['label'] : $key;
			$trigger['category'] = isset( $trigger['category'] ) && is_scalar( $trigger['category'] ) ? (string) $trigger['category'] : __( 'Other', 'dragon-webhook-manager' );
			$clean[ $key ]       = $trigger;
		}

		return empty( $clean ) ? $builtin : $clean;
	}

	/**
	 * Get the display label for a trigger event.
	 *
	 * Falls back to the raw event key when the trigger is not registered.
	 *
	 * @param string $trigger_event Trigger event key.
	 * @return string
	 */
	public static function get_label( string $trigger_event ): string {
		$triggers = self::get_triggers();

		return (string) ( $triggers[ $trigger_event ]['label'] ?? $trigger_event );
	}

	/**
	 * Get triggers grouped by category
	 */
	public static function get_triggers_grouped(): array {
		$grouped = array();

		foreach ( self::get_triggers() as $key => $trigger ) {
			$category = (string) ( $trigger['category'] ?? __( 'Other', 'dragon-webhook-manager' ) );
			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][ $key ] = array(
				'label' => (string) ( $trigger['label'] ?? $key ),
			);
		}

		return $grouped;
	}
}
