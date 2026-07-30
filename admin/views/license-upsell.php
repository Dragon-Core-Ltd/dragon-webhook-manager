<?php
/**
 * License upsell view for free version.
 *
 * @package DragonWebhookManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables are scoped to the including method, not global; dwm_ is this plugin's established prefix and its hooks are consumed by the Pro add-on.

$dwm_pro_installed = defined( 'DWMP_VERSION' );
?>
<div class="dwm-license-upsell">
	<?php if ( $dwm_pro_installed ) : ?>
		<!-- Pro is installed but not licensed -->
		<div class="dwm-license-upsell__box dwm-license-upsell__box--warning">
			<h2><?php esc_html_e( 'Activate Your License', 'dragon-webhook-manager' ); ?></h2>
			<p><?php esc_html_e( 'Dragon Webhook Manager Pro is installed but not activated. Enter your license key to unlock all Pro features.', 'dragon-webhook-manager' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=dragon-webhook-manager&tab=license' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Enter License Key', 'dragon-webhook-manager' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<!-- Pro not installed - show upsell -->
		<div class="dwm-license-upsell__box">
			<div class="dwm-license-upsell__header">
				<span class="dwm-license-upsell__badge"><?php esc_html_e( 'Pro', 'dragon-webhook-manager' ); ?></span>
				<h2><?php esc_html_e( 'Upgrade to Pro', 'dragon-webhook-manager' ); ?></h2>
			</div>
			<p><?php esc_html_e( 'Unlock the full power of Dragon Webhook Manager with Pro features:', 'dragon-webhook-manager' ); ?></p>
			<ul class="dwm-license-upsell__features">
				<li>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Unlimited webhooks (free limited to 10)', 'dragon-webhook-manager' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( '20+ WooCommerce triggers', 'dragon-webhook-manager' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Conditional logic (IF/THEN rules)', 'dragon-webhook-manager' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'HMAC webhook signing', 'dragon-webhook-manager' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Auto-retry with exponential backoff', 'dragon-webhook-manager' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Priority support', 'dragon-webhook-manager' ); ?>
				</li>
			</ul>
			<p>
				<a href="https://plugins.dragoncore.ltd/plugins/dragon-webhook-manager-pro/" class="button button-primary button-hero" target="_blank">
					<?php esc_html_e( 'Get Pro License', 'dragon-webhook-manager' ); ?>
				</a>
			</p>
			<p class="dwm-license-upsell__price">
				<?php esc_html_e( 'Starting at $49/year', 'dragon-webhook-manager' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>

<style>
.dwm-license-upsell {
	max-width: 600px;
	margin: 20px 0;
}
.dwm-license-upsell__box {
	background: #f0f6fc;
	border-left: 4px solid #667eea;
	padding: 25px 30px;
	border-radius: 4px;
}
.dwm-license-upsell__box--warning {
	background: #fff8e5;
	border-left-color: #f0b849;
}
.dwm-license-upsell__header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 15px;
}
.dwm-license-upsell__badge {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	padding: 3px 10px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
}
.dwm-license-upsell__header h2 {
	margin: 0;
	padding: 0;
}
.dwm-license-upsell__features {
	list-style: none;
	margin: 20px 0;
	padding: 0;
}
.dwm-license-upsell__features li {
	margin: 10px 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.dwm-license-upsell__features .dashicons {
	color: #46b450;
}
.dwm-license-upsell__price {
	color: #666;
	font-size: 13px;
	margin-top: 10px;
}
</style>
