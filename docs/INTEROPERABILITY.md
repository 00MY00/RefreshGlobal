# FreeScout Interop — RefreshGlobal implementation (protocol 1.0.0)

RefreshGlobal and Refresh are **sibling** FreeScout modules: neither owns or extends the other, each works without
the other. When both are installed they cooperate through a small, versioned, declared contract. This document
describes that contract **as implemented by RefreshGlobal**; the design it comes from is
[docs/interop/ARCHITECTURE.md](interop/ARCHITECTURE.md). For Refresh's maintainer, the steps to cooperate natively
are in [docs/interop/REFRESH_MAINTAINER_GUIDE.md](interop/REFRESH_MAINTAINER_GUIDE.md).

> **Rule:** no undocumented cross-module integration. Anything one module offers to or uses from another is declared
> in `interop.json`, versioned, tested and documented — in the same commit as the code.

## 1. Files

| File | Role |
|---|---|
| `RefreshGlobal/interop.json` | **single source of truth**: what RefreshGlobal provides and consumes, compatibility, UpdateGuard metadata |
| `RefreshGlobal/Public/js/fs-interop.js` | the neutral browser bridge `window.FreeScoutInterop` (no dependency, idempotent; any module may ship the same file) |
| `RefreshGlobal/Public/js/interop-adapter.js` | RefreshGlobal's participation: registration, surfaces, intents, conversation origin, events, negotiation |
| `RefreshGlobal/Services/Interop/` | server side: `Manifest` (read / validate), `SemVer` (ranges), `PeerStatus` (cooperation mode), `ServerHooks` (`fsinterop.*` hooks) |
| `RefreshGlobal/docs/interop/schemas/*.schema.json` | JSON schemas of every payload (shipped with the module) |
| `tools/validate-interop.php`, `tests/interop/fs-interop.test.js` | CI job **interop-contract** |
| `RefreshGlobal/Tests/Feature/InteropTest.php` | PHPUnit: manifest, ranges, modes, server hooks, what pages give to the browser |

## 2. Cooperation modes

`Services/Interop/PeerStatus.php` decides, from the declared contracts only, how RefreshGlobal cooperates with Refresh
on an installation (shown in **RefreshGlobal › Diagnostics › Interoperability** and in `refreshglobal:health --json`):

| Situation | Mode | Compatibility |
|---|---|---|
| Refresh not installed / not active | `standalone` | `COMPATIBLE` (the peer is optional) |
| Refresh provides `interop.json` with a protocol in `>=1.0.0 <2.0.0` and every consumed capability | `native` | `COMPATIBLE` |
| …but some capabilities are missing | `native` (missing ones: `legacy` if the version is in the tested legacy range, else `unavailable`) | `DEGRADED` |
| No contract, Refresh version in the tested legacy range (`>=1.4.0 <1.7.0`, = `Config/integration.php`) | `legacy` (documented DOM fallbacks) | `DEGRADED` |
| No contract, untested version | `legacy` within `supported_versions`, else `unavailable` | `UNKNOWN` (policy `unknown_peer_version: warning`) |
| Protocol of another major | `unavailable` | `DEGRADED` (policy `peer_protocol_mismatch: degraded`) |
| Version listed in `compatibility.peers.refresh.conflicts` | `unavailable` | `INCOMPATIBLE` (policy `explicit_conflict: blocked`) |

In the browser, the same decision is made capability by capability: `RefreshGlobalInterop.native(capability)` is true
only when Refresh has registered with a compatible protocol **and** provides the capability in the declared range.
Console: `RefreshGlobalInterop.status()`, `RefreshGlobalInterop.mode()`.

### Legacy fallbacks (what RefreshGlobal still does itself when Refresh does not cooperate natively)

| Capability consumed | Native: Refresh does it from the contract | Legacy: `Public/js/shell.js` does it on Refresh's page | Checks |
|---|---|---|---|
| `navigation` | places the `refreshglobal.all-mailboxes` navigation intent (rail, phone tab bar, drawer, `replaces`) | rail link order, phone tab, hides Refresh's “Tickets” if the setting says so | RG-HOOK-13, 14 |
| `navigation-context` | shows the conversation origin (breadcrumb, back arrow, “← All mailboxes (N)” landmark) | rewrites the breadcrumb / back arrow, adds the landmark | RG-HOOK-25…27 |
| `ticket-toolbar` | renders the toolbar intents (`refreshglobal.move-to-folder`, `refreshglobal.delete-forever`) desktop and phone | button next to “Close”, entries in the phone “Ticket actions” sheet | RG-HOOK-23, 40 |
| `swipe-actions` | offers the swipe intents (`refreshglobal.move-to-folder`, direction `left`) in its gesture handling | its own left-swipe handler on the cards | — |
| `conversation-ui` | reserved (ticket page enhancements) | — | — |

Server-side integrations through **public** Eventy hooks of FreeScout / Refresh (e.g. `dashboard.before`,
`refresh.rail_items`, `conversations_table.before_subject`, `thread.body_output`) are not DOM coupling and stay as
they are; they are listed with `file:line` in `RefreshGlobal/INTEGRATION_MAP.md` and checked by
`php artisan refreshglobal:check`.

## 3. Browser API — `window.FreeScoutInterop`

Loaded by FreeScout's `javascripts` filter on every page, in this order: `fs-interop.js`, `interop-adapter.js`,
`shell.js`. Capability ids are **kebab-case**, exactly as in `interop.json`.

```javascript
FreeScoutInterop.protocol                       // "1.0.0"
FreeScoutInterop.modules.register({id, version, protocol, capabilities})
FreeScoutInterop.modules.get(id) / .list() / .when(id, fn)     // when(): now, or when the module registers
FreeScoutInterop.supports(moduleId, capability, range)         // false = use your own fallback
FreeScoutInterop.events.on(name, fn) / .once / .off / .emit(name, payload)
FreeScoutInterop.context.setConversationOrigin(origin) / .getConversationOrigin(conversationId) / .clearConversationOrigin()
FreeScoutInterop.surfaces.register({module, surface, context, version}) / .owner(surface, context)
FreeScoutInterop.navigation | .toolbar | .swipe  .register(item) / .get(id) / .list(filter) / .run(id, context)
FreeScoutInterop.semver.satisfies(version, range) / .compare(a, b)
```

Guarantees: loading order does not matter (`modules.when()`), a second copy of the bridge keeps the first one's
registries (a newer copy of the same major only adds missing members), listeners and handlers that throw are isolated,
payloads are copied (no shared objects), unknown range syntax never matches. Ranges: `">=1.0 <2.0"`, `"^1.2"`,
`"=1.2.0"`, `"1.2"`, `"*"`.

**Register while your script loads** (not in a DOM-ready handler): other modules decide on DOM ready.

## 4. What RefreshGlobal provides

### Capabilities (`interop.provides.capabilities`)

| Id | Version | Meaning |
|---|---|---|
| `global-ticket-list` | 1.0 | the “All mailboxes” list (`/refresh-global/tickets`) |
| `global-dashboard` | 1.1 | the all-mailboxes dashboard (and `fsinterop.dashboard.data` for scope `global-mailboxes`) |
| `saved-views` / `shared-views` | 1.0 | saved views, private / shared / for everyone (export format `saved-view` 2.0) |
| `navigation-origin` | 1.0 | gives the origin of the tickets opened from its list (`context.setConversationOrigin`) |
| `mail-tags` | 1.0 | origin tags of the e-mails (server hooks, `docs/api-tags.md`) |
| `mail-move` | 1.0 | moving the e-mails of a ticket to a folder of the mail server (`refreshglobal.mail_moved`) |

### Surfaces (`interop.provides.surfaces`)

`ticket-list@global-mailboxes`, `dashboard@global-mailboxes` (1.1), `saved-views@global-mailboxes`. On
`/refresh-global/tickets` RefreshGlobal owns the list; on `/mailbox/N/…` Refresh does.

### Intents (`interop.provides.registrations`)

| Registry | Id | Content (schema) |
|---|---|---|
| navigation | `refreshglobal.all-mailboxes` | label (translated), href, icon (SVG URL), placements `desktop-sidebar` / `mobile-drawer` / `mobile-bottom-bar`, `position.after: "tickets"`, `active`, `replaces` when the administrator chose to replace Refresh's “Tickets” (`navigation-entry` 1.0) |
| toolbar | `refreshglobal.move-to-folder` | “Move to a folder”, icon `folder`, context `conversation`, `position.after: "close"`; handler opens RefreshGlobal's own dialog (`toolbar-action` 1.0) |
| toolbar | `refreshglobal.delete-forever` | only on a ticket in the trash, context `conversation-deleted`; handler clicks FreeScout's own button (its confirmation and rights) |
| swipe | `refreshglobal.move-to-folder` | direction `left`, context `ticket-list` (`swipe-action` 1.0) |

Handlers receive a plain context: `{conversationId, mailboxId, userId, mobile}`.

### Conversation origin (`interop.provides.context`)

When a ticket of the “All mailboxes” list is opened (click or tap, captured before navigation), RefreshGlobal calls
`FreeScoutInterop.context.setConversationOrigin()` with (`conversation-origin` 1.0):

```json
{
  "schema": "1.0",
  "sourceModule": "refresh-global",
  "conversationId": 1523,
  "mailboxId": null,
  "origin": { "id": "refreshglobal.all-mailboxes", "label": "All mailboxes", "href": "https://…/refresh-global/tickets?status%5B0%5D=1#rg-back=1523", "count": 42 }
}
```

`origin.id` is `refreshglobal.view.<id>` for a saved view; the label is already translated; `href` reopens the list at
that ticket. The value is kept for the browser tab (sessionStorage). RefreshGlobal also keeps the way back across
FreeScout's own redirects (next ticket after “Close”), on the server side, in every mode.

### Events (`interop.provides.events`)

| Event | Payload (schema) |
|---|---|
| `refreshglobal:view-changed` | `{schema, source_module, view_id, modified, href, label, count}` when the list is shown (`view-changed` 1.0) |
| `refreshglobal:conversation-opened` | `{schema, source_module, conversation_id, origin}` (`conversation-origin` 1.0) |

The bridge itself emits `interop:ready`, `module:registered`, `context:changed`, `surface:registered`,
`<registry>:registered`.

### Server hooks (`interop.provides.server_hooks`)

```php
// every participating module adds its summary
$modules = \Eventy::filter('fsinterop.modules', []);                 // ['refresh-global' => ['version', 'protocol', 'capabilities', …]]

// figures of a dashboard scope (schema dashboard-data 1.1); RefreshGlobal answers "global-mailboxes"
$data = \Eventy::filter('fsinterop.dashboard.data', null, ['scope' => 'global-mailboxes', 'user_id' => $userId]);

// narrow the mailboxes of the all-mailboxes dashboard (never widen: intersected with the user's rights)
\Eventy::addFilter('fsinterop.dashboard.context', function ($context) { /* $context['mailbox_ids'] */ return $context; });
```

Note: Eventy passes a `null` value on as `''`; listeners treat any empty value as “not answered yet”.
The existing public hooks `refreshglobal.mail_tags`, `refreshglobal.thread_mail_tags`, `refreshglobal.mail_tagged`,
`refreshglobal.mail_moved` ([docs/api-tags.md](api-tags.md)) are declared in the same manifest.

## 5. What RefreshGlobal consumes from Refresh (optional)

```json
"refresh": {
  "protocol": ">=1.0.0 <2.0.0",
  "capabilities": {
    "navigation": ">=1.0 <2.0", "navigation-context": ">=1.0 <2.0", "ticket-toolbar": ">=1.0 <2.0",
    "swipe-actions": ">=1.0 <2.0", "conversation-ui": ">=1.0 <2.0"
  }
}
```

Nothing is required: without any of them RefreshGlobal uses its legacy fallback or works alone.

## 6. Compatibility and UpdateGuard

`interop.json` declares `compatibility` (FreeScout `>=1.8.0 <1.9.0`, PHP `>=7.1.0`, Refresh optional with
`supported_versions`, `interop_protocol`, `fallback` = the tested legacy range, `conflicts`), a
`compatibility_policy`, and an `updateguard` section (package, SHA-256 integrity, health command and exit codes).
Each release publishes `interop.json` next to `RefreshGlobal.zip` and lists it in `SHA256SUMS`, so UpdateGuard can
evaluate the **target** version before installing it.

Health check — `php artisan refreshglobal:health --json` (schema `health` 1.0): exit code **0** healthy, **1**
unhealthy, **2** the check itself failed; read-only, no network. Checks: files, classes, views, routes, hooks,
assets, database, `interop_manifest`, page; plus `interop.refresh.{mode, compatibility}` for information.

## 7. Changing the contract

1. Design the integration point; prefer a public channel (FreeScout / Eventy hook, then this contract, then a
   documented adapter; DOM only as a legacy fallback).
2. Update `interop.json` (and its schema file) **in the same commit**.
3. Implement; the browser side registers in `Public/js/interop-adapter.js` with literal ids (the validator reads them).
4. Tests: `InteropTest`, `fs-interop.test.js`, browser scenario.
5. Docs: this file, `INTEGRATION_MAP.md` for any element of another module, both changelogs.

`php tools/validate-interop.php` fails when an event emitted or an intent registered by the scripts is not declared
(or the other way round), when a declared server hook is not used, when the version ranges differ from
`Config/integration.php`, or when the health command differs from `module.json`.

**Breaking changes** (event name or payload meaning, required field, capability behaviour, schema, health command or
exit codes, checksum location, compatibility meaning) need a protocol **major** bump, migration notes and cross-module
tests. A capability or event to be removed is first marked `"status": "deprecated"` with a `replacement` and the
protocol version that removes it.
