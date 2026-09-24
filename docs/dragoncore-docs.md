# Dragon Webhook Manager

Outgoing webhooks for developers: POST to any URL when things happen in WordPress.

## Getting started
**Tools → Webhook Manager → Add New**: pick a trigger (post published, user registered, comment posted…), enter the destination HTTPS URL, save. An internationalised host name (for example `https://bücher.example/hook`) is accepted and stored in its ASCII (punycode) form, which is what the Logs tab shows. The **Logs** tab shows every delivery with status codes. Add-ons can append label/value pairs to a delivery's details modal through the `dragonwebhookmanager_log_details` filter (Webhook Manager Pro shows retry status there).

## Payloads
The body is a JSON template with `{{variable}}` placeholders; the variables available (site, post, user and comment, plus any an add-on registers) are listed under the template on the edit screen. Text values are escaped to sit inside double quotes (`"title": "{{post_title}}"`); numbers are inserted as written. **Send test** fills post, user and comment variables with sample data for those triggers, so you see the shape your endpoint will receive without exposing a real account or post.

## Settings
**Tools → Webhook Manager → Settings**:
- **Log retention** - how many days of delivery logs to keep (1 to 365, default 7). A daily cleanup removes older entries.
- **Delivery timeout** - how long to wait for the endpoint before a delivery is marked failed (5 to 120 seconds, default 30).
- **Delete data on uninstall** - off by default; tick it to remove webhooks, logs, and settings when the plugin is deleted.

There is no cap on the number of webhooks.

## Extending
Other plugins can register triggers through the `dragonwebhookmanager_triggers` filter (key => label, category, hook) and dispatch them with `do_action( 'dragonwebhookmanager_trigger_fired', $trigger, $context )`. Add placeholders to the edit screen's list with the `dragonwebhookmanager_template_variables` filter and fill them with `dragonwebhookmanager_parse_variable` (return a string, int or float, or null to leave the placeholder as written).

## Data & privacy
Delivery logs live in your database with pruning (see Settings). Webhook destinations receive the event data you configured - audit them like any integration. **Uninstall keeps configuration by default.**

## Dragon Webhook Manager Pro
WooCommerce triggers (orders, products, stock), HMAC-SHA256 request signing so receivers can verify authenticity, and automatic retry with backoff for failed deliveries.

## Uninstall
Deleting the plugin keeps all its data by default, so a reinstall picks up where you left off. To remove everything on uninstall, tick **Delete data on uninstall** under Settings first, or:

```bash
wp option update dragonwebhookmanager_delete_data_on_uninstall 1
```
