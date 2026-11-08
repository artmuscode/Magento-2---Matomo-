# Development

## Tests

### PHP (PHPUnit, in Docker)

```bash
bin/test/unit app/code/Artimus/Matomo
bin/test/unit-coverage app/code/Artimus/Matomo
bin/phpcs app/code/Artimus/Matomo
bin/analyse app/code/Artimus/Matomo
```

Tests live in `Test/Unit/` and mirror the source tree. Config XML files have their own tests in `Test/Unit/Etc/`, which check events, sections and module sequence.

### JavaScript (Node runner, on the host)

The module's RequireJS modules are tested with a small, dependency-free runner (Node 22) committed at `Test/js/run.js`:

```bash
node src/app/code/Artimus/Matomo/Test/js/run.js              # every *.test.js under Test/js
node src/app/code/Artimus/Matomo/Test/js/run.js paq          # files whose path contains "paq"
node src/app/code/Artimus/Matomo/Test/js/run.js src/app/code/Artimus/Matomo/Test/js/tracker.test.js
```

- **Layout:** specs mirror `view/frontend/web/js/` (`Test/js/model/paq.test.js` tests `model/paq.js`).
- **Module IDs:** `Artimus_Matomo/js/*` resolves to `view/frontend/web/js/*.js`.
- **Jasmine subset:** `describe`, `it` (with `done` for async), `beforeEach` and `afterEach`. It has the common matchers, `jasmine.createSpy`, `spyOn` and `jasmine.clock()`. Spies made with `spyOn` and the fake clock are reset after each spec.
- **`amd.load(id, stubs)`:** returns a freshly evaluated module, with any other module IDs replaced by stubs. Use it for modules with module-level state, such as the `paq` singleton or the tracker's run-once guard. An unstubbed non-Artimus dependency like `jquery` throws an error naming it.
- **Globals:** `window` is `globalThis`. Specs that need `document` or `localStorage` install a fake.
- **Exit code and fixtures:** the runner exits non-zero on any failure. Its own self-test fixtures live in `Test/js/_runner-fixtures/` and aren't discovered.

## Local Matomo

This project's Docker setup includes an opt-in Matomo at `https://magento.test:8443` (see `docs/matomo-local.md` at the repository root):

```bash
docker compose -f compose.yaml -f compose.matomo.yaml up -d matomodb matomo matomo-tls
```

`bin/start` removes these containers, so start them again afterwards. Use:

- Tracker URL `https://magento.test:8443/`
- Server-Side Tracker URL `http://matomo/`
- Site ID `1`

Inspect what Matomo recorded directly:

```bash
docker exec magento-matomodb-1 mariadb -umatomo -pmatomo matomo -e \
  "SELECT idvisit, HEX(idvisitor), LEFT(user_id,8), visit_first_action_time, visit_total_actions
   FROM matomo_log_visit ORDER BY idvisit DESC LIMIT 10;"
```

## Extending

### Add page-type data

1. Implement `Api\PageContextProviderInterface`:
   - `getCommands()` returns `_paq` command arrays for the current request, or `[]`.
   - `replacesPageView()` returns true only if your commands send their own page view.
2. Register it in `etc/frontend/di.xml` under `Artimus\Matomo\Model\PageContextPool` → `providers`.
3. Use only data that's safe in full-page-cached HTML: no customer, session or cookie data.

### Add a browser component

1. Write an AMD component that sends **only** through `Artimus_Matomo/js/model/paq` (`paq.push`). Put the logic in a pure `model/` module so it can be unit-tested with the Node runner.
2. If it needs to start after the tracker bootstrap, wrap its start-up in `paq.whenReady(fn)`.
3. Register it in `etc/frontend/di.xml` as a `jsComponents` item on `Artimus\Matomo\ViewModel\TrackerConfig`. It's then initialised on every page where tracking is active.

### Track orders from another area

Register `Artimus\Matomo\Observer\CaptureOrderForTracking` on `checkout_submit_all_after` in that area's `events.xml`.

### Replace the order sender

`Api\OrderTrackerInterface::track(OrderInterface $order, OrderTrackingInterface $record)` is the single seam between the queue and Matomo. Override it with a DI preference. It must throw `Exception\TrackingFailedException` on failure so the consumer records the row as `failed` and retries.
