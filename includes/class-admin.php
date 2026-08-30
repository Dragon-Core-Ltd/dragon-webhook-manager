<?php
/**
 * Admin pages and menu
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * Settings group used by the options form.
	 */
	private const SETTINGS_GROUP = 'dragonwebhookmanager_settings';

	private Webhook $webhook;
	private Logger $logger;

	public function __construct( Webhook $webhook, Logger $logger ) {
		$this->webhook = $webhook;
		$this->logger  = $logger;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_admin_menu(): void {
		add_management_page(
			__( 'Dragon Webhook Manager', 'dragon-webhook-manager' ),
			__( 'Webhook Manager', 'dragon-webhook-manager' ),
			'manage_options',
			'dragon-webhook-manager',
			array( $this, 'render_dashboard_page' )
		);
	}

	/**
	 * Register the options edited on the settings view.
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			'dragonwebhookmanager_log_retention_days',
			array(
				'type'              => 'integer',
				'default'           => 7,
				'sanitize_callback' => array( Plugin::class, 'sanitize_retention_days' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'dragonwebhookmanager_default_timeout',
			array(
				'type'              => 'integer',
				'default'           => 30,
				'sanitize_callback' => array( Plugin::class, 'sanitize_timeout' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'dragonwebhookmanager_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => static function ( $value ): int {
					return empty( $value ) ? 0 : 1;
				},
			)
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'tools_page_dragon-webhook-manager' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'dragon-webhook-manager-dragon-ui',
			DRAGONWEBHOOKMANAGER_PLUGIN_URL . 'admin/css/dragon-ui.css',
			array(),
			DRAGONWEBHOOKMANAGER_VERSION
		);

		wp_enqueue_style(
			'dwm-admin',
			DRAGONWEBHOOKMANAGER_PLUGIN_URL . 'admin/css/admin.css',
			array( 'dragon-webhook-manager-dragon-ui' ),
			DRAGONWEBHOOKMANAGER_VERSION
		);

		wp_enqueue_script(
			'dwm-admin',
			DRAGONWEBHOOKMANAGER_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			DRAGONWEBHOOKMANAGER_VERSION,
			true
		);

		wp_localize_script(
			'dwm-admin',
			'dwmAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dragonwebhookmanager_ajax_nonce' ),
				'i18n'    => array(
					'confirmDelete'    => __( 'Are you sure you want to delete this webhook?', 'dragon-webhook-manager' ),
					'confirmClearLogs' => __( 'Are you sure you want to clear all logs?', 'dragon-webhook-manager' ),
					'webhookSaved'     => __( 'Webhook saved successfully.', 'dragon-webhook-manager' ),
					'webhookDeleted'   => __( 'Webhook deleted.', 'dragon-webhook-manager' ),
					'webhookToggled'   => __( 'Webhook status updated.', 'dragon-webhook-manager' ),
					'testSent'         => __( 'Test webhook sent.', 'dragon-webhook-manager' ),
					'logsCleared'      => __( 'Logs cleared.', 'dragon-webhook-manager' ),
					'error'            => __( 'An error occurred.', 'dragon-webhook-manager' ),
				),
			)
		);
	}

	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dragon-webhook-manager' ) );
		}

		// Tabs are registered by other plugins through `dragonwebhookmanager_admin_tabs`.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view routing; no state change.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( $tab && has_action( 'dragonwebhookmanager_admin_tab_' . $tab ) ) {
			$this->render_tabs( $tab );

			do_action( 'dragonwebhookmanager_admin_tab_' . $tab );
			echo '</div>'; // Close the wrap div opened by render_tabs().
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view routing; no state change.
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list';

		switch ( $view ) {
			case 'edit':
				$this->render_edit_page();
				break;
			case 'logs':
				$this->render_logs_page();
				break;
			case 'settings':
				$this->render_settings_page();
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

		$tabs = apply_filters( 'dragonwebhookmanager_admin_tabs', array() );
		if ( empty( $tabs ) ) {
			return;
		}

		echo '<div class="wrap dragon-ui"><h1 class="dragon-title"><span class="dragon-mark" aria-hidden="true"></span>' . esc_html__( 'Dragon Webhook Manager', 'dragon-webhook-manager' ) . '</h1>';
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
		$dragonwebhookmanager_webhooks      = $this->webhook->get_all();
		$dragonwebhookmanager_webhook_count = $this->webhook->count();
		$dragonwebhookmanager_triggers      = Triggers::get_triggers();
		$dragonwebhookmanager_stats         = $this->logger->get_stats();

		include DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	private function render_edit_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view routing; no state change.
		$dragonwebhookmanager_id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$dragonwebhookmanager_webhook = $dragonwebhookmanager_id ? $this->webhook->get( $dragonwebhookmanager_id ) : null;

		$dragonwebhookmanager_triggers           = Triggers::get_triggers_grouped();
		$dragonwebhookmanager_variable_reference = Payload::get_variable_reference();

		include DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'admin/views/edit.php';
	}

	private function render_logs_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter; no state change.
		$dragonwebhookmanager_webhook_id = isset( $_GET['webhook_id'] ) ? absint( $_GET['webhook_id'] ) : null;
		$dragonwebhookmanager_logs       = $this->logger->get_logs( 100, 0, $dragonwebhookmanager_webhook_id );
		$dragonwebhookmanager_stats      = $this->logger->get_stats();
		$dragonwebhookmanager_webhooks   = $this->webhook->get_all();
		$dragonwebhookmanager_triggers   = Triggers::get_triggers();

		include DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'admin/views/logs.php';
	}

	private function render_settings_page(): void {
		$dragonwebhookmanager_settings_group  = self::SETTINGS_GROUP;
		$dragonwebhookmanager_retention_days  = Plugin::sanitize_retention_days( get_option( 'dragonwebhookmanager_log_retention_days', 7 ) );
		$dragonwebhookmanager_timeout         = Plugin::sanitize_timeout( get_option( 'dragonwebhookmanager_default_timeout', 30 ) );
		$dragonwebhookmanager_delete_on_unins = (bool) get_option( 'dragonwebhookmanager_delete_data_on_uninstall', false );

		include DRAGONWEBHOOKMANAGER_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
