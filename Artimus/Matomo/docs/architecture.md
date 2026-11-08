# Architecture

## Overview

```
 Browser                                    Magento                                Matomo
 ───────                                    ───────                                ──────
 page HTML (full-page cacheable)
   └─ x-magento-init: tracker config  ◄──── Block\Tracker + ViewModel\TrackerConfig
                                              (store and catalog data only)
 tracker.js
   ├─ reads the "matomo" customer-data  ◄─── CustomerData\MatomoSection
   │   section (hashed User ID)               (private, per customer)
   ├─ queues _paq commands, loads matomo.js ───────────────────────────────────►  matomo.php
   ├─ cart-tracker.js   (cart changes)    ───────────────────────────────────►  matomo.php
   ├─ user-id.js        (login/logout)    ───────────────────────────────────►  matomo.php
   └─ checkout-steps.js (checkout steps)  ───────────────────────────────────►  matomo.php

 place order ───────────────────────────► Observer\CaptureOrderForTracking
                                              ├─ reads the _pk_id cookie (visitor ID)
                                              ├─ saves artimus_matomo_order_tracking row
                                              └─ publishes record ID to queue
                                          Model\Queue\OrderTrackingConsumer
                                              └─ Model\OrderTracker (PHP SDK) ─────►  matomo.php
                                          Cron\ResendFailedOrderTracking
                                              └─ re-publishes failed or stuck rows
```

## Design principles

- **The cached HTML stays anonymous.** The tracker config printed into the page uses only store and catalog data, because Magento caches the whole page for every visitor. Per-customer data, which is the hashed User ID, comes from a private customer-data section. Magento loads that section over AJAX or from localStorage.
- **Revenue doesn't depend on the browser.** Orders are sent server-side, through a durable queue with retries. The checkout never waits for Matomo and never fails because of it.
- **Every logged-in request carries the User ID.** That includes the page view, so Matomo keeps the session in one visit (see [Browser tracking](browser-tracking.md#user-id)).
- **Only `paq` sends companion commands.** Every browser component except the bootstrap sends commands through `js/model/paq.js`, which guarantees they never arrive before the page's default page view.

## Components

### PHP

| Class | Role |
|---|---|
| `Api\ConfigInterface` / `Model\Config` | Typed access to admin settings. Tracking is *active* only when it's enabled **and** both a Tracker URL and a site ID above 0 are set |
| `Block\Tracker` | Renders the tracker template only while tracking is active |
| `ViewModel\TrackerConfig` | Builds the cacheable `x-magento-init` JSON: tracker URL, site ID, consent mode, page-context commands, and the companion JS components registered in `etc/frontend/di.xml` |
| `Api\PageContextProviderInterface` / `Model\PageContextPool` | Provider pool that adds page-type commands (product, category, search, checkout) |
| `Model\PageContext\*Provider` | One provider per page type (see [Browser tracking](browser-tracking.md#page-context)) |
| `CustomerData\MatomoSection` | The private `matomo` section: `{userId: <hash>}` for a logged-in customer with User ID tracking on, otherwise `{}` |
| `Api\UserIdHasherInterface` / `Model\UserIdHasher` | HMAC-SHA256 of the customer ID, keyed with the first line of the deployment's `crypt/key` |
| `Api\ConsentCheckerInterface` / `Model\ConsentChecker` | Wraps Magento's cookie-restriction mode: whether consent is required, and whether this visitor gave it |
| `Model\Csp\MatomoPolicyCollector` | Allows the tracker host in `script-src`, `connect-src` and `img-src` |
| `Observer\CaptureOrderForTracking` | On `checkout_submit_all_after` it records the order, visitor ID, User ID and consent, then publishes to the queue |
| `Model\VisitorIdResolver` | Reads the 16-hex Matomo visitor ID from the raw `_pk_id.<siteId>.*` cookie |
| `Api\OrderTrackingRepositoryInterface` and `Model\OrderTracking*` | Service contract and persistence for `artimus_matomo_order_tracking` |
| `Model\Queue\OrderTrackingConsumer` | Consumer of `artimus.matomo.order.track`: sends one record and updates its status |
| `Api\OrderTrackerInterface` / `Model\OrderTracker` | Builds and sends the ecommerce order through the Matomo PHP SDK |
| `Model\TrackerFactory` | The only `new \MatomoTracker`. It disables the SDK's cookie handling, which has no meaning in a CLI consumer |
| `Cron\ResendFailedOrderTracking` | Every 10 minutes: re-queues failed rows and stuck pending rows, and abandons rows that are too old |

### JavaScript (`view/frontend/web/js/`)

| File | Role |
|---|---|
| `tracker.js` | Bootstrap: resolves the User ID, queues the base commands, initialises `paq`, loads `matomo.js` |
| `model/command-builder.js` | Pure function: config → ordered list of `_paq` commands |
| `model/user-id-resolver.js` | Waits (up to a timeout) for the `matomo` section when it's being reloaded |
| `model/visit-boundary.js` | Remembers whether the last page view had a User ID, so logout can start a new visit |
| `model/paq.js` | Shared command buffer. Holds companion commands until the bootstrap has run |
| `cart-tracker.js` + `model/cart-diff.js` | Sends a cart update whenever the `cart` section's contents change |
| `user-id.js` | Follows the `matomo` section after bootstrap: `setUserId` on login, `resetUserId` on logout |
| `checkout-steps.js` + `model/step-page-view.js` | A virtual page view per visible checkout step |

## Module layout

```
Api/                     service contracts (config, consent, hasher, page context, order tracking)
Block/Tracker.php        tracker block
CustomerData/            "matomo" customer-data section
Cron/                    resend job
Model/                   implementations, page-context providers, queue consumer, CSP collector
Observer/                order capture
ViewModel/               cacheable tracker config
etc/                     config, ACL, DI, schema, queue topology, cron, events per area
etc/frontend/            sections.xml, frontend DI (providers, JS components, section source)
view/frontend/           layout, templates, JS
Test/Unit/               PHPUnit tests
Test/js/                 JS specs and their Node runner
docs/                    this documentation
```

## Areas and events

The order observer is registered on `checkout_submit_all_after` in the `frontend`, `webapi_rest` and `graphql` areas. That covers Luma checkout, headless REST checkouts and GraphQL (PWA) checkouts. It isn't registered for `adminhtml`, so orders created in the admin aren't tracked.
