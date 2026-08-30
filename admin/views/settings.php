<?php
/**
 * Settings view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables are scoped to the including method, not global; dragonwebhookmanager_ is this plugin's established prefix.
?>
<div class="wrap dragon-ui dwm-wrap">
	<h1 class="dragon-title wp-heading-inline"><span class="dragon-mark" aria-hidden="true"></span><?php esc_html_e( 'Settings', 'dragon-webhook-manager' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to Webhooks', 'dragon-webhook-manager' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php settings_errors(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
		<?php settings_fields( $dragonwebhookmanager_settings_group ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="dwm-log-retention-days"><?php esc_html_e( 'Log retention', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<input type="number"
						id="dwm-log-retention-days"
						name="dragonwebhookmanager_log_retention_days"
						class="small-text"
						min="1"
						max="365"
						step="1"
						value="<?php echo esc_attr( $dragonwebhookmanager_retention_days ); ?>">
					<?php esc_html_e( 'days', 'dragon-webhook-manager' ); ?>
					<p class="description"><?php esc_html_e( 'Delivery logs older than this are removed by the daily cleanup. 1 to 365 days.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-default-timeout"><?php esc_html_e( 'Delivery timeout', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<input type="number"
						id="dwm-default-timeout"
						name="dragonwebhookmanager_default_timeout"
						class="small-text"
						min="5"
						max="120"
						step="1"
						value="<?php echo esc_attr( $dragonwebhookmanager_timeout ); ?>">
					<?php esc_html_e( 'seconds', 'dragon-webhook-manager' ); ?>
					<p class="description"><?php esc_html_e( 'How long to wait for the endpoint to respond before a delivery is marked failed. 5 to 120 seconds.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'dragon-webhook-manager' ); ?></th>
				<td>
					<label for="dwm-delete-data-on-uninstall">
						<input type="checkbox"
							id="dwm-delete-data-on-uninstall"
							name="dragonwebhookmanager_delete_data_on_uninstall"
							value="1"
							<?php checked( $dragonwebhookmanager_delete_on_unins ); ?>>
						<?php esc_html_e( 'Delete all webhooks, logs, and settings when the plugin is uninstalled', 'dragon-webhook-manager' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Leave unchecked to keep your data so a reinstall picks up where you left off.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
