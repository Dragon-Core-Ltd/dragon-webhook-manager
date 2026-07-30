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
	 * Available triggers with their WordPress hooks
	 */
	public const TRIGGERS = array(
		// Free triggers - WordPress Core
		'post_published'          => array(
			'label'    => 'Post Published',
			'category' => 'Content',
			'hook'     => 'transition_post_status',
		),
		'post_updated'            => array(
			'label'    => 'Post Updated',
			'category' => 'Content',
			'hook'     => 'post_updated',
		),
		'post_trashed'            => array(
			'label'    => 'Post Trashed',
			'category' => 'Content',
			'hook'     => 'wp_trash_post',
		),
		'user_registered'         => array(
			'label'    => 'User Registered',
			'category' => 'User',
			'hook'     => 'user_register',
		),
		'user_login'              => array(
			'label'    => 'User Login',
			'category' => 'User',
			'hook'     => 'wp_login',
		),
		'comment_submitted'       => array(
			'label'    => 'Comment Submitted',
			'category' => 'Comment',
			'hook'     => 'wp_insert_comment',
		),
		'comment_approved'        => array(
			'label'    => 'Comment Approved',
			'category' => 'Comment',
			'hook'     => 'transition_comment_status',
		),
		// Pro triggers - WooCommerce Orders
		'wc_order_created'        => array(
			'label'    => 'Order Created',
			'category' => 'WooCommerce Orders',
			'hook'     => 'woocommerce_new_order',
			'pro'      => true,
		),
		'wc_order_paid'           => array(
			'label'    => 'Order Paid',
			'category' => 'WooCommerce Orders',
			'hook'     => 'woocommerce_payment_complete',
			'pro'      => true,
		),
		'wc_order_completed'      => array(
			'label'    => 'Order Completed',
			'category' => 'WooCommerce Orders',
			'hook'     => 'woocommerce_order_status_completed',
			'pro'      => true,
		),
		'wc_order_cancelled'      => array(
			'label'    => 'Order Cancelled',
			'category' => 'WooCommerce Orders',
			'hook'     => 'woocommerce_order_status_cancelled',
			'pro'      => true,
		),
		'wc_order_refunded'       => array(
			'label'    => 'Order Refunded',
			'category' => 'WooCommerce Orders',
			'hook'     => 'woocommerce_order_refunded',
			'pro'      => true,
		),
		// Pro triggers - WooCommerce Customers
		'wc_customer_created'     => array(
			'label'    => 'Customer Created',
			'category' => 'WooCommerce Customers',
			'hook'     => 'woocommerce_created_customer',
			'pro'      => true,
		),
		// Pro triggers - WooCommerce Products
		'wc_product_low_stock'    => array(
			'label'    => 'Product Low Stock',
			'category' => 'WooCommerce Products',
			'hook'     => 'woocommerce_low_stock',
			'pro'      => true,
		),
		'wc_product_out_of_stock' => array(
			'label'    => 'Product Out of Stock',
			'category' => 'WooCommerce Products',
			'hook'     => 'woocommerce_no_stock',
			'pro'      => true,
		),
	);

	public function __construct( Webhook $webhook, Payload $payload, Logger $logger ) {
		$this->webhook = $webhook;
		$this->payload = $payload;
		$this->logger  = $logger;

		$this->register_hooks();

		// Allow Pro to dispatch triggers.
		add_action( 'dwm_trigger_fired', array( $this, 'dispatch' ), 10, 2 );
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
		// Allow Pro to check conditions.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- dwm_ is this plugin's prefix; hook consumed by Dragon Webhook Manager Pro.
		$should_deliver = apply_filters( 'dwm_should_deliver', true, $webhook, $context );
		if ( ! $should_deliver ) {
			return;
		}

		// Parse payload template
		$payload = $this->payload->parse( $webhook['payload_template'] ?? '{}', $context );

		// Allow Pro to add signature headers.
		$webhook_headers = json_decode( $webhook['headers'] ?? '{}', true );
		if ( ! is_array( $webhook_headers ) ) {
			$webhook_headers = array();
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- dwm_ is this plugin's prefix; hook consumed by Dragon Webhook Manager Pro.
		$webhook_headers    = apply_filters( 'dwm_webhook_headers', $webhook_headers, $webhook, $payload );
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

		// Allow Pro to handle retries on failure.
		if ( ! $result['success'] ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- dwm_ is this plugin's prefix; hook consumed by Dragon Webhook Manager Pro.
			do_action( 'dwm_delivery_failed', $log_id, $webhook, $context );
		}
	}

	/**
	 * Get all available triggers
	 */
	public static function get_triggers(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- dwm_ is this plugin's prefix; hook consumed by Dragon Webhook Manager Pro.
		return apply_filters( 'dwm_triggers', self::TRIGGERS );
	}

	/**
	 * Get triggers grouped by category
	 */
	public static function get_triggers_grouped(): array {
		$grouped = array();

		foreach ( self::TRIGGERS as $key => $trigger ) {
			$category = $trigger['category'];
			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][ $key ] = array(
				'label' => $trigger['label'],
				'pro'   => ! empty( $trigger['pro'] ),
			);
		}

		return $grouped;
	}
}
