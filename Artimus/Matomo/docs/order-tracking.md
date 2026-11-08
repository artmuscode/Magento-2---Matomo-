# Order tracking

Orders are sent to Matomo from the server, not the browser:

- **Revenue is reliable:** an ad blocker, a closed tab or a failed `matomo.js` load can't lose an order.
- **Checkout is never slowed or broken:** placing an order only writes one row and publishes one queue message. Talking to Matomo happens later, in a consumer.

## Flow

```
checkout_submit_all_after
  └─ Observer\CaptureOrderForTracking
       ├─ tracking not active for the order's store → do nothing
       ├─ visitor has not given cookie consent      → save row, status "skipped"
       └─ otherwise
            ├─ visitor_id = Matomo visitor ID from the _pk_id cookie (or null)
            ├─ user_id    = hashed customer ID (logged-in customer, User ID tracking on)
            ├─ save row, status "pending"
            └─ publish row ID to topic artimus.matomo.order.track

queue consumer artimus.matomo.order.track
  └─ Model\Queue\OrderTrackingConsumer::process(rowId)
       ├─ row or order gone → drop the message (logged)
       ├─ not pending/failed, or ≥ 10 attempts → skip
       ├─ attempts + 1, save
       ├─ Model\OrderTracker::track(order, row)   → Matomo
       ├─ success → status "sent", sent_at, last_error cleared
       └─ failure → status "failed", last_error (token redacted, 255 chars)

cron, every 10 minutes
  └─ Cron\ResendFailedOrderTracking
       ├─ "failed" rows                     → re-publish
       ├─ "pending" rows older than 30 min  → re-publish (message lost or consumer was down)
       └─ older than 3 days, or ≥ 10 attempts → status "abandoned" (logged as an error)
```

The observer never throws into checkout. Every failure is caught and logged as `Artimus_Matomo: failed to capture order …`.

It runs in the `frontend`, `webapi_rest` and `graphql` areas. Orders created in the admin aren't tracked.

## The `artimus_matomo_order_tracking` table

| Column | Meaning |
|---|---|
| `entity_id` | Row ID. This is the queue message body |
| `order_id` | `sales_order.entity_id`, unique. The row is deleted with the order (`ON DELETE CASCADE`) |
| `store_id` | Store the order was placed in. Config is read for this store |
| `visitor_id` | 16-hex Matomo visitor ID read from the browser's `_pk_id.<siteId>.*` cookie at checkout, or null |
| `user_id` | 64-hex hashed customer ID, or null for guests and when User ID tracking is off |
| `status` | `pending`, `sent`, `failed`, `skipped` or `abandoned` |
| `attempts` | Send attempts so far (maximum 10) |
| `last_error` | Last failure message, with the auth token redacted |
| `created_at` / `sent_at` | When the order was captured / successfully sent (UTC) |

| Status | Meaning | What happens next |
|---|---|---|
| `pending` | Captured and queued | The consumer sends it. Cron re-queues it after 30 min if it's still pending |
| `sent` | Matomo accepted it | Nothing |
| `failed` | Last attempt failed | Cron re-queues it every 10 min |
| `skipped` | The visitor never accepted cookies | Never sent |
| `abandoned` | Over 3 days old or 10 attempts | Never sent. Investigate `last_error` |

The table is behind a service contract, `Api\OrderTrackingRepositoryInterface`, with `getById`, `save`, `delete` and `getList(SearchCriteria)`.

## What is sent (`Model\OrderTracker`)

The SDK tracker is built by `Model\TrackerFactory` for the order's store, with the site ID and the **Server-Side Tracker URL**. It has cookie support disabled, because a CLI consumer has no browser cookies. The tracker then sets:

| SDK call | Value | Condition |
|---|---|---|
| `setTokenAuth` | Auth Token | Token configured |
| `setVisitorId` (`cid`) | Captured `visitor_id`, or `substr(sha256('artimus-matomo-order:' . incrementId), 0, 16)` when none was captured | Token configured |
| `setIp` (`cip`) | The order's remote IP | Token configured |
| `setForceVisitDateTime` (`cdt`) | The order's `created_at` | Token configured |
| `setUserId` (`uid`) | Captured `user_id` | Customer order with User ID tracking on |
| `addEcommerceItem` | SKU, name, (no category), price, qty ordered for each visible item | Always |
| `doTrackEcommerceOrder` | increment ID, grand total, subtotal, tax, shipping, discount (absolute) | Always |

- **Without a token** the order is still sent, but without `cid`, `cip` and `cdt`, and a warning is logged. Matomo then can't tie it to the browser visit or date it correctly. Configure a token.
- **An empty or false SDK response** counts as a failure.

## How the order joins the browser visit

Matomo matches the server request to the right visit using these rules:

- **Guest with a cookie:** `cid` is the browser's own visitor ID, so the order lands in the visit that holds the guest's browsing and cart.
- **Logged-in customer:** `uid` is set, and with Matomo's default `enable_userid_overwrites_visitorid = 1` the visitor ID becomes `sha1(uid)`. That's the same visitor the browser's logged-in requests used, so the order lands in the customer's visit.
- **No cookie** (`matomo.js` was blocked, so the browser never got a `_pk_id` cookie): the order-scoped `cid` is the same on every retry of that order and different between orders.
  - Without a forced `cid`, Matomo would match by `config_id`. That's a fingerprint of IP and user agent, and every server-side request shares one SDK user agent. Unrelated orders would merge into each other's visits.
  - With `cid` forced, Matomo looks the visit up by visitor ID only.
- **`cdt` is the order time**, so a retry hours later is still filed at the moment of purchase.
- **`increment_id` deduplicates.** Matomo records each order ID once, so a duplicate send after a lost response doesn't double the revenue.
