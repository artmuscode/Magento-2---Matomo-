# Configuration

## Admin settings

**Stores > Configuration > Sales > Matomo Analytics > General.** Every field can be set at default, website or store-view scope. The ACL resource is `Artimus_Matomo::config`.

| Field | Path | Default | Notes |
|---|---|---|---|
| Enable | `artimus_matomo/general/enabled` | No | Master switch |
| Tracker URL | `artimus_matomo/general/tracker_url` | (empty) | Matomo's base URL as the **browser** sees it. A trailing slash is added if missing. Use HTTPS on an HTTPS store, or browsers block `matomo.js` as mixed content |
| Server-Side Tracker URL | `artimus_matomo/general/server_tracker_url` | (empty) | Matomo's base URL as the **Magento server** sees it, e.g. an internal hostname such as `http://matomo/`. Empty means the Tracker URL is used |
| Site ID | `artimus_matomo/general/site_id` | (empty) | The numeric Matomo site ID |
| Auth Token | `artimus_matomo/general/auth_token` | (empty) | Stored encrypted and marked *sensitive*, so `app:config:dump` writes it to `env.php`, not `config.php` |
| Enable User ID Tracking | `artimus_matomo/general/user_id_enabled` | Yes | Sends a hashed customer ID for logged-in customers |

Tracking is **active** for a store view only when Enable is Yes, the Tracker URL isn't empty, and the Site ID is greater than 0. When tracking isn't active:

- no tracker markup is rendered
- no CSP entries are added
- placed orders aren't recorded

Flush the cache after changing settings, because the tracker config is part of full-page-cached HTML.

Settings from the CLI:

```bash
bin/magento config:set artimus_matomo/general/enabled 1
bin/magento config:set artimus_matomo/general/tracker_url https://analytics.example.com/
bin/magento config:set artimus_matomo/general/site_id 1
bin/magento config:sensitive:set artimus_matomo/general/auth_token <token>
bin/magento cache:flush
```

## Matomo-side setup

1. **Create a website** for the store and note its site ID.
2. **Enable Ecommerce** for the site. Without it Matomo drops cart updates and orders.
3. **Set the site currency** to the store's currency. The module sends amounts as numbers without a currency code.
4. **Create an auth token** (Administration > Personal > Security) for a user with **write** or **admin** access to the site. Matomo accepts the forced visitor ID, IP and visit time on order requests only from such a token. With a view-only token, every order fails.
5. **Leave `enable_userid_overwrites_visitorid` at its default (`1`).** The module is built for Matomo's default User ID behaviour (see [Browser tracking](browser-tracking.md#user-id)), and nothing on the server needs changing.

## Cookie consent

The module follows Magento's **Cookie Restriction Mode**: Stores > Configuration > General > Web > Default Cookie Settings.

- **Off:** Matomo tracks normally.
- **On:** the browser tracker starts with `requireConsent`, so nothing is sent until the visitor accepts the cookie notice (Magento's `user_allowed_save_cookie` cookie). Orders from visitors who never accepted are recorded with status `skipped` and never sent.

## Queue and cron

The order queue uses Magento's database queue (`db` connection, `magento-db` exchange). It doesn't need RabbitMQ.

- **Consumer:** `artimus.matomo.order.track`. With cron running and `cron_consumers_runner` enabled, which is Magento's default, cron starts it. Otherwise run `bin/magento queue:consumers:start artimus.matomo.order.track` under a process supervisor.
- **Cron job:** `artimus_matomo_resend_failed_order_tracking` in group `default`, every 10 minutes.

## Content Security Policy

When tracking is active, `Model\Csp\MatomoPolicyCollector` adds the Tracker URL's host to `script-src`, `connect-src` and `img-src`. It includes the port when that isn't the scheme's default. No `csp_whitelist.xml` is needed.
