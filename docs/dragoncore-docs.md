# Dragon Webhook Manager

Outgoing webhooks for developers: POST to any URL when things happen in WordPress.

## Getting started
**Tools → Webhook Manager → Add New**: pick a trigger (post published, user registered, comment posted…), enter the destination HTTPS URL, save. The **Logs** tab shows every delivery with status codes.

## Payloads
JSON bodies with the event's relevant object (post, user, comment) — field reference per trigger on the edit screen.

## Settings
**Tools → Webhook Manager → Settings**:
- **Log retention** - how many days of delivery logs to keep (1 to 365, default 7). A daily cleanup removes older entries.
- **Delivery timeout** - how long to wait for the endpoint before a delivery is marked failed (5 to 120 seconds, default 30).
- **Delete data on uninstall** - off by default; tick it to remove webhooks, logs, and settings when the plugin is deleted.

There is no cap on the number of webhooks.

## Extending
Other plugins can register triggers through the `dragonwebhookmanager_triggers` filter (key => label, category, hook) and dispatch them with `do_action( 'dragonwebhookmanager_trigger_fired', $trigger, $context )`.

## Data & privacy
Delivery logs live in your database with pruning (see Settings). Webhook destinations receive the event data you configured - audit them like any integration. **Uninstall keeps configuration by default.**

## Dragon Webhook Manager Pro
WooCommerce triggers (orders, products, stock), HMAC-SHA256 request signing so receivers can verify authenticity, and automatic retry with backoff for failed deliveries.

## Uninstall
Deleting the plugin keeps all its data by default, so a reinstall picks up where you left off. To remove everything on uninstall, tick **Delete data on uninstall** under Settings first, or:

```bash
wp option update dragonwebhookmanager_delete_data_on_uninstall 1
```
