<?php
/**
 * Dashboard view - Webhook list
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables are scoped to the including method, not global; dragonwebhookmanager_ is this plugin's established prefix and its hooks are consumed by the Pro add-on.
?>
<div class="wrap dwm-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Dragon Webhook Manager', 'dragon-webhook-manager' ); ?></h1>
	<?php if ( $dragonwebhookmanager_webhook_count < $dragonwebhookmanager_max_webhooks ) : ?>
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&view=edit' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add Webhook', 'dragon-webhook-manager' ); ?>
		</a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php
	// Render tabs navigation.
	$dragonwebhookmanager_tabs = apply_filters( 'dragonwebhookmanager_admin_tabs', array() );
	if ( ! empty( $dragonwebhookmanager_tabs ) ) :
		?>
	<nav class="nav-tab-wrapper dwm-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager' ) ); ?>" class="nav-tab nav-tab-active">
			<?php esc_html_e( 'Webhooks', 'dragon-webhook-manager' ); ?>
		</a>
		<?php foreach ( $dragonwebhookmanager_tabs as $dragonwebhookmanager_tab_id => $dragonwebhookmanager_tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $dragonwebhookmanager_tab_id, admin_url( 'tools.php?page=dragon-webhook-manager' ) ) ); ?>" class="nav-tab">
				<?php echo esc_html( $dragonwebhookmanager_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<!-- Stats Grid -->
	<div class="dwm-stats-grid">
		<div class="dwm-stat-card">
			<span class="dwm-stat-value"><?php echo esc_html( $dragonwebhookmanager_webhook_count ); ?></span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Webhooks', 'dragon-webhook-manager' ); ?></span>
			<span class="dwm-stat-limit">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Current number of webhooks, 2: Maximum number of webhooks allowed. */
						__( '%1$d of %2$d', 'dragon-webhook-manager' ),
						$dragonwebhookmanager_webhook_count,
						$dragonwebhookmanager_max_webhooks
					)
				);
				?>
			</span>
		</div>
		<div class="dwm-stat-card">
			<span class="dwm-stat-value"><?php echo esc_html( $dragonwebhookmanager_stats['today'] ); ?></span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Deliveries Today', 'dragon-webhook-manager' ); ?></span>
		</div>
		<div class="dwm-stat-card">
			<span class="dwm-stat-value"><?php echo esc_html( $dragonwebhookmanager_stats['success_rate'] ); ?>%</span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Success Rate', 'dragon-webhook-manager' ); ?></span>
		</div>
		<div class="dwm-stat-card">
			<span class="dwm-stat-value"><?php echo esc_html( $dragonwebhookmanager_stats['avg_duration'] ); ?>ms</span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Avg Response', 'dragon-webhook-manager' ); ?></span>
		</div>
	</div>

	<?php
	// Only show Pro upsell if Pro is not active and licensed.
	$dragonwebhookmanager_pro_enabled = apply_filters( 'dragonwebhookmanager_pro_triggers_enabled', false );
	if ( ! $dragonwebhookmanager_pro_enabled ) :
		?>
	<!-- Pro Upsell Notice -->
	<div class="dwm-pro-notice">
		<div class="dwm-pro-notice-content">
			<strong><?php esc_html_e( 'Need WooCommerce webhooks?', 'dragon-webhook-manager' ); ?></strong>
			<span><?php esc_html_e( 'Upgrade to Pro for 20+ WooCommerce triggers: orders, customers, products, inventory alerts & more.', 'dragon-webhook-manager' ); ?></span>
		</div>
		<a href="https://dragoncore.ltd/plugins/dragon-webhook-manager-pro" target="_blank" class="button button-primary">
			<?php esc_html_e( 'Get Pro', 'dragon-webhook-manager' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- Webhooks Table -->
	<table class="wp-list-table widefat fixed striped dwm-webhooks-table">
		<thead>
			<tr>
				<th class="column-status" style="width: 60px;"><?php esc_html_e( 'Status', 'dragon-webhook-manager' ); ?></th>
				<th class="column-name"><?php esc_html_e( 'Name', 'dragon-webhook-manager' ); ?></th>
				<th class="column-trigger"><?php esc_html_e( 'Trigger', 'dragon-webhook-manager' ); ?></th>
				<th class="column-url"><?php esc_html_e( 'URL', 'dragon-webhook-manager' ); ?></th>
				<th class="column-method" style="width: 80px;"><?php esc_html_e( 'Method', 'dragon-webhook-manager' ); ?></th>
				<th class="column-actions" style="width: 120px;"><?php esc_html_e( 'Actions', 'dragon-webhook-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $dragonwebhookmanager_webhooks ) ) : ?>
				<tr>
					<td colspan="6" class="dwm-empty-state">
						<p><?php esc_html_e( 'No webhooks created yet.', 'dragon-webhook-manager' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&view=edit' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create Your First Webhook', 'dragon-webhook-manager' ); ?>
						</a>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $dragonwebhookmanager_webhooks as $dragonwebhookmanager_webhook ) : ?>
					<tr data-webhook-id="<?php echo esc_attr( $dragonwebhookmanager_webhook['id'] ); ?>">
						<td class="column-status">
							<button type="button"
								class="dwm-toggle-status <?php echo $dragonwebhookmanager_webhook['is_active'] ? 'is-active' : ''; ?>"
								data-id="<?php echo esc_attr( $dragonwebhookmanager_webhook['id'] ); ?>"
								title="<?php echo $dragonwebhookmanager_webhook['is_active'] ? esc_attr__( 'Active - Click to disable', 'dragon-webhook-manager' ) : esc_attr__( 'Inactive - Click to enable', 'dragon-webhook-manager' ); ?>">
								<span class="dwm-status-indicator"></span>
							</button>
						</td>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&view=edit&id=' . $dragonwebhookmanager_webhook['id'] ) ); ?>">
									<?php echo esc_html( $dragonwebhookmanager_webhook['name'] ); ?>
								</a>
							</strong>
							<?php if ( $dragonwebhookmanager_webhook['description'] ) : ?>
								<p class="description"><?php echo esc_html( wp_trim_words( $dragonwebhookmanager_webhook['description'], 10 ) ); ?></p>
							<?php endif; ?>
						</td>
						<td class="column-trigger">
							<span class="dwm-trigger-badge">
								<?php
								$dragonwebhookmanager_triggers = \DragonWebhookManager\Triggers::TRIGGERS;
								echo esc_html( $dragonwebhookmanager_triggers[ $dragonwebhookmanager_webhook['trigger_event'] ]['label'] ?? $dragonwebhookmanager_webhook['trigger_event'] );
								?>
							</span>
						</td>
						<td class="column-url">
							<code class="dwm-url-display"><?php echo esc_html( wp_trim_words( $dragonwebhookmanager_webhook['url'], 5, '...' ) ); ?></code>
						</td>
						<td class="column-method">
							<span class="dwm-method-badge dwm-method-<?php echo esc_attr( strtolower( $dragonwebhookmanager_webhook['method'] ) ); ?>">
								<?php echo esc_html( $dragonwebhookmanager_webhook['method'] ); ?>
							</span>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&view=edit&id=' . $dragonwebhookmanager_webhook['id'] ) ); ?>"
								class="button button-small"
								title="<?php esc_attr_e( 'Edit', 'dragon-webhook-manager' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</a>
							<button type="button"
								class="button button-small dwm-delete-webhook"
								data-id="<?php echo esc_attr( $dragonwebhookmanager_webhook['id'] ); ?>"
								title="<?php esc_attr_e( 'Delete', 'dragon-webhook-manager' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<p class="dwm-footer-links">
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&view=logs' ) ); ?>">
			<?php esc_html_e( 'View Delivery Logs', 'dragon-webhook-manager' ); ?> &rarr;
		</a>
	</p>
</div>

<div id="dwm-toast" class="dwm-toast"></div>
