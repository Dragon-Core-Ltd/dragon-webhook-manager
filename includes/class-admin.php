<?php
/**
 * Admin pages and menu
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private Webhook $webhook;
	private Logger $logger;

	public function __construct( Webhook $webhook, Logger $logger ) {
		$this->webhook = $webhook;
		$this->logger  = $logger;

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_admin_menu(): void {
		add_management_page(
			__( 'Dragon Webhook Manager', 'dragon-webhook-manager' ),
			__( 'Webhook Manager', 'dragon-webhook-manager' ),
			'manage_options',
			'dragon-webhook-manager',
			[ $this, 'render_dashboard_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'tools_page_dragon-webhook-manager' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'dwm-admin',
			DWM_PLUGIN_URL . 'admin/css/admin.css',
			[],
			DWM_VERSION
		);

		wp_enqueue_script(
			'dwm-admin',
			DWM_PLUGIN_URL . 'admin/js/admin.js',
			[ 'jquery' ],
			DWM_VERSION,
			true
		);

		wp_localize_script(
			'dwm-admin',
			'dwmAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dwm_ajax_nonce' ),
				'i18n'    => [
					'confirmDelete'      => __( 'Are you sure you want to delete this webhook?', 'dragon-webhook-manager' ),
					'confirmClearLogs'   => __( 'Are you sure you want to clear all logs?', 'dragon-webhook-manager' ),
					'webhookSaved'       => __( 'Webhook saved successfully.', 'dragon-webhook-manager' ),
					'webhookDeleted'     => __( 'Webhook deleted.', 'dragon-webhook-manager' ),
					'webhookToggled'     => __( 'Webhook status updated.', 'dragon-webhook-manager' ),
					'testSent'           => __( 'Test webhook sent.', 'dragon-webhook-manager' ),
					'logsCleared'        => __( 'Logs cleared.', 'dragon-webhook-manager' ),
					'error'              => __( 'An error occurred.', 'dragon-webhook-manager' ),
					'limitReached'       => __( 'Webhook limit reached. Upgrade to Pro for unlimited webhooks.', 'dragon-webhook-manager' ),
				],
			]
		);
	}

	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dragon-webhook-manager' ) );
		}

		// Check for tab parameter (for Pro license tab).
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( $tab && has_action( 'dwm_admin_tab_' . $tab ) ) {
			$this->render_tabs( $tab );
			do_action( 'dwm_admin_tab_' . $tab );
			return;
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list';

		switch ( $view ) {
			case 'edit':
				$this->render_edit_page();
				break;
			case 'logs':
				$this->render_logs_page();
				break;
			default:
				$this->render_list_page();
		}
	}

	/**
	 * Render tab navigation.
	 *
	 * @param string $current_tab Current active tab.
	 */
	private function render_tabs( string $current_tab ): void {
		$tabs = apply_filters( 'dwm_admin_tabs', [] );
		if ( empty( $tabs ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Dragon Webhook Manager', 'dragon-webhook-manager' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		echo '<a href="' . esc_url( admin_url( 'tools.php?page=dragon-webhook-manager' ) ) . '" class="nav-tab">' . esc_html__( 'Webhooks', 'dragon-webhook-manager' ) . '</a>';

		foreach ( $tabs as $tab_id => $tab_label ) {
			$url   = add_query_arg( 'tab', $tab_id, admin_url( 'tools.php?page=dragon-webhook-manager' ) );
			$class = $current_tab === $tab_id ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab_label ) . '</a>';
		}

		echo '</nav>';
	}

	private function render_list_page(): void {
		$webhooks      = $this->webhook->get_all();
		$webhook_count = $this->webhook->count();
		$max_webhooks  = apply_filters( 'dwm_max_webhooks', DWM_MAX_WEBHOOKS_FREE );
		$stats         = $this->logger->get_stats();

		include DWM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	private function render_edit_page(): void {
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$webhook = $id ? $this->webhook->get( $id ) : null;

		// Check limit for new webhooks (Pro can override via filter).
		$max_webhooks = apply_filters( 'dwm_max_webhooks', DWM_MAX_WEBHOOKS_FREE );
		if ( ! $id && $this->webhook->count() >= $max_webhooks ) {
			wp_die( esc_html__( 'Webhook limit reached. Upgrade to Pro for unlimited webhooks.', 'dragon-webhook-manager' ) );
		}

		$triggers          = Triggers::get_triggers_grouped();
		$variable_reference = Payload::get_variable_reference();

		include DWM_PLUGIN_DIR . 'admin/views/edit.php';
	}

	private function render_logs_page(): void {
		$webhook_id = isset( $_GET['webhook_id'] ) ? absint( $_GET['webhook_id'] ) : null;
		$logs       = $this->logger->get_logs( 100, 0, $webhook_id );
		$stats      = $this->logger->get_stats();
		$webhooks   = $this->webhook->get_all();

		include DWM_PLUGIN_DIR . 'admin/views/logs.php';
	}
}
