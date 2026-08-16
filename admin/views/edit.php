<?php
/**
 * Edit webhook view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables are scoped to the including method, not global; dragonwebhookmanager_ is this plugin's established prefix and its hooks are consumed by the Pro add-on.

$dragonwebhookmanager_is_edit = ! empty( $dragonwebhookmanager_webhook );
$dragonwebhookmanager_title   = $dragonwebhookmanager_is_edit ? __( 'Edit Webhook', 'dragon-webhook-manager' ) : __( 'Add Webhook', 'dragon-webhook-manager' );

// Parse headers for display
$dragonwebhookmanager_headers_display = '';
if ( $dragonwebhookmanager_is_edit && ! empty( $dragonwebhookmanager_webhook['headers'] ) ) {
	$dragonwebhookmanager_headers = json_decode( $dragonwebhookmanager_webhook['headers'], true );
	if ( is_array( $dragonwebhookmanager_headers ) ) {
		foreach ( $dragonwebhookmanager_headers as $dragonwebhookmanager_key => $dragonwebhookmanager_value ) {
			$dragonwebhookmanager_headers_display .= $dragonwebhookmanager_key . ': ' . $dragonwebhookmanager_value . "\n";
		}
	}
}

// Default payload template
$dragonwebhookmanager_default_payload = '{
  "event": "{{trigger_event}}",
  "site": "{{site_name}}",
  "timestamp": "{{timestamp_iso}}"
}';
?>
<div class="wrap dragon-ui dwm-wrap">
	<h1 class="dragon-title"><span class="dragon-mark" aria-hidden="true"></span><?php echo esc_html( $dragonwebhookmanager_title ); ?></h1>

	<form id="dwm-webhook-form" class="dwm-form">
		<?php if ( $dragonwebhookmanager_is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $dragonwebhookmanager_webhook['id'] ); ?>">
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="dwm-name"><?php esc_html_e( 'Name', 'dragon-webhook-manager' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="text"
						id="dwm-name"
						name="name"
						class="regular-text"
						value="<?php echo esc_attr( $dragonwebhookmanager_webhook['name'] ?? '' ); ?>"
						required>
					<p class="description"><?php esc_html_e( 'A descriptive name for this webhook.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-description"><?php esc_html_e( 'Description', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<textarea id="dwm-description"
						name="description"
						class="large-text"
						rows="2"><?php echo esc_textarea( $dragonwebhookmanager_webhook['description'] ?? '' ); ?></textarea>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-trigger"><?php esc_html_e( 'Trigger Event', 'dragon-webhook-manager' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<?php
					// Check if Pro triggers are enabled.
					$dragonwebhookmanager_pro_enabled = apply_filters( 'dragonwebhookmanager_pro_triggers_enabled', false );
					?>
					<select id="dwm-trigger" name="trigger_event" class="regular-text" required>
						<option value=""><?php esc_html_e( '-- Select Trigger --', 'dragon-webhook-manager' ); ?></option>
						<?php foreach ( $dragonwebhookmanager_triggers as $dragonwebhookmanager_category => $dragonwebhookmanager_events ) : ?>
							<optgroup label="<?php echo esc_attr( $dragonwebhookmanager_category ); ?>">
								<?php foreach ( $dragonwebhookmanager_events as $dragonwebhookmanager_key => $dragonwebhookmanager_trigger_data ) : ?>
									<?php
									$dragonwebhookmanager_is_pro   = ! empty( $dragonwebhookmanager_trigger_data['pro'] );
									$dragonwebhookmanager_disabled = $dragonwebhookmanager_is_pro && ! $dragonwebhookmanager_pro_enabled;
									$dragonwebhookmanager_label    = $dragonwebhookmanager_trigger_data['label'] . ( $dragonwebhookmanager_disabled ? ' [PRO]' : '' );
									?>
									<option value="<?php echo esc_attr( $dragonwebhookmanager_key ); ?>"
										<?php selected( $dragonwebhookmanager_webhook['trigger_event'] ?? '', $dragonwebhookmanager_key ); ?>
										<?php disabled( $dragonwebhookmanager_disabled, true ); ?>>
										<?php echo esc_html( $dragonwebhookmanager_label ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<?php if ( ! $dragonwebhookmanager_pro_enabled ) : ?>
					<p class="description dwm-pro-teaser">
						<?php
						printf(
							/* translators: %s: upgrade link */
							esc_html__( 'Need WooCommerce triggers? %s', 'dragon-webhook-manager' ),
							'<a href="https://dragoncore.ltd/plugins/dragon-webhook-manager-pro" target="_blank">' . esc_html__( 'Upgrade to Pro', 'dragon-webhook-manager' ) . '</a>'
						);
						?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

			<?php
			// Allow Pro to add conditions field after trigger.
			do_action( 'dragonwebhookmanager_webhook_form_after_trigger', $dragonwebhookmanager_webhook ?? array() );
			?>

			<tr>
				<th scope="row">
					<label for="dwm-url"><?php esc_html_e( 'Webhook URL', 'dragon-webhook-manager' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="url"
						id="dwm-url"
						name="url"
						class="large-text"
						value="<?php echo esc_url( $dragonwebhookmanager_webhook['url'] ?? '' ); ?>"
						placeholder="https://example.com/webhook"
						required>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'HTTP Method', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="method" value="POST"
								<?php checked( ( $dragonwebhookmanager_webhook['method'] ?? 'POST' ), 'POST' ); ?>>
							POST
						</label>
						<label>
							<input type="radio" name="method" value="PUT"
								<?php checked( ( $dragonwebhookmanager_webhook['method'] ?? '' ), 'PUT' ); ?>>
							PUT
						</label>
						<label>
							<input type="radio" name="method" value="PATCH"
								<?php checked( ( $dragonwebhookmanager_webhook['method'] ?? '' ), 'PATCH' ); ?>>
							PATCH
						</label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-headers"><?php esc_html_e( 'Headers', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<textarea id="dwm-headers"
						name="headers"
						class="large-text code"
						rows="3"
						placeholder="Content-Type: application/json
Authorization: Bearer your-token"><?php echo esc_textarea( $dragonwebhookmanager_headers_display ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One header per line in "Key: Value" format.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>

			<?php
			// Allow Pro to add signature and retry fields after headers.
			do_action( 'dragonwebhookmanager_webhook_form_after_headers', $dragonwebhookmanager_webhook ?? array() );
			?>

			<tr>
				<th scope="row">
					<label for="dwm-payload"><?php esc_html_e( 'Payload Template', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<textarea id="dwm-payload"
						name="payload_template"
						class="large-text code"
						rows="10"><?php echo esc_textarea( $dragonwebhookmanager_webhook['payload_template'] ?? $dragonwebhookmanager_default_payload ); ?></textarea>

					<div class="dwm-variable-reference">
						<p><strong><?php esc_html_e( 'Available Variables:', 'dragon-webhook-manager' ); ?></strong></p>
						<div class="dwm-variable-groups">
							<?php foreach ( $dragonwebhookmanager_variable_reference as $dragonwebhookmanager_group => $dragonwebhookmanager_vars ) : ?>
								<div class="dwm-variable-group">
									<h4><?php echo esc_html( $dragonwebhookmanager_group ); ?></h4>
									<ul>
										<?php foreach ( $dragonwebhookmanager_vars as $dragonwebhookmanager_var => $dragonwebhookmanager_desc ) : ?>
											<li>
												<code class="dwm-var-copy" title="<?php esc_attr_e( 'Click to copy', 'dragon-webhook-manager' ); ?>"><?php echo esc_html( $dragonwebhookmanager_var ); ?></code>
												<span class="description"><?php echo esc_html( $dragonwebhookmanager_desc ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-active"><?php esc_html_e( 'Status', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox"
							id="dwm-active"
							name="is_active"
							value="1"
							<?php checked( $dragonwebhookmanager_webhook['is_active'] ?? 1, 1 ); ?>>
						<?php esc_html_e( 'Active (webhook will fire on trigger)', 'dragon-webhook-manager' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="dwm-save-webhook">
				<?php echo $dragonwebhookmanager_is_edit ? esc_html__( 'Update Webhook', 'dragon-webhook-manager' ) : esc_html__( 'Create Webhook', 'dragon-webhook-manager' ); ?>
			</button>
			<button type="button" class="button" id="dwm-test-webhook">
				<?php esc_html_e( 'Test Webhook', 'dragon-webhook-manager' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager' ) ); ?>" class="button">
				<?php esc_html_e( 'Cancel', 'dragon-webhook-manager' ); ?>
			</a>
		</p>
	</form>

	<!-- Test Result Modal -->
	<div id="dwm-test-result" class="dwm-modal" style="display: none;">
		<div class="dwm-modal-content">
			<h3><?php esc_html_e( 'Test Result', 'dragon-webhook-manager' ); ?></h3>
			<div class="dwm-test-result-body"></div>
			<button type="button" class="button dwm-modal-close"><?php esc_html_e( 'Close', 'dragon-webhook-manager' ); ?></button>
		</div>
	</div>
</div>

<div id="dwm-toast" class="dwm-toast"></div>
