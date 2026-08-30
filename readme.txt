=== Dragon Webhook Manager ===
Contributors: dragoncoreltd
Tags: webhooks, automation, notifications, api, integration
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress and WooCommerce store to any service with webhooks. The simplest way to automate your site.

== Description ==

Dragon Webhook Manager lets you send HTTP requests to external services whenever specific events occur on your WordPress site. Perfect for:

* **Slack/Discord notifications** - Get notified when posts are published or comments arrive
* **Zapier/Make integrations** - Trigger complex workflows automatically
* **CRM updates** - Sync user registrations and profile data

= Features =

* **Easy Setup** - No coding required. Point-and-click interface.
* **7 Trigger Events** - Posts, users, and comments
* **Template Variables** - Dynamic payloads with `{{post_title}}`, `{{user_email}}`, etc.
* **Delivery Logs** - Track every webhook with request/response details
* **Test Button** - Verify webhooks before going live
* **Retry Failed** - One-click retry for failed deliveries
* **Settings** - Choose how long delivery logs are kept and how long to wait for an endpoint
* **Extensible** - Other plugins can register their own triggers through the `dragonwebhookmanager_triggers` filter

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

= Pro Features =

The free plugin is complete on its own: create as many webhooks as you need on every WordPress core trigger. **[Dragon Webhook Manager Pro](https://dragoncore.ltd/plugins/dragon-webhook-manager-pro)** adds:

* **20+ WooCommerce triggers:**
  * Orders: created, paid, completed, cancelled, refunded
  * Customers: registered, updated, deleted
  * Products: low stock, out of stock, back in stock
  * Subscriptions: renewed, cancelled, expired
* **Conditional logic** - Only send when order_total > $100
* **HMAC signing** - Secure your webhooks with signatures
* **Auto-retry** - Automatic retry with exponential backoff

WooCommerce order processing and inventory alerts are covered by these triggers.

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

= How many webhooks can I create? =

As many as you need. There is no cap on the number of webhooks.

= How long are delivery logs kept? =

Seven days by default. Change the retention period (1 to 365 days) and the delivery timeout (5 to 120 seconds) under Tools -> Webhook Manager -> Settings.

= Can I use this with WooCommerce? =

The plugin works alongside WooCommerce but ships no WooCommerce-specific triggers of its own. Other plugins can register additional triggers through the `dragonwebhookmanager_triggers` filter; see the Pro Features section above for an add-on that does.

== Changelog ==

= 1.0.11 =
* Removed the cap on the number of webhooks - create as many as you need.
* The trigger dropdown now lists only triggers that can actually fire on your site; other plugins can register more through the `dragonwebhookmanager_triggers` filter.
* New Settings screen (Tools -> Webhook Manager -> Settings): log retention (1 to 365 days), delivery timeout (5 to 120 seconds), and the delete-data-on-uninstall opt-in.
* Removed the License tab from the free plugin.

= 1.0.9 =
* Compatibility: tested up to WordPress 7.1.
* Housekeeping: corrected the contributor name in the plugin readme.

= 1.0.8 =
* Documentation: the privacy section now accurately describes where webhook data goes.
* Performance: a composite index speeds up webhook lookups on every trigger; schema updates now apply without reactivating.
* Accessibility: icon buttons are screen-reader labelled.

= 1.0.7 =
* Data safety: uninstalling the plugin no longer deletes its data unless you explicitly opt in first — a reinstall now picks up exactly where you left off.

= 1.0.6 =
* New look: the Dragon design system arrives — a consistent Dragon Core header, cleaner tables, and unified status colours. Purely visual; no behaviour changes.

= 1.0.5 =
* Fix: settings could be lost on a deactivate then reactivate update; the migration now carries each value before removing the old copy.

= 1.0.4 =
* Renamed all option, hook, function and constant prefixes to the unique `dragonwebhookmanager_` / `DRAGONWEBHOOKMANAGER_` prefix. Existing settings are migrated automatically on update; configured webhooks and delivery logs are unaffected.

= 1.0.3 =
* Add the integration API for add-ons (re-delivery and logging hooks), so automatic retry and request signing add-ons work correctly.

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
* No data is sent to Dragon Core, and the plugin has no service of its own to send it to
* Data goes only to the endpoints you configure — you choose every destination, and no webhook fires until you create one
* You are responsible for reviewing the terms and privacy policy of any service you send data to, and for ensuring those transfers comply with applicable privacy laws (GDPR, CCPA, etc.)
* Webhook delivery logs are stored locally in your WordPress database and automatically cleaned up after 7 days (configurable under Settings, 1 to 365 days)

For more information, visit [Dragon Core](https://dragoncore.ltd/).

== Upgrade Notice ==

= 1.0.11 =
Removes the webhook cap and adds a Settings screen for log retention and delivery timeout. Existing webhooks and logs are unaffected.

= 1.0.0 =
Initial release of Dragon Webhook Manager.
