# Browser tracking

The storefront sends everything except orders from the browser, through Matomo's JavaScript tracker (`matomo.js`).

## How the tracker gets onto the page

`view/frontend/layout/default.xml` adds `Block\Tracker` to `before.body.end` on every storefront page. The block renders nothing unless tracking is [active](configuration.md#admin-settings). When it does render, `tracker.phtml` prints one `x-magento-init` script:

```json
{"*": {
  "Artimus_Matomo/js/tracker": {
    "trackerUrl": "https://analytics.example.com/",
    "siteId": 1,
    "requireConsent": false,
    "providerCommands": [["setEcommerceView", false, false, "Bags"]],
    "replacesPageView": false
  },
  "Artimus_Matomo/js/cart-tracker": {},
  "Artimus_Matomo/js/user-id": {}
}}
```

- **Built by `ViewModel\TrackerConfig`.** It uses only store and catalog data, because the HTML is full-page cached and shared by every visitor. The JSON is hex-escaped (`JsonHexTag`), so a product or category name can't close the script tag.
- **Companions are registered in `etc/frontend/di.xml`.** These are the components after the tracker (`cart-tracker`, `user-id`), added as `jsComponents` on `TrackerConfig`. A new companion needs only a DI entry.
- **The checkout page adds one more.** `checkout_index_index.xml` adds `checkout-steps.phtml`, which starts `Artimus_Matomo/js/checkout-steps`.

## Bootstrap sequence (`tracker.js`)

1. **Guard.** Runs once per page. A second call, even while the first is still waiting, does nothing.
2. **Resolve the User ID.** `model/user-id-resolver.js` reads the `matomo` customer-data section (see [User ID](#user-id)). It waits at most **2000 ms**.
3. **Detect logout.** `model/visit-boundary.js` checks whether the previous page view had a User ID and this one doesn't (see [Logout](#logout-starts-a-new-visit)).
4. **Build the base commands.** `model/command-builder.js` returns them in this order:
   1. `requireConsent`, only when cookie consent is required
   2. `setTrackerUrl <trackerUrl>matomo.php`
   3. `setSiteId <siteId>`
   4. `setUserId <hash>`, only for a logged-in customer
   5. `enableLinkTracking`
   6. page-context provider commands, e.g. `setEcommerceView` or `trackSiteSearch`
   7. `appendToTrackingUrl new_visit=1`, only right after logout
   8. `trackPageView`, unless a provider replaces it
   9. `appendToTrackingUrl ''`, which resets step 7 so only that one request starts a new visit
5. **Push** those commands to `window._paq`, and set up consent if it's required.
6. **Hand over.** It records the User ID it sent with `paq.setInitialUserId(...)`, then calls `paq.init()`. That flushes any buffered companion commands and runs the `paq.whenReady` callbacks.
7. **Load** `<trackerUrl>matomo.js` asynchronously.

### The `paq` contract (`model/paq.js`)

Only the bootstrap writes to `window._paq` directly. Every companion component pushes through the shared `paq` model:

- **`paq.push(command)`** buffers until `init()` runs, and passes straight through after that. So a cart update or checkout step can't reach Matomo before the page's own page view.
- **`paq.whenReady(fn)`** runs `fn` once, after `init()` has flushed the buffer, or immediately if it already has.
- **`paq.getInitialUserId()`** returns the User ID the bootstrap sent, so `user-id.js` never sends it a second time.

When tracking isn't active the tracker isn't rendered and `init()` never runs, so companions stay silent.

## Page context

`Model\PageContextPool` asks every provider registered in `etc/frontend/di.xml` for extra commands. It also asks whether one of them replaces the default page view.

| Provider | Page (`full action name`) | Commands | Replaces page view |
|---|---|---|---|
| `CategoryViewProvider` | `catalog_category_view` | `setEcommerceView(false, false, <category name>)` | No |
| `ProductViewProvider` | `catalog_product_view` | `setEcommerceView(<sku>, <name>, [<up to 5 category names>], <final price>)` | No |
| `SearchResultProvider` | `catalogsearch_result_index` | `trackSiteSearch(<query>, false, <result count>)` | Yes, a site search is itself a page view |
| `CheckoutPageProvider` | `checkout_index_index` | none | Yes, `checkout-steps.js` sends per-step page views instead |

## Cart updates (`cart-tracker.js`)

`cart-tracker.js` subscribes to Magento's `cart` customer-data section, which every add, update and remove refreshes. For each change, `model/cart-diff.js` compares a signature of the cart against the last one sent:

- The signature is the sorted `sku:qty` pairs plus the subtotal, stored in localStorage under `artimus-matomo-cart-sig`.
- If it differs, the tracker sends `addEcommerceItem` for **every** item (SKU, name, price excluding tax, qty), then `trackEcommerceCartUpdate(<subtotal>)`. Matomo expects the whole cart on each update, not just the change.
- An emptied cart sends `clearEcommerceCart`, then an update with 0.
- **On the order success page** it sends nothing and resets the signature. The order itself comes from the server.
- **On the checkout page an empty cart** resets the signature without sending anything. Luma empties the `cart` section on the checkout page right after an order is placed, which would otherwise look like the customer emptied the cart.
- **If localStorage is unavailable,** the worst case is a repeated cart update.

## Checkout steps (`checkout-steps.js`)

The one-page checkout is a single URL, so `checkout-steps.js` watches Magento's `step-navigator`. Whenever a step becomes visible it sends a virtual page view:

```
setCustomUrl     https://store.example/checkout/#shipping
setDocumentTitle Checkout: Shipping
trackPageView
```

The same step isn't sent twice in a row. These page views form the checkout funnel in Matomo.

## User ID

### Why every request carries it

With User ID tracking enabled, a logged-in customer's requests carry `uid`. The value is `HMAC-SHA256(customerId, <first crypt/key line>)`, 64 hex characters, so Matomo never sees the raw customer ID or email.

Matomo's default `enable_userid_overwrites_visitorid = 1` uses `substr(sha1(uid), 0, 16)` as the visitor ID for any request that has a `uid`. A request without `uid` uses the `_pk_id` cookie's visitor ID. Matomo's visit matching then works like this:

1. **Login:** the first request with a `uid` finds the guest visit by browser fingerprint and re-keys it to the User ID's visitor ID. So the guest pages before login join the logged-in visit.
2. **A request without `uid` after that** looks up the cookie visitor ID and doesn't find that visit any more. Matomo opens a **second, overlapping visit**.

So every request of a logged-in session has to carry `uid`, the page view included. Early versions sent `setUserId` only after the page view, which split sessions in two.

### How the User ID is delivered

- **Private section.** `CustomerData\MatomoSection` serves the private `matomo` section. It's `{userId: <hash>}` when the customer is logged in and User ID tracking is enabled, and `{}` otherwise. The hash is never part of cached HTML.
- **Bootstrap reads it first.** `tracker.js` reads the section *before* it queues the page view, and sends `setUserId` ahead of `trackPageView`.
- **Waiting.** When the section is being reloaded, `model/user-id-resolver.js` waits for the reload to land. Magento reports a reload through `customerData.getExpiredSectionNames()`, which happens on the first page after login, after logout, on a new browser session, and when the customer-data cache times out. The wait is capped at **2000 ms**, then the page view goes out without `uid`. Later pages carry it, and Matomo keeps them in the same visit.
- **No wait otherwise.** On all other pages, guests included, the resolver answers at once from localStorage.
- **Invalidation.** `etc/frontend/sections.xml` invalidates `matomo` on `customer/account/loginPost`, `customer/account/logout`, `customer/account/createpost` and `customer/ajax/login`, the last being the checkout "Sign In" popup.
- **Changes after bootstrap.** `user-id.js` waits for `paq.whenReady`, starts from the User ID the bootstrap sent, and follows later changes. It sends `setUserId` when an ID appears or changes, for example an AJAX login without a reload, and `resetUserId` when the ID disappears.

### Effects worth knowing

- **Same customer, back within 30 minutes:** if a customer logs out and back in on the same device within 30 minutes, Matomo puts the new logged-in requests into that customer's earlier visit. This is Matomo's normal User ID behaviour.
- **Crypt key rotation:** the hash is keyed with the first line of `crypt/key`. Rotating that key changes every customer's User ID, so Matomo sees them as new users from then on.
- **Opting out:** with Enable User ID Tracking set to No, the section is always `{}` and orders carry no User ID either.

## Logout starts a new visit

A logout is a full page load, so the next page doesn't know a customer was logged in a moment ago. `model/visit-boundary.js` keeps a boolean marker in localStorage:

- The key is `artimus-matomo-had-user-id` and the value is `1`. The User ID and its hash are **never** stored.
- When the bootstrap sends a User ID, it sets the marker.
- When the bootstrap sends none and the marker is set, it clears the marker. That page view is then wrapped in `appendToTrackingUrl new_visit=1` / `appendToTrackingUrl ''`, so Matomo starts a fresh visit for the logged-out browsing and later events on the page don't force more visits.

Details:

- The marker holds no personal data, so it's written even before cookie consent.
- If localStorage throws (private mode, blocked storage), tracking continues without `new_visit`.
- When a provider replaces the page view (search results, checkout), `new_visit` isn't added. Logout redirects to a regular page, so in practice it lands on one that has a page view.
- A logout without a page reload sends `resetUserId` only. Magento's logout always reloads.

## Consent

When Magento's Cookie Restriction Mode is on (`ConsentCheckerInterface::isConsentRequired()`):

- The base commands start with `requireConsent`, so `matomo.js` sends nothing yet.
- If the visitor already accepted the cookie notice (the `user_allowed_save_cookie` cookie exists), the tracker pushes `rememberConsentGiven` straight away.
- Otherwise it pushes `rememberConsentGiven` when Magento fires `user:allowed:save:cookie`, which is when the visitor accepts.

The cached tracker config contains only *whether* consent is required, which is store config. It never contains whether this visitor gave it, which is per-visitor state.

## Full-page cache safety

The page HTML is shared, so these rules hold:

- `TrackerConfig` uses store config, the current category and product, and search data only.
- `ConsentCheckerInterface::hasConsent()` (visitor cookie) is never used for rendering.
- The User ID arrives only through the private `matomo` section.

You can check this by loading a cached page (`X-Magento-Cache-Debug: HIT`) as a logged-in customer: it contains no 64-hex User ID.
