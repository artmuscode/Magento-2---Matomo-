# Artimus_Matomo

Tracks the storefront journey in [Matomo](https://matomo.org/), from the first page view to the
placed order. The browser reports page views, product and category views, searches, cart changes and
checkout steps. A queue worker sends each order from the server, so ad blockers can't lose revenue.
Logged-in customers are linked with a hashed User ID and stay in one visit.

Full documentation is in [`Artimus/Matomo`](Artimus/Matomo/docs/index.md).

## Requirements

- Magento 2.4.8, PHP 8.3 or 8.4
- A Matomo site with **Ecommerce** enabled and its currency set to the store's currency
- A Matomo auth token with **write** or **admin** access to that site

## Install

```bash
composer require matomo/matomo-php-tracker
bin/magento module:enable Artimus_Matomo
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configure

In **Stores > Configuration > Sales > Matomo Analytics** (default, website or store view scope):

| Setting | Value |
|---|---|
| Enable | Yes |
| Tracker URL | Matomo's URL as the browser sees it, e.g. `https://analytics.example.com/` |
| Server-Side Tracker URL | Optional. Matomo's URL as the Magento server sees it. Falls back to the Tracker URL |
| Site ID | The Matomo site ID |
| Auth Token | The token above (stored encrypted) |
| Enable User ID Tracking | Yes (default) to link logged-in customers by a hashed ID |

Flush the cache after saving.

## Run the order sender

Orders are sent by the queue consumer `artimus.matomo.order.track`. With Magento cron running, the
consumers runner starts it, and a cron job re-sends failed orders every 10 minutes. Without cron, run
the consumer as a supervised process:

```bash
bin/magento queue:consumers:start artimus.matomo.order.track
```

## Check it works

1. Open the storefront. The browser should send a `matomo.php` request on every page.
2. Log in and browse. The requests carry `uid=<64 hex>`.
3. Place a test order. Its row in `artimus_matomo_order_tracking` goes from `pending` to `sent`, and
   the order appears in Matomo's Visits Log.

If something is off, see [Troubleshooting](docs/troubleshooting.md).

## Tests

```bash
bin/test/unit app/code/Artimus/Matomo              # PHP unit tests (in Docker)
node src/app/code/Artimus/Matomo/Test/js/run.js    # JS specs (Node 22, no npm install)
```
