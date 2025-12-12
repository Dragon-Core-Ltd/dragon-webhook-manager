<?php
/**
 * Edit webhook view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $webhook );
$title   = $is_edit ? __( 'Edit Webhook', 'dragon-webhook-manager' ) : __( 'Add Webhook', 'dragon-webhook-manager' );

// Parse headers for display
$headers_display = '';
if ( $is_edit && ! empty( $webhook['headers'] ) ) {
	$headers = json_decode( $webhook['headers'], true );
	if ( is_array( $headers ) ) {
		foreach ( $headers as $key => $value ) {
			$headers_display .= $key . ': ' . $value . "\n";
		}
	}
}

// Default payload template
$default_payload = '{
  "event": "{{trigger_event}}",
  "site": "{{site_name}}",
  "timestamp": "{{timestamp_iso}}"
}';
?>
<div class="wrap dwm-wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<form id="dwm-webhook-form" class="dwm-form">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $webhook['id'] ); ?>">
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
						value="<?php echo esc_attr( $webhook['name'] ?? '' ); ?>"
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
						rows="2"><?php echo esc_textarea( $webhook['description'] ?? '' ); ?></textarea>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dwm-trigger"><?php esc_html_e( 'Trigger Event', 'dragon-webhook-manager' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<?php
					// Check if Pro triggers are enabled.
					$pro_enabled = apply_filters( 'dwm_pro_triggers_enabled', false );
					?>
					<select id="dwm-trigger" name="trigger_event" class="regular-text" required>
						<option value=""><?php esc_html_e( '-- Select Trigger --', 'dragon-webhook-manager' ); ?></option>
						<?php foreach ( $triggers as $category => $events ) : ?>
							<optgroup label="<?php echo esc_attr( $category ); ?>">
								<?php foreach ( $events as $key => $trigger_data ) : ?>
									<?php
									$is_pro   = ! empty( $trigger_data['pro'] );
									$disabled = $is_pro && ! $pro_enabled;
									$label    = $trigger_data['label'] . ( $disabled ? ' [PRO]' : '' );
									?>
									<option value="<?php echo esc_attr( $key ); ?>"
										<?php selected( $webhook['trigger_event'] ?? '', $key ); ?>
										<?php disabled( $disabled, true ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<?php if ( ! $pro_enabled ) : ?>
					<p class="description dwm-pro-teaser">
						<?php
						printf(
							/* translators: %s: upgrade link */
							esc_html__( 'Need WooCommerce triggers? %s', 'dragon-webhook-manager' ),
							'<a href="https://dragoncore.ltd/plugins/dragon-webhook-manager-pro/" target="_blank">' . esc_html__( 'Upgrade to Pro', 'dragon-webhook-manager' ) . '</a>'
						);
						?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

			<?php
			// Allow Pro to add conditions field after trigger.
			do_action( 'dwm_webhook_form_after_trigger', $webhook ?? [] );
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
						value="<?php echo esc_url( $webhook['url'] ?? '' ); ?>"
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
								<?php checked( ( $webhook['method'] ?? 'POST' ), 'POST' ); ?>>
							POST
						</label>
						<label>
							<input type="radio" name="method" value="PUT"
								<?php checked( ( $webhook['method'] ?? '' ), 'PUT' ); ?>>
							PUT
						</label>
						<label>
							<input type="radio" name="method" value="PATCH"
								<?php checked( ( $webhook['method'] ?? '' ), 'PATCH' ); ?>>
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
Authorization: Bearer your-token"><?php echo esc_textarea( $headers_display ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One header per line in "Key: Value" format.', 'dragon-webhook-manager' ); ?></p>
				</td>
			</tr>

			<?php
			// Allow Pro to add signature and retry fields after headers.
			do_action( 'dwm_webhook_form_after_headers', $webhook ?? [] );
			?>

			<tr>
				<th scope="row">
					<label for="dwm-payload"><?php esc_html_e( 'Payload Template', 'dragon-webhook-manager' ); ?></label>
				</th>
				<td>
					<textarea id="dwm-payload"
						name="payload_template"
						class="large-text code"
						rows="10"><?php echo esc_textarea( $webhook['payload_template'] ?? $default_payload ); ?></textarea>

					<div class="dwm-variable-reference">
						<p><strong><?php esc_html_e( 'Available Variables:', 'dragon-webhook-manager' ); ?></strong></p>
						<div class="dwm-variable-groups">
							<?php foreach ( $variable_reference as $group => $vars ) : ?>
								<div class="dwm-variable-group">
									<h4><?php echo esc_html( $group ); ?></h4>
									<ul>
										<?php foreach ( $vars as $var => $desc ) : ?>
											<li>
												<code class="dwm-var-copy" title="<?php esc_attr_e( 'Click to copy', 'dragon-webhook-manager' ); ?>"><?php echo esc_html( $var ); ?></code>
												<span class="description"><?php echo esc_html( $desc ); ?></span>
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
							<?php checked( $webhook['is_active'] ?? 1, 1 ); ?>>
						<?php esc_html_e( 'Active (webhook will fire on trigger)', 'dragon-webhook-manager' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="dwm-save-webhook">
				<?php echo $is_edit ? esc_html__( 'Update Webhook', 'dragon-webhook-manager' ) : esc_html__( 'Create Webhook', 'dragon-webhook-manager' ); ?>
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
