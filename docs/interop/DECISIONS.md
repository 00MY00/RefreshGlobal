# FreeScout Interop — decisions agreed with Refresh's maintainer

Record of what the maintainers of Refresh and RefreshGlobal agreed, so that both sides implement the same contract.
The design document is [ARCHITECTURE.md](ARCHITECTURE.md); RefreshGlobal's implementation is
[../INTEROPERABILITY.md](../INTEROPERABILITY.md).

## 2026-10-22 — answer to the architecture proposal

| # | Topic | Decision | Status on RefreshGlobal's branch `feature/interop-1.0` |
|---|---|---|---|
| 1 | Principle | Two independent modules, each working alone, talking through a versioned contract when both are installed. | Done: RefreshGlobal works with Refresh disabled (dashboard, views, filters, list), tested. |
| 2 | Core of protocol 1.0.0 | **Validated**: navigation registry, ticket toolbar actions, swipe registry, navigation context (origin of a ticket opened from RefreshGlobal). Refresh implements its side. | Done on RefreshGlobal's side; waiting for Refresh 1.8.0 to test native mode. |
| 3 | Bridge `fs-interop.js` | Lives in **its own repository**, used by both modules, instead of one copy each (copies would diverge). | Prepared: content of the future repository staged in `interop-bridge/` (file, tests, CI, README, changelog); RefreshGlobal ships an unchanged copy recorded in `Public/js/fs-interop.lock.json` (SHA-256), checked by its CI. Waiting for the repository to be created. |
| 4 | Dashboard and SLA | Through **public hooks** (Eventy filters on the server, events in the browser), never through Refresh's HTML or its `Settings` class (internal, may change). | Prepared: `fsinterop.sla.targets` (asked by RefreshGlobal), `fsinterop.dashboard.owner` (answered by RefreshGlobal), browser event `refreshglobal:dashboard-rendered`, capability `dashboard-handoff` consumed. The current reading of Refresh's `Settings` class and the replacement of its `rf-dash` block stay as **legacy** fallbacks for Refresh ≤ 1.7, removed once Refresh answers the hooks. To be confirmed by Refresh's maintainer (names and payloads below). |
| 5 | Search | **Later**, once the core is in place. | Search is no longer in the shared bridge (protocol back to **1.0.0**). RefreshGlobal keeps it as its own extension (`GET /refresh-global/search`, `RefreshGlobalInterop.search()`, filters marked `"extension": true`), to be proposed for the contract later. |
| 6 | UpdateGuard | **Later**, only an idea for now: the machine-readable declaration could one day carry what UpdateGuard needs (compatible versions, contract, health check, SHA-256). | Nothing for UpdateGuard in the contract: the `updateguard` section was removed from `interop.json` and `interop.json` is no longer published as a release asset. RefreshGlobal's existing UpdateGuard support (since 1.9.0) is unchanged. |
| 7 | Calendar | Refresh 1.7.0 shipped collision detection; the contract comes in **Refresh 1.8.0**. RefreshGlobal continues on `feature/interop-1.0`. | Branch kept; nothing published, nothing merged into `main` until a first testable Refresh 1.8.0. |

## Proposed hooks for decision 4 (to confirm)

```php
// SLA targets — Refresh answers, RefreshGlobal asks (schema sla-targets 1.0)
\Eventy::addFilter('fsinterop.sla.targets', function ($targets, $context = []) {
    return $targets ?: ['schema' => '1.0', 'source_module' => 'refresh',
        'first_response_hours' => 24, 'resolution_hours' => 72];
}, 20, 2);

// Dashboard handoff — RefreshGlobal answers, Refresh asks before rendering its own block (schema dashboard-owner 1.0)
$owner = \Eventy::filter('fsinterop.dashboard.owner', null, ['schema' => '1.0', 'page' => 'dashboard', 'user_id' => $user->id]);
if ($owner && $owner !== 'refresh') {
    return $html; // another module renders the dashboard of this page
}
```

Refresh declares the capability `"dashboard-handoff": "1.0"` (browser registration and `interop.json`) when it asks
`fsinterop.dashboard.owner`; RefreshGlobal then adds its dashboard instead of replacing Refresh's HTML. In the browser,
RefreshGlobal emits `refreshglobal:dashboard-rendered` `{schema, source_module, scope, placement}`.

## Open points

* Owner, name and licence of the bridge repository (proposal: a neutral repository `fs-interop`, AGPL-3.0 like both
  modules, tags `vX.Y.Z` = `@bridge-version`).
* Exact names and payloads of the two hooks above.
