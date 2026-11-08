# Troubleshooting

Start with the browser's network tab (filter `matomo.php`) and this table:

```sql
SELECT status, COUNT(*) FROM artimus_matomo_order_tracking GROUP BY status;
SELECT * FROM artimus_matomo_order_tracking WHERE status IN ('failed','abandoned') ORDER BY entity_id DESC LIMIT 20;
```

Module log lines start with `Artimus_Matomo:` in `var/log/system.log` / `var/log/exception.log`.

## No `matomo.php` requests at all

| Check | Fix |
|---|---|
| Page source contains `Artimus_Matomo/js/tracker` | If not, tracking isn't active for this store view. It needs Enable = Yes, a Tracker URL and a Site ID above 0. Then flush the cache |
| `matomo.js` loads (network tab) | Check the Tracker URL. On an HTTPS store it must be HTTPS. A CSP violation in the console means the host doesn't match the Tracker URL |
| Cookie Restriction Mode is on | Nothing is sent until the visitor accepts the cookie notice. This is by design |
| Ad or tracker blocker | Browser tracking is blocked. Orders are still sent server-side |

## Logged-in requests have no `uid`

- **Check the setting:** Enable User ID Tracking must be Yes for the store view.
- **Check the section:** in the browser, `JSON.parse(localStorage['mage-cache-storage']).matomo` should be `{userId: "<64 hex>", …}` while logged in. If it's `{}` after login, check `etc/frontend/sections.xml` is deployed (cache flush) and that `customer/section/load?sections=matomo` returns data.
- **Slow section reload:** the first page after login waits up to 2 s for the section reload. A slower or failing `customer/section/load` sends that one page view without `uid`. Later pages should carry it.

## A logged-in session shows as two visits

The usual cause is a request without `uid` in the middle of the logged-in session. Find the request in the network tab and check:

- **Custom tracking code:** a theme or another extension pushing to `_paq` outside `Artimus_Matomo/js/model/paq` can send before the User ID is set.
- **Matomo server setting:** `enable_userid_overwrites_visitorid` has been changed on the server. The module expects the default.
- **Expected, not a fault:** a customer who logs out and back in on the same device within 30 minutes is put back in their earlier visit. See [Browser tracking](browser-tracking.md#effects-worth-knowing).

## Orders stuck in `pending`

The consumer isn't running.

- **With cron:** check that Magento cron runs and that `cron_consumers_runner` isn't disabled in `env.php`.
- **Without cron:** run `bin/magento queue:consumers:start artimus.matomo.order.track` under a supervisor.
- **Either way:** cron re-queues pending rows older than 30 minutes, but only a running consumer sends them.

## Orders in `failed` or `abandoned`

Read `last_error`:

| Error | Cause |
|---|---|
| Empty or failed response | Matomo rejected the request. Check the token has write/admin access to the site ID, and that Ecommerce is enabled for the site |
| Connection or timeout errors | The **Server-Side Tracker URL** isn't reachable from the Magento server. Inside Docker, use the internal service URL (e.g. `http://matomo/`) |
| `abandoned` | 10 failed attempts, or the row is more than 3 days old. To re-send after fixing the cause, see below |

To re-send abandoned rows, reset them to `failed` and refresh `created_at`. The cron re-queues `failed` rows on its next run, and abandons again anything whose `created_at` is more than 3 days old:

```sql
UPDATE artimus_matomo_order_tracking
SET status = 'failed', attempts = 0, created_at = UTC_TIMESTAMP()
WHERE status = 'abandoned';
```

Matomo still records each order at its original time, because the visit time sent is the order's own `created_at`, not this row's.

## Orders appear in Matomo but not in the right visit

- **Token:** without an auth token the order is sent without `cid`, IP and visit time (a warning is logged). Configure a token.
- **Consent:** with consent required, an order is only sent if the visitor accepted cookies. Otherwise its row is `skipped`.
- **No cookie:** orders without a captured visitor ID get their own visit by design (see [Order tracking](order-tracking.md#how-the-order-joins-the-browser-visit)).

## Testing with headless browsers

Matomo matches visits by browser fingerprint (IP plus client hints). So test runs from one machine within 30 minutes merge into one visit. Wait 30 minutes between runs, or judge by which visit each run's actions land in.
