# Artimus_Matomo documentation

Artimus_Matomo sends the Magento storefront journey to Matomo. It covers everything from the first page view to the placed order. Tracking runs on two paths:

- **Browser path.** `matomo.js` records page views, product, category and search views, cart changes and checkout steps. For logged-in customers it also records a hashed User ID.
- **Server path.** A message-queue consumer sends each placed order through the Matomo PHP SDK. Revenue reaches Matomo even when an ad blocker stops `matomo.js`.

The server path ties each order back to the visit the browser recorded, so one visit shows the whole journey and the purchase.

## Contents

| Document | What it covers |
|---|---|
| [Architecture](architecture.md) | Components, how they fit together, and the module's file layout |
| [Configuration](configuration.md) | Admin settings, scopes, and what to set up on the Matomo side |
| [Browser tracking](browser-tracking.md) | Tracker bootstrap, page context, cart, checkout steps, User ID, logout, consent, CSP, full-page cache |
| [Order tracking](order-tracking.md) | Order capture, the tracking table, queue, consumer, retries, and how orders join the browser visit |
| [Troubleshooting](troubleshooting.md) | Symptoms, causes and fixes |
| [Development](development.md) | Tests, the JS spec runner, local Matomo, and how to extend the module |

## What Matomo receives

| Storefront event | Matomo data | Sent from |
|---|---|---|
| Any page | Page view | Browser |
| Category page | Page view with `setEcommerceView` (category name) | Browser |
| Product page | Page view with `setEcommerceView` (SKU, name, up to 5 categories, final price) | Browser |
| Search results | Site search (keyword, result count). This replaces the page view | Browser |
| Cart change | Cart update with every item and the subtotal (`trackEcommerceCartUpdate`) | Browser |
| Checkout | A virtual page view per step, e.g. `/checkout/#shipping`, titled `Checkout: Shipping` | Browser |
| Logged-in customer | `uid` (HMAC-SHA256 of the customer ID) on every request | Browser and server |
| Logout | `new_visit=1` on the first page view after logout | Browser |
| Placed order | Ecommerce order: items, grand total, subtotal, tax, shipping, discount | Server (queue) |
