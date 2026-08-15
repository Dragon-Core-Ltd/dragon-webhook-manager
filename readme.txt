=== Dragon Webhook Manager ===
Contributors: dragoncore
Tags: webhooks, automation, woocommerce, notifications, api
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress and WooCommerce store to any service with webhooks. The simplest way to automate your site.

== Description ==

Dragon Webhook Manager lets you send HTTP requests to external services whenever specific events occur on your WordPress site. Perfect for:

* **Slack/Discord notifications** - Get notified when posts are published or orders come in
* **Zapier/Make integrations** - Trigger complex workflows automatically
* **CRM updates** - Sync user registrations and customer data
* **Order processing** - Notify fulfillment systems when orders are placed (Pro)
* **Inventory alerts** - Get notified when stock runs low (Pro)

= Features =

* **Easy Setup** - No coding required. Point-and-click interface.
* **7 Trigger Events** - Posts, users, and comments
* **Template Variables** - Dynamic payloads with `{{post_title}}`, `{{user_email}}`, etc.
* **Delivery Logs** - Track every webhook with request/response details
* **Test Button** - Verify webhooks before going live
* **Retry Failed** - One-click retry for failed deliveries

= Supported Triggers =

**Content**
* Post Published
* Post Updated
* Post Trashed

**Users**
* User Registered
* User Login

**Comments**
* Comment Submitted
* Comment Approved

= Template Variables =

Use variables in your payload to include dynamic data:

`
{
  "text": "New post: {{post_title}}",
  "url": "{{post_url}}",
  "author": "{{post_author_name}}"
}
`

**Global:** `{{site_url}}`, `{{site_name}}`, `{{timestamp_iso}}`
**Posts:** `{{post_id}}`, `{{post_title}}`, `{{post_url}}`, `{{post_author_name}}`
**Users:** `{{user_id}}`, `{{user_email}}`, `{{user_display_name}}`
**Comments:** `{{comment_id}}`, `{{comment_author}}`, `{{comment_content}}`

= Free Version Limits =

The free version supports up to 10 webhooks with all WordPress core triggers. Need WooCommerce integration? Check out Dragon Webhook Manager Pro.

= Pro Features =

**[Dragon Webhook Manager Pro](https://dragoncore.ltd/plugins/dragon-webhook-manager-pro)** adds:

* **Unlimited webhooks**
* **20+ WooCommerce triggers:**
  * Orders: created, paid, completed, cancelled, refunded
  * Customers: registered, updated, deleted
  * Products: low stock, out of stock, back in stock
  * Subscriptions: renewed, cancelled, expired
* **Conditional logic** - Only send when order_total > $100
* **HMAC signing** - Secure your webhooks with signatures
* **Auto-retry** - Automatic retry with exponential backoff

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/dragon-webhook-manager/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Tools → Webhook Manager to create your first webhook

== Frequently Asked Questions ==

= What services can I send webhooks to? =

Any service that accepts HTTP POST/PUT/PATCH requests. Popular examples include Slack, Discord, Zapier, Make (Integromat), custom APIs, and more.

= How do I test a webhook? =

When creating or editing a webhook, click the "Test Webhook" button. This sends a sample payload with test data to verify your configuration.

= Can I see what was sent? =

Yes! The Delivery Logs page shows every webhook delivery with full request/response details. You can also retry failed deliveries with one click.

= What happens if a webhook fails? =

Failed deliveries are logged with the error message. You can retry them manually from the logs page.

= Are there limits on the free version? =

The free version supports up to 10 webhooks with all WordPress core triggers. Upgrade to Pro for unlimited webhooks, WooCommerce triggers, and additional features.

= Can I use this with WooCommerce? =

The free version works alongside WooCommerce but doesn't have WooCommerce-specific triggers. For order notifications, customer events, and inventory alerts, check out [Dragon Webhook Manager Pro](https://dragoncore.ltd/plugins/dragon-webhook-manager-pro).

== Screenshots ==

1. Dashboard - View and manage all webhooks
2. Create Webhook - Configure trigger, URL, and payload
3. Delivery Logs - Monitor all webhook deliveries
4. Variable Reference - Available template variables

== Changelog ==

= 1.0.5 =
* Fix: settings could be lost on a deactivate then reactivate update; the migration now carries each value before removing the old copy.

= 1.0.4 =
* Renamed all option, hook, function and constant prefixes to the unique `dragonwebhookmanager_` / `DRAGONWEBHOOKMANAGER_` prefix. Existing settings are migrated automatically on update; configured webhooks and delivery logs are unaffected.

= 1.0.3 =
* Add the integration API that Dragon Webhook Manager Pro uses, so Pro's automatic retry and HMAC request signing work correctly.

= 1.0.2 =
* Security: pin each delivery to the IP address that passed the safety check, so a DNS change between the check and the request cannot redirect it to an internal host (DNS-rebinding).
* Security: webhook deliveries no longer follow redirects — a redirect to an internal address would otherwise bypass the safety check. A redirecting endpoint is now reported clearly in the delivery log; point the webhook at the final URL instead.
* Security: an unresolvable webhook host is now blocked rather than allowed through.

= 1.0.1 =
* Security: strengthen the SSRF guard to resolve and check every IPv4 and IPv6 address a webhook host points to (previously only the first IPv4 record), closing an IPv6/multi-record bypass. Adds the `dwm_is_internal_url` filter for operators who deliberately need an internal endpoint.

= 1.0.0 =
* Initial release
* 7 trigger events (posts, users, comments)
* Template variable system
* Delivery logging with retry
* Test webhook functionality

== Privacy Policy ==

Dragon Webhook Manager sends data from your WordPress site to external URLs that you configure. This may include:

* **Post data** - Title, content, URL, author information
* **User data** - Email, username, display name (for user-related triggers)
* **Comment data** - Author, email, content (for comment triggers)
* **Site information** - Site URL, site name

**Important:**
* Data is only sent to webhook URLs that YOU configure
* No data is sent to Dragon Core or any third parties
* You are responsible for ensuring the external services you connect to comply with applicable privacy laws (GDPR, CCPA, etc.)
* Webhook delivery logs are stored locally in your WordPress database and automatically cleaned up after 7 days (configurable)

For more information, visit [Dragon Core](https://dragoncore.ltd/).

== Upgrade Notice ==

= 1.0.0 =
Initial release of Dragon Webhook Manager.
