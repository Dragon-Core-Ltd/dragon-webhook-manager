<?php
/**
 * Edit webhook view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables are scoped to the including method, not global; dwm_ is this plugin's established prefix and its hooks are consumed by the Pro add-on.

$dwm_is_edit = ! empty( $dwm_webhook );
$dwm_title   = $dwm_is_edit ? __( 'Edit Webhook', 'dragon-webhook-manager' ) : __( 'Add Webhook', 'dragon-webhook-manager' );

// Parse headers for display
$dwm_headers_display = '';
if ( $dwm_is_edit && ! empty( $dwm_webhook['headers'] ) ) {
	$dwm_headers = json_decode( $dwm_webhook['headers'], true );
	if ( is_array( $dwm_headers ) ) {
		foreach ( $dwm_headers as $dwm_key => $dwm_value ) {
			$dwm_headers_display .= $dwm_key . ': ' . $dwm_value . "\n";
		}
	}
}

// Default payload template
$dwm_default_payload = '{
  "event": "{{trigger_event}}",
  "site": "{{site_name}}",
  "timestamp": "{{timestamp_iso}}"
}';
?>
<div class="wrap dwm-wrap">
	<h1><?php echo esc_html( $dwm_title ); ?></h1>

	<form id="dwm-webhook-form" class="dwm-form">
		<?php if ( $dwm_is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $dwm_webhook['id'] ); ?>">
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
						value="<?php echo esc_attr( $dwm_webhook['name'] ?? '' ); ?>"
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
						rows="2"><?php echo esc_textarea( $dwm_webhook['description'] ?? '' ); ?></textarea>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-trigger"><?php esc_html_e( 'Trigger Event', 'dragon-webhook-manager' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<?php
					// Check if Pro triggers are enabled.
					$dwm_pro_enabled = apply_filters( 'dwm_pro_triggers_enabled', false );
					?>
					<select id="dwm-trigger" name="trigger_event" class="regular-text" required>
						<option value=""><?php esc_html_e( '-- Select Trigger --', 'dragon-webhook-manager' ); ?></option>
						<?php foreach ( $dwm_triggers as $dwm_category => $dwm_events ) : ?>
							<optgroup label="<?php echo esc_attr( $dwm_category ); ?>">
								<?php foreach ( $dwm_events as $dwm_key => $dwm_trigger_data ) : ?>
									<?php
									$dwm_is_pro   = ! empty( $dwm_trigger_data['pro'] );
									$dwm_disabled = $dwm_is_pro && ! $dwm_pro_enabled;
									$dwm_label    = $dwm_trigger_data['label'] . ( $dwm_disabled ? ' [PRO]' : '' );
									?>
									<option value="<?php echo esc_attr( $dwm_key ); ?>"
										<?php selected( $dwm_webhook['trigger_event'] ?? '', $dwm_key ); ?>
										<?php disabled( $dwm_disabled, true ); ?>>
										<?php echo esc_html( $dwm_label ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<?php if ( ! $dwm_pro_enabled ) : ?>
					<p class="description dwm-pro-teaser">
						<?php
						printf(
							/* translators: %s: upgrade link */
							esc_html__( 'Need WooCommerce triggers? %s', 'dragon-webhook-manager' ),
							'<a href="https://plugins.dragoncore.ltd/plugins/dragon-webhook-manager-pro/" target="_blank">' . esc_html__( 'Upgrade to Pro', 'dragon-webhook-manager' ) . '</a>'
						);
						?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

			<?php
			// Allow Pro to add conditions field after trigger.
			do_action( 'dwm_webhook_form_after_trigger', $dwm_webhook ?? array() );
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
						value="<?php echo esc_url( $dwm_webhook['url'] ?? '' ); ?>"
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
								<?php checked( ( $dwm_webhook['method'] ?? 'POST' ), 'POST' ); ?>>
							POST
						</label>
						<label>
							<input type="radio" name="method" value="PUT"
								<?php checked( ( $dwm_webhook['method'] ?? '' ), 'PUT' ); ?>>
							PUT
						</label>
						<label>
							<input type="radio" name="method" value="PATCH"
								<?php checked( ( $dwm_webhook['method'] ?? '' ), 'PATCH' ); ?>>
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
Authorization: Bearer your-token"><?php echo esc_textarea( $dwm_headers_display ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One header per line in "Key: Value" format.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>

			<?php
			// Allow Pro to add signature and retry fields after headers.
			do_action( 'dwm_webhook_form_after_headers', $dwm_webhook ?? array() );
			?>

			<tr>
				<th scope="row">
					<label for="dwm-payload"><?php esc_html_e( 'Payload Template', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<textarea id="dwm-payload"
						name="payload_template"
						class="large-text code"
						rows="10"><?php echo esc_textarea( $dwm_webhook['payload_template'] ?? $dwm_default_payload ); ?></textarea>

					<div class="dwm-variable-reference">
						<p><strong><?php esc_html_e( 'Available Variables:', 'dragon-webhook-manager' ); ?></strong></p>
						<div class="dwm-variable-groups">
							<?php foreach ( $dwm_variable_reference as $dwm_group => $dwm_vars ) : ?>
								<div class="dwm-variable-group">
									<h4><?php echo esc_html( $dwm_group ); ?></h4>
									<ul>
										<?php foreach ( $dwm_vars as $dwm_var => $dwm_desc ) : ?>
											<li>
												<code class="dwm-var-copy" title="<?php esc_attr_e( 'Click to copy', 'dragon-webhook-manager' ); ?>"><?php echo esc_html( $dwm_var ); ?></code>
												<span class="description"><?php echo esc_html( $dwm_desc ); ?></span>
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
							<?php checked( $dwm_webhook['is_active'] ?? 1, 1 ); ?>>
						<?php esc_html_e( 'Active (webhook will fire on trigger)', 'dragon-webhook-manager' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="dwm-save-webhook">
				<?php echo $dwm_is_edit ? esc_html__( 'Update Webhook', 'dragon-webhook-manager' ) : esc_html__( 'Create Webhook', 'dragon-webhook-manager' ); ?>
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
