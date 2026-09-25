# Dragon Webhook Manager

Outgoing webhooks for developers: POST to any URL when things happen in WordPress.

## Getting started
**Tools → Webhook Manager → Add New**: pick a trigger (post published, user registered, comment posted…), enter the destination HTTPS URL, save. An internationalised host name (for example `https://bücher.example/hook`) is accepted and stored in its ASCII (punycode) form, which is what the Logs tab shows. The post triggers (published, updated, trashed) fire for public post types such as posts, pages and public custom post types; internal records WordPress stores as posts (templates, global styles, navigation menus, oEmbed caches) do not send webhooks. The comment triggers (submitted, approved) fire for ordinary comments and WooCommerce product reviews; records other plugins store as comments (WooCommerce order notes, Action Scheduler logs) and pingbacks/trackbacks do not send webhooks. The **Logs** tab shows every delivery with status codes, 100 per page. Add-ons can append label/value pairs to a delivery's details modal through the `dragonwebhookmanager_log_details` filter (Webhook Manager Pro shows retry status there).

## Payloads
The body is a JSON template with `{{variable}}` placeholders; the variables available (site, post, user and comment, plus any an add-on registers) are listed under the template on the edit screen. `{{trigger_event}}` holds the key of the trigger being delivered, such as `post_published`. Text values are escaped to sit inside double quotes (`"title": "{{post_title}}"`); numbers are inserted as written. **Send test** fills post, user and comment variables with sample data for those triggers, so you see the shape your endpoint will receive without exposing a real account or post (see *Test sends* below for the variables a test leaves empty). Add-ons fill their own triggers' variables in a test the same way. A test sends what is on screen, including edits you have not saved yet, and a **Retry** from the Logs tab resends the logged body to the webhook's current URL. Both carry the same extra headers (such as a request signature) as a triggered delivery.

Every variable the payload fills, by the triggers that fill it (the edit screen lists the most used ones):

| Group | Variables | Filled for |
|-------|-----------|------------|
| Global | `{{site_url}}`, `{{site_name}}`, `{{admin_email}}`, `{{timestamp}}` (Unix seconds), `{{timestamp_iso}}` (ISO 8601, UTC), `{{trigger_event}}` | Every trigger |
| Post | `{{post_id}}`, `{{post_title}}`, `{{post_content}}`, `{{post_excerpt}}` (the first 55 words of the content when the post has no excerpt), `{{post_url}}`, `{{post_type}}`, `{{post_status}}`, `{{post_date}}`, `{{post_modified}}` (both in the site's time zone), `{{post_author_id}}`, `{{post_author_name}}`, `{{post_author_email}}` | Post published, updated, trashed |
| User | `{{user_id}}`, `{{user_email}}`, `{{user_login}}`, `{{user_display_name}}`, `{{user_first_name}}`, `{{user_last_name}}`, `{{user_role}}` (comma-separated roles), `{{user_registered}}` (UTC) | User registered, user login |
| Comment | `{{comment_id}}`, `{{comment_author}}`, `{{comment_email}}`, `{{comment_url}}` (the commenter's website), `{{comment_content}}`, `{{comment_date}}` (site time zone), `{{comment_status}}` (as stored: `1` approved, `0` pending, `spam` for a comment caught as spam), `{{comment_post_id}}`, `{{comment_post_title}}`, `{{comment_post_url}}` | Comment submitted, comment approved |

A placeholder a trigger does not fill, and that no add-on fills, is left in the body as written.

**Test sends.** The sample post has no author and the sample comment belongs to no post, so a real account or post never ends up in a test delivery. In a test, `{{post_author_name}}`, `{{post_author_email}}`, `{{comment_post_title}}` and `{{comment_post_url}}` are empty, and `{{post_author_id}}` and `{{comment_post_id}}` are `0`. `{{post_url}}` is a placeholder URL that does not open a real post. The global variables hold this site's real values; every other variable holds a sample value. A triggered delivery fills all of them from the real post, user or comment.

## Settings
**Tools → Webhook Manager → Settings**:
- **Log retention** - how many days of delivery logs to keep (1 to 365, default 7). A daily cleanup removes older entries.
- **Delivery timeout** - how long to wait for the endpoint before a delivery is marked failed (5 to 120 seconds, default 30).
- **Delete data on uninstall** - off by default; tick it to remove webhooks, logs, and settings when the plugin is deleted.

There is no cap on the number of webhooks.

## Extending
Other plugins can register triggers through the `dragonwebhookmanager_triggers` filter (key => label, category, hook) and dispatch them with `do_action( 'dragonwebhookmanager_trigger_fired', $trigger, $context )`. Add placeholders to the edit screen's list with the `dragonwebhookmanager_template_variables` filter and fill them with `dragonwebhookmanager_parse_variable` (return a string, int or float, or null to leave the placeholder as written). Supply sample data for your triggers' test sends with `dragonwebhookmanager_sample_context` (receives the sample context array and the trigger key; return an array in the shape your trigger dispatches, built from sample values only). Change which post types fire the post triggers with `dragonwebhookmanager_post_types` (list of post type names, default the viewable types; also receives the trigger key), for example to add a non-public custom post type. Change which comment types fire the comment triggers with `dragonwebhookmanager_comment_types` (list of comment type names, default `comment` and `review`, where a comment stored with an empty type counts as `comment`; also receives the trigger key), for example to add `pingback` or `trackback`. Every request, including a test and a retry, passes its headers through `dragonwebhookmanager_webhook_headers` (header map, webhook row, request body). A `Content-Type` header you set, in any letter case, replaces the default `application/json`. `dragonwebhookmanager_logs_cleared` fires after **Clear Logs** deletes every delivery log row, so an add-on can drop state it keeps per log ID. `dragonwebhookmanager_webhook_deleted` (webhook ID) fires after a webhook and its logs are deleted, so an add-on can drop settings it keeps for that webhook.

## Data & privacy
Delivery logs live in your database with pruning (see Settings). Webhook destinations receive the event data you configured - audit them like any integration. **Uninstall keeps configuration by default.**

## Dragon Webhook Manager Pro
WooCommerce triggers (orders, products, stock), HMAC-SHA256 request signing so receivers can verify authenticity, and automatic retry with backoff for failed deliveries.

## Uninstall
Deleting the plugin keeps all its data by default, so a reinstall picks up where you left off. To remove everything on uninstall, tick **Delete data on uninstall** under Settings first, or:

```bash
wp option update dragonwebhookmanager_delete_data_on_uninstall 1
```
