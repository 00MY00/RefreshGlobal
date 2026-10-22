# FreeScout Interop — guide for the Refresh maintainer

RefreshGlobal 1.17.0 implements its side of the contract **as agreed on 2026-10-22** ([DECISIONS.md](DECISIONS.md)):
protocol 1.0.0 = navigation, ticket toolbar, swipe and navigation context; bridge in its own repository; dashboard and
SLA through public hooks; search later (UpdateGuard: only an idea for later, nothing in the contract). It waits for a testable Refresh 1.8.0 before being released.
Nothing here requires RefreshGlobal: every step keeps Refresh fully independent, and Refresh keeps working exactly as
today if you do none of them.

**Why:** today RefreshGlobal integrates with Refresh's page through documented DOM fallbacks (it places its button
next to `.rf-tb-close`, rewrites `.rf-crumb`, adds a tab to `.rf-m-tabs`, handles its own left swipe on your cards…).
Every Refresh release can break one of them, and RefreshGlobal has to follow your line numbers
(`RefreshGlobal/INTEGRATION_MAP.md`). With the contract, RefreshGlobal only declares **intents and data**; Refresh
decides how to render them, and RefreshGlobal stops touching your HTML capability by capability.

Contract reference: [../INTEROPERABILITY.md](../INTEROPERABILITY.md). Design: [ARCHITECTURE.md](ARCHITECTURE.md).

## 1. Ship the bridge (5 minutes)

The bridge has its own repository (being created; its content is staged in RefreshGlobal's `interop-bridge/` folder).
Copy `fs-interop.js` of a **release tag** unchanged into Refresh (e.g. `Public/js/fs-interop.js`) and load it first in
your `javascripts` filter; FreeScout installs modules one by one, so each module ships the file — never as a module to
install. It is plain ES5, has no dependency and is idempotent: if RefreshGlobal's copy loaded first, yours keeps its
registries; loading order never matters.

To keep the copies identical, record the release next to it (`fs-interop.lock.json`: `version`, `protocol`, `sha256`)
and let your CI compare the SHA-256 and run the bridge's tests on your copy
(`FS_INTEROP_FILE=Public/js/fs-interop.js node tests/fs-interop.test.js`). Never edit the copy: change the shared
repository and release a new version.

## 2. Register Refresh while your script loads

At script evaluation time (not in `$(function () {…})`, because other modules decide on DOM ready):

```javascript
FreeScoutInterop.modules.register({
    id: 'refresh',
    version: '1.8.0',            // Refresh's version (e.g. printed into the page by your provider)
    protocol: '1.0.0',
    capabilities: {
        // declare ONLY what you implement (steps 4–8); RefreshGlobal keeps its fallback for the others
        'navigation': '1.0',
        'navigation-context': '1.0',
        'ticket-toolbar': '1.0',
        'swipe-actions': '1.0',
        'conversation-ui': '1.0',
        'dashboard-handoff': '1.0'   // step 8
    }
});
FreeScoutInterop.surfaces.register({ module: 'refresh', surface: 'ticket-list', context: 'mailbox', version: '1.0' });
```

Capabilities can be adopted one at a time: declaring `ticket-toolbar` alone already stops RefreshGlobal's toolbar
fallbacks, the others keep working as today.

## 3. Add `interop.json` next to your `module.json`

The server side of RefreshGlobal reads it (diagnostics, health check).
A complete proposal is in [examples/refresh.interop.json](examples/refresh.interop.json). Without it, RefreshGlobal
still detects your browser registration, but its server side reports “legacy”.

## 4. `navigation` — render navigation intents

```javascript
FreeScoutInterop.navigation.list().forEach(function (entry) {
    // entry: {id, owner, label, href, icon (SVG URL), placements[], position: {after}, active, replaces?}
    // place it in your rail / phone tab bar / drawer according to entry.placements and entry.position;
    // if entry.replaces is set, hide your own entry entry.replaces.id in entry.replaces.placements
});
FreeScoutInterop.events.on('navigation:registered', function () { /* re-render if needed */ });
```

RefreshGlobal registers `refreshglobal.all-mailboxes` (`position.after: "tickets"`, `replaces` only when the
administrator chose “replace Refresh's Tickets entry”). It also keeps adding its rail link through your public
`refresh.rail_items` filter on the server side, which stays valid.

## 5. `navigation-context` — show where a ticket was opened from

On a ticket page:

```javascript
var origin = FreeScoutInterop.context.getConversationOrigin(conversationId);
if (origin) {
    // origin.origin = {id, label (translated), href, count}: use it as the first breadcrumb item, the target of the
    // phone back arrow, and (optionally) a "← label (count)" landmark at the top of the views panel
}
```

RefreshGlobal sets it when a ticket is opened from “All mailboxes” (and keeps it across FreeScout's redirects to the
next ticket). Schema: `RefreshGlobal/docs/interop/schemas/conversation-origin-1.0.schema.json`.

## 6. `ticket-toolbar` — render toolbar actions

```javascript
FreeScoutInterop.toolbar.list(function (a) {
    return a.contexts.indexOf(isDeleted ? 'conversation-deleted' : 'conversation') >= 0;
}).forEach(function (action) {
    // action: {id, owner, label, shortLabel?, icon, position: {after: "close"}, order}
    // desktop: a button in your toolbar; phone: an entry of your "Ticket actions" sheet
    button.onclick = function () {
        FreeScoutInterop.toolbar.run(action.id, { conversationId: id, mailboxId: mailboxId, userId: userId, mobile: isPhone });
    };
});
```

RefreshGlobal registers `refreshglobal.move-to-folder` (opens its own dialog) and, on a ticket in the trash,
`refreshglobal.delete-forever` (clicks FreeScout's own button — your sheet had no deletion in that case).

## 7. `swipe-actions` — offer swipe actions

Only the owner of the list handles raw touch events. When a card is swiped, offer the actions registered for that
direction (RefreshGlobal: `refreshglobal.move-to-folder`, direction `left`) next to your own right-swipe panel:

```javascript
FreeScoutInterop.swipe.list(function (a) { return a.direction === 'left'; })
    .forEach(function (a) { /* button → FreeScoutInterop.swipe.run(a.id, {conversationId: id}) */ });
```

## 8. Dashboard and SLA through public hooks (proposal, to confirm)

As agreed, neither module reads the other's dashboard HTML or settings class. Names and payloads below are
RefreshGlobal's proposal (schemas in `RefreshGlobal/docs/interop/schemas/`); tell us if you prefer others.

* **SLA targets** — answer the filter `fsinterop.sla.targets` (schema `sla-targets` 1.0); RefreshGlobal uses it for
  “Overdue”, “Due: today” and “Resolved within SLA”, so both modules count the same tickets:

  ```php
  \Eventy::addFilter('fsinterop.sla.targets', function ($targets, $context = []) {
      return $targets ?: ['schema' => '1.0', 'source_module' => 'refresh',
          'first_response_hours' => Settings::firstResponseHours(), 'resolution_hours' => Settings::resolutionHours()];
  }, 20, 2);
  ```

* **Dashboard handoff** — before rendering your “My dashboard” in `dashboard.before`, ask who owns the page's dashboard
  (schema `dashboard-owner` 1.0), and declare the capability `"dashboard-handoff": "1.0"` (registration and
  `interop.json`):

  ```php
  $owner = \Eventy::filter('fsinterop.dashboard.owner', null, ['schema' => '1.0', 'page' => 'dashboard', 'user_id' => $user->id]);
  if ($owner && $owner !== 'refresh') {
      return $html; // RefreshGlobal renders the dashboard of all mailboxes (only when its setting is on)
  }
  ```

  RefreshGlobal then adds its dashboard instead of replacing your block, and announces it in the browser
  (`refreshglobal:dashboard-rendered`). Until then it keeps its legacy replacement of the `rf-dash` block and its
  reading of `Settings`, for Refresh ≤ 1.7 only.

## 9. Optional: server contexts

* **Who renders what** — on the server, `\Eventy::addFilter('fsinterop.navigation.context', …)` lets Refresh claim
  its surfaces (`"ticket-list@mailbox" => "refresh"`, `"dashboard@mailbox" => "refresh"`…); RefreshGlobal only
  claims `…@global-mailboxes`.
* **Ticket data** — `\Eventy::filter('fsinterop.conversation.context', $base, $conversation, $user)` gives
  `modules['refresh-global']` (“All mailboxes” link, origin tags, actions usable on the ticket) for a user who may view
  it; Refresh can add its own entry `modules['refresh']`.

Search is not part of the contract yet (agreed: later); RefreshGlobal keeps it as its own extension
(`GET /refresh-global/search`, `RefreshGlobalInterop.search()`).

## 10. Check it

* Diagnostics page of RefreshGlobal (**Interoperability**): mode **Native**, compatibility **COMPATIBLE**, every
  capability **Native**, legacy DOM fallbacks **Off**.
* Browser console: `RefreshGlobalInterop.status()`.
* RefreshGlobal's browser scenario `interop.js` simulates exactly this registration (legacy and native) and checks that
  RefreshGlobal no longer changes Refresh's page in native mode.

## 11. What does not change

* Refresh's own dashboard, lists, views, settings and API stay yours; RefreshGlobal never calls private Refresh classes
  for these capabilities.
* Since 1.17.0 RefreshGlobal no longer calls `Views::query` or `Dashboard::chart / delta / duration`: its dashboard and
  quick views are its own (same meaning as your views of the same name). Its reading of
  `Settings::firstResponseHours / resolutionHours` and its replacement of the `rf-dash` block remain only as legacy
  fallbacks for a Refresh without the contract, and go away once you answer the two hooks of step 8.
* `refresh.rail_items` (documented in your README) stays as it is; it is listed in `RefreshGlobal/INTEGRATION_MAP.md`
  and checked by `refreshglobal:check`.
* If you change a capability you declared, bump its version; a breaking change bumps the protocol major — RefreshGlobal
  then falls back to legacy / standalone by itself instead of breaking.
