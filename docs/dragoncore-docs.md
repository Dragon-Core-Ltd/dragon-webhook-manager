# Dragon Webhook Manager

Outgoing webhooks for developers: POST to any URL when things happen in WordPress.

## Getting started
**Tools → Webhook Manager → Add New**: pick a trigger (post published, user registered, comment posted…), enter the destination HTTPS URL, save. The **Logs** tab shows every delivery with status codes.

## Payloads
JSON bodies with the event's relevant object (post, user, comment) — field reference per trigger on the edit screen.

## Data & privacy
Delivery logs live in your database with pruning. Webhook destinations receive the event data you configured — audit them like any integration. **Uninstall keeps configuration by default.**

## Dragon Webhook Manager Pro
WooCommerce triggers (orders, products, stock), HMAC-SHA256 request signing so receivers can verify authenticity, and automatic retry with backoff for failed deliveries.
