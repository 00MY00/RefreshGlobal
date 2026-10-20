# FreeScout Interop — guide for the Refresh maintainer

This is a **proposal**: RefreshGlobal 1.17.0 implements its side of the contract and is waiting for your review before
it is released. Nothing here requires RefreshGlobal: every step keeps Refresh fully independent, and Refresh keeps
working exactly as today if you do none of them.

**Why:** today RefreshGlobal integrates with Refresh's page through documented DOM fallbacks (it places its button
next to `.rf-tb-close`, rewrites `.rf-crumb`, adds a tab to `.rf-m-tabs`, handles its own left swipe on your cards…).
Every Refresh release can break one of them, and RefreshGlobal has to follow your line numbers
(`RefreshGlobal/INTEGRATION_MAP.md`). With the contract, RefreshGlobal only declares **intents and data**; Refresh
decides how to render them, and RefreshGlobal stops touching your HTML capability by capability.

Contract reference: [../INTEROPERABILITY.md](../INTEROPERABILITY.md). Design: [ARCHITECTURE.md](ARCHITECTURE.md).

## 1. Ship the bridge (5 minutes)

Copy `RefreshGlobal/Public/js/fs-interop.js` **unchanged** into Refresh (e.g. `Public/js/fs-interop.js`) and load it
first in your `javascripts` filter. It is plain ES5, has no dependency and is idempotent: if RefreshGlobal's copy
loaded first, yours keeps its registries; loading order never matters.

## 2. Register Refresh while your script loads

At script evaluation time (not in `$(function () {…})`, because other modules decide on DOM ready):

```javascript
FreeScoutInterop.modules.register({
    id: 'refresh',
    version: '1.8.0',            // Refresh's version (e.g. printed into the page by your provider)
    protocol: '1.0.0',
    capabilities: {
        // declare ONLY what you implement (steps 4–7); RefreshGlobal keeps its fallback for the others
        'navigation': '1.0',
        'navigation-context': '1.0',
        'ticket-toolbar': '1.0',
        'swipe-actions': '1.0',
        'conversation-ui': '1.0'
    }
});
FreeScoutInterop.surfaces.register({ module: 'refresh', surface: 'ticket-list', context: 'mailbox', version: '1.0' });
```

Capabilities can be adopted one at a time: declaring `ticket-toolbar` alone already stops RefreshGlobal's toolbar
fallbacks, the others keep working as today.

## 3. Add `interop.json` next to your `module.json`

The server side of RefreshGlobal reads it (diagnostics, health check) and UpdateGuard can use it before an update.
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

## 8. Check it

* Diagnostics page of RefreshGlobal (**Interoperability**): mode **Native**, compatibility **COMPATIBLE**, every
  capability **Native**, legacy DOM fallbacks **Off**.
* Browser console: `RefreshGlobalInterop.status()`.
* RefreshGlobal's browser scenario `interop.js` simulates exactly this registration (legacy and native) and checks that
  RefreshGlobal no longer changes Refresh's page in native mode.

## 9. What does not change

* Refresh's own dashboard, lists, views, settings and API stay yours; RefreshGlobal never calls private Refresh classes
  for these capabilities.
* The public server hooks of Refresh used by RefreshGlobal (`refresh.rail_items`, `dashboard.before` replacement,
  `Views::query`, `Dashboard::chart`…) are listed in `RefreshGlobal/INTEGRATION_MAP.md` and checked by
  `refreshglobal:check`; a future protocol version could turn them into contract capabilities too.
* If you change a capability you declared, bump its version; a breaking change bumps the protocol major — RefreshGlobal
  then falls back to legacy / standalone by itself instead of breaking.
