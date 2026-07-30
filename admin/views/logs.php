<?php
/**
 * Logs view - Delivery history
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables are scoped to the including method, not global; dwm_ is this plugin's established prefix and its hooks are consumed by the Pro add-on.
?>
<div class="wrap dwm-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Delivery Logs', 'dragon-webhook-manager' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to Webhooks', 'dragon-webhook-manager' ); ?>
	</a>
	<hr class="wp-header-end">

	<!-- Stats Grid -->
	<div class="dwm-stats-grid">
		<div class="dwm-stat-card">
			<span class="dwm-stat-value"><?php echo esc_html( $dwm_stats['total'] ); ?></span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Total Deliveries', 'dragon-webhook-manager' ); ?></span>
		</div>
		<div class="dwm-stat-card dwm-stat-success">
			<span class="dwm-stat-value"><?php echo esc_html( $dwm_stats['success'] ); ?></span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Successful', 'dragon-webhook-manager' ); ?></span>
		</div>
		<div class="dwm-stat-card dwm-stat-error">
			<span class="dwm-stat-value"><?php echo esc_html( $dwm_stats['failed'] ); ?></span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Failed', 'dragon-webhook-manager' ); ?></span>
		</div>
		<div class="dwm-stat-card">
			<span class="dwm-stat-value"><?php echo esc_html( $dwm_stats['avg_duration'] ); ?>ms</span>
			<span class="dwm-stat-label"><?php esc_html_e( 'Avg Duration', 'dragon-webhook-manager' ); ?></span>
		</div>
	</div>

	<!-- Filter -->
	<div class="dwm-filter-bar">
		<form method="get">
			<input type="hidden" name="page" value="dragon-webhook-manager">
			<input type="hidden" name="view" value="logs">
			<label for="dwm-filter-webhook"><?php esc_html_e( 'Filter by webhook:', 'dragon-webhook-manager' ); ?></label>
			<select name="webhook_id" id="dwm-filter-webhook">
				<option value=""><?php esc_html_e( 'All Webhooks', 'dragon-webhook-manager' ); ?></option>
				<?php foreach ( $dwm_webhooks as $dwm_wh ) : ?>
					<option value="<?php echo esc_attr( $dwm_wh['id'] ); ?>"
						<?php selected( $dwm_webhook_id, $dwm_wh['id'] ); ?>>
						<?php echo esc_html( $dwm_wh['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'dragon-webhook-manager' ); ?></button>
		</form>
		<button type="button" id="dwm-clear-logs" class="button button-link-delete">
			<?php esc_html_e( 'Clear All Logs', 'dragon-webhook-manager' ); ?>
		</button>
	</div>

	<!-- Logs Table -->
	<table class="wp-list-table widefat fixed striped dwm-logs-table">
		<thead>
			<tr>
				<th class="column-webhook"><?php esc_html_e( 'Webhook', 'dragon-webhook-manager' ); ?></th>
				<th class="column-trigger"><?php esc_html_e( 'Trigger', 'dragon-webhook-manager' ); ?></th>
				<th class="column-status" style="width: 100px;"><?php esc_html_e( 'Status', 'dragon-webhook-manager' ); ?></th>
				<th class="column-response" style="width: 100px;"><?php esc_html_e( 'Response', 'dragon-webhook-manager' ); ?></th>
				<th class="column-duration" style="width: 100px;"><?php esc_html_e( 'Duration', 'dragon-webhook-manager' ); ?></th>
				<th class="column-time"><?php esc_html_e( 'Time', 'dragon-webhook-manager' ); ?></th>
				<th class="column-actions" style="width: 80px;"><?php esc_html_e( 'Actions', 'dragon-webhook-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $dwm_logs ) ) : ?>
				<tr>
					<td colspan="7" class="dwm-empty-state">
						<p><?php esc_html_e( 'No delivery logs yet.', 'dragon-webhook-manager' ); ?></p>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $dwm_logs as $dwm_log ) : ?>
					<tr data-log-id="<?php echo esc_attr( $dwm_log['id'] ); ?>">
						<td class="column-webhook">
							<?php if ( $dwm_log['webhook_name'] ) : ?>
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&view=edit&id=' . $dwm_log['webhook_id'] ) ); ?>">
									<?php echo esc_html( $dwm_log['webhook_name'] ); ?>
								</a>
							<?php else : ?>
								<em><?php esc_html_e( '(deleted)', 'dragon-webhook-manager' ); ?></em>
							<?php endif; ?>
						</td>
						<td class="column-trigger">
							<?php
							$dwm_triggers = \DragonWebhookManager\Triggers::TRIGGERS;
							echo esc_html( $dwm_triggers[ $dwm_log['trigger_event'] ]['label'] ?? $dwm_log['trigger_event'] );
							?>
						</td>
						<td class="column-status">
							<span class="dwm-status-badge dwm-status-<?php echo esc_attr( $dwm_log['status'] ); ?>">
								<?php echo esc_html( ucfirst( $dwm_log['status'] ) ); ?>
							</span>
						</td>
						<td class="column-response">
							<?php if ( $dwm_log['response_code'] ) : ?>
								<code class="dwm-response-code dwm-response-<?php echo esc_attr( $dwm_log['response_code'] >= 400 ? 'error' : 'success' ); ?>">
									<?php echo esc_html( $dwm_log['response_code'] ); ?>
								</code>
							<?php else : ?>
								<span class="description">-</span>
							<?php endif; ?>
						</td>
						<td class="column-duration">
							<?php echo esc_html( $dwm_log['duration_ms'] ); ?>ms
						</td>
						<td class="column-time">
							<?php
							$dwm_time = strtotime( $dwm_log['created_at'] );
							echo esc_html( human_time_diff( $dwm_time, time() ) . ' ' . __( 'ago', 'dragon-webhook-manager' ) );
							?>
							<br>
							<span class="description"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $dwm_time ) ); ?></span>
						</td>
						<td class="column-actions">
							<?php if ( 'failed' === $dwm_log['status'] ) : ?>
								<button type="button"
									class="button button-small dwm-retry-delivery"
									data-log-id="<?php echo esc_attr( $dwm_log['id'] ); ?>"
									title="<?php esc_attr_e( 'Retry', 'dragon-webhook-manager' ); ?>">
									<span class="dashicons dashicons-update"></span>
								</button>
							<?php endif; ?>
							<button type="button"
								class="button button-small dwm-view-log-details"
								data-log='<?php echo esc_attr( wp_json_encode( $dwm_log ) ); ?>'
								title="<?php esc_attr_e( 'View Details', 'dragon-webhook-manager' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Log Details Modal -->
<div id="dwm-log-details-modal" class="dwm-modal" style="display: none;">
	<div class="dwm-modal-content dwm-modal-large">
		<h3><?php esc_html_e( 'Delivery Details', 'dragon-webhook-manager' ); ?></h3>
		<div class="dwm-log-details-body">
			<div class="dwm-log-section">
				<h4><?php esc_html_e( 'Request', 'dragon-webhook-manager' ); ?></h4>
				<p><strong><?php esc_html_e( 'URL:', 'dragon-webhook-manager' ); ?></strong> <code id="dwm-log-url"></code></p>
				<p><strong><?php esc_html_e( 'Method:', 'dragon-webhook-manager' ); ?></strong> <span id="dwm-log-method"></span></p>
				<p><strong><?php esc_html_e( 'Headers:', 'dragon-webhook-manager' ); ?></strong></p>
				<pre id="dwm-log-req-headers"></pre>
				<p><strong><?php esc_html_e( 'Body:', 'dragon-webhook-manager' ); ?></strong></p>
				<pre id="dwm-log-req-body"></pre>
			</div>
			<div class="dwm-log-section">
				<h4><?php esc_html_e( 'Response', 'dragon-webhook-manager' ); ?></h4>
				<p><strong><?php esc_html_e( 'Status:', 'dragon-webhook-manager' ); ?></strong> <span id="dwm-log-status"></span> <code id="dwm-log-response-code"></code></p>
				<p><strong><?php esc_html_e( 'Duration:', 'dragon-webhook-manager' ); ?></strong> <span id="dwm-log-duration"></span></p>
				<p id="dwm-log-error-row" style="display: none;"><strong><?php esc_html_e( 'Error:', 'dragon-webhook-manager' ); ?></strong> <span id="dwm-log-error" class="dwm-error-text"></span></p>
				<p><strong><?php esc_html_e( 'Body:', 'dragon-webhook-manager' ); ?></strong></p>
				<pre id="dwm-log-res-body"></pre>
			</div>
		</div>
		<button type="button" class="button dwm-modal-close"><?php esc_html_e( 'Close', 'dragon-webhook-manager' ); ?></button>
	</div>
</div>

<div id="dwm-toast" class="dwm-toast"></div>
