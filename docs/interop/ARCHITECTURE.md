# FreeScout Module Interoperability Contract
## Refresh, RefreshGlobal and UpdateGuard

**Status:** Proposed architecture  
**Purpose:** Keep `Refresh` and `RefreshGlobal` independent, while allowing them to cooperate through stable, versioned and machine-readable contracts.  
**Primary consumers:** Human maintainers, AI coding agents, CI pipelines, and UpdateGuard.

---

## 1. Goals

This architecture is based on one fundamental rule:

> **Refresh and RefreshGlobal are sibling FreeScout modules. Neither module owns or extends the other.**

Both modules must remain usable when the other one is absent.

When both modules are installed, they may cooperate through a small, explicit, versioned interoperability contract.

The contract must:

- avoid direct dependency on another module's private DOM structure;
- avoid direct calls to private functions/classes of another module;
- allow both modules to evolve independently;
- make integration points obvious to maintainers and AI coding agents;
- make new integration points discoverable and reviewable;
- provide compatibility metadata that UpdateGuard can evaluate before an update;
- provide health checks after an update;
- provide package integrity information;
- allow safe fallback when a capability is absent;
- make breaking changes detectable before installation.

The expected architecture is:

```text
                         FreeScout
                             |
                +------------+------------+
                |                         |
             Refresh                 RefreshGlobal
                |                         |
                +------ FS Interop -------+
                         Contract
                             |
                    stable communication

                         UpdateGuard
                             |
                  reads compatibility,
                 hashes and health data
```

RefreshGlobal is **not**:

```text
FreeScout
   |
Refresh
   |
RefreshGlobal
```

Instead it is:

```text
FreeScout
   |
   +---- Refresh
   |
   +---- RefreshGlobal
```

with optional lateral communication.

---

# 2. Design principles

## 2.1 Independence

Refresh must work without RefreshGlobal.

RefreshGlobal must work without Refresh.

An unavailable optional peer module must not make the other module fail.

Example:

```text
Refresh installed
RefreshGlobal absent

=> Refresh works normally.
```

```text
RefreshGlobal installed
Refresh absent

=> RefreshGlobal keeps:
   - All Mailboxes
   - global dashboard
   - saved/shared views
   - filters
   - its own navigation
   - its own backend logic
```

If both are installed:

```text
Refresh + RefreshGlobal
=> optional interoperability is enabled.
```

---

## 2.2 Communicate intentions and data, not DOM implementation

The preferred rule is:

> **Share intentions and structured data, never private HTML implementation.**

Bad:

```javascript
$('.rf-m-tab-tickets').after(...)
$('.rf-tb-close').after(...)
$('.rf-crumb').text('All mailboxes')
```

Good:

```javascript
FreeScoutInterop.navigation.register(...)
FreeScoutInterop.toolbar.register(...)
FreeScoutInterop.context.setOrigin(...)
```

A module should never need to know where another module places a button in HTML.

---

## 2.3 Public channels only

Cross-module communication must use one of these channels, in this order:

1. FreeScout public API / Eventy hook;
2. FreeScout Interop contract;
3. documented compatibility adapter;
4. DOM fallback only for legacy compatibility.

A new implementation must not introduce a private dependency when a public channel exists.

---

## 2.4 Version everything that crosses a module boundary

Any data, event, capability, action or contract consumed by another module must have a version.

Example:

```text
interop protocol: 1.0.0
navigation capability: 1.0
toolbar capability: 1.0
conversation-context schema: 1.0
dashboard-data schema: 1.1
```

Module versions and protocol versions are independent.

Example:

```text
Refresh 1.8.0
Interop protocol 1.0.0

RefreshGlobal 1.20.0
Interop protocol 1.0.0
```

---

# 3. Canonical declaration file

Every participating module MUST provide a machine-readable manifest named:

```text
interop.json
```

It must be placed at the module root, next to `module.json` where possible.

Example:

```text
Modules/
└── RefreshGlobal/
    ├── module.json
    ├── interop.json
    ├── AGENTS.md
    ├── docs/
    │   └── INTEROPERABILITY.md
    └── ...
```

`interop.json` is the **single source of truth** for cross-module contracts.

Human documentation may explain it, but the machine-readable file is authoritative.

---

# 4. Why a canonical manifest is important

Without a canonical declaration, a developer or AI agent may add a new integration such as:

```text
new JavaScript event
new toolbar action
new navigation entry
new dashboard data field
new callback
new compatibility requirement
```

but forget to update the other module.

This creates hidden coupling.

With `interop.json`, every public cross-module interface must be declared.

The required development rule is:

> **If code introduces, removes, renames or changes a cross-module integration point, `interop.json` MUST be updated in the same commit.**

CI should fail if the manifest is invalid.

AI agents must be explicitly instructed to read it before modifying integration-related code.

---

# 5. Recommended `interop.json` structure

Example for RefreshGlobal:

```json
{
  "manifest_version": 1,
  "module": {
    "id": "refresh-global",
    "name": "RefreshGlobal",
    "version_source": "module.json"
  },

  "interop": {
    "protocol": "1.0.0",

    "provides": {
      "capabilities": {
        "global-ticket-list": "1.0",
        "global-dashboard": "1.0",
        "saved-views": "1.0",
        "shared-views": "1.0",
        "navigation-origin": "1.0"
      },

      "events": {
        "refreshglobal:view-changed": "1.0",
        "refreshglobal:conversation-opened": "1.0"
      },

      "schemas": {
        "conversation-origin": "1.0",
        "saved-view": "1.0"
      }
    },

    "consumes": {
      "optional": {
        "refresh": {
          "protocol": ">=1.0.0 <2.0.0",
          "capabilities": {
            "ticket-toolbar": ">=1.0 <2.0",
            "conversation-ui": ">=1.0 <2.0",
            "swipe-actions": ">=1.0 <2.0",
            "navigation-context": ">=1.0 <2.0"
          }
        }
      }
    }
  }
}
```

Refresh would declare its own capabilities instead of being a parent dependency.

---

# 6. Runtime JavaScript interoperability

The browser-side contract should use a neutral global namespace:

```javascript
window.FreeScoutInterop
```

Do not use:

```javascript
window.Refresh
```

as the root interoperability API if the goal is equal-level cooperation.

Refresh may still expose its own private/public module API, but shared cooperation should use the neutral namespace.

---

# 7. Safe bootstrap

Both modules may include the same small interoperability bootstrap.

It must be idempotent:

```javascript
window.FreeScoutInterop = window.FreeScoutInterop || createFreeScoutInterop();
```

Loading order must not matter.

Valid:

```text
Refresh loads first
RefreshGlobal loads second
```

Valid:

```text
RefreshGlobal loads first
Refresh loads second
```

The same global registry must be reused.

---

# 8. Module registration

Each module registers itself:

```javascript
FreeScoutInterop.modules.register({
    id: 'refresh',
    version: '1.8.0',
    protocol: '1.0.0',

    capabilities: {
        navigation: '1.0',
        ticketToolbar: '1.0',
        swipeActions: '1.0',
        conversationUi: '1.0',
        dashboard: '1.1'
    }
});
```

RefreshGlobal:

```javascript
FreeScoutInterop.modules.register({
    id: 'refresh-global',
    version: '1.20.0',
    protocol: '1.0.0',

    capabilities: {
        globalTicketList: '1.0',
        globalDashboard: '1.0',
        savedViews: '1.0',
        sharedViews: '1.0',
        navigationOrigin: '1.0'
    }
});
```

---

# 9. Capability discovery

Modules must never assume another capability exists.

Use capability discovery:

```javascript
FreeScoutInterop.supports(
    'refresh',
    'ticketToolbar',
    '>=1.0 <2.0'
);
```

Possible result:

```javascript
true
```

If false, the caller must:

- use its own implementation;
- use a documented legacy fallback;
- or disable only the optional integration.

It must not crash.

---

# 10. Communication surfaces

A **surface** is a user-interface or functional area that can have an active owner.

Recommended surfaces:

```text
navigation
mobile-navigation
ticket-list
ticket-toolbar
dashboard
swipe-actions
conversation-context
search
saved-views
```

Example registration:

```javascript
FreeScoutInterop.surfaces.register({
    module: 'refresh',
    surface: 'ticket-list',
    context: 'mailbox',
    version: '1.0'
});
```

RefreshGlobal:

```javascript
FreeScoutInterop.surfaces.register({
    module: 'refresh-global',
    surface: 'ticket-list',
    context: 'global-mailboxes',
    version: '1.0'
});
```

---

# 11. Context-aware ownership

The goal is not to remove duplicate capabilities.

Both modules may implement:

```text
navigation
dashboard
ticket list
mobile UI
```

because both must work independently.

Only the implementation relevant to the current context should be active.

Example:

```text
/mailbox/3
=> ticket-list owner: Refresh

/refresh-global/tickets
=> ticket-list owner: RefreshGlobal
```

For dashboards:

```text
mailbox context
=> Refresh dashboard

global mailbox context
=> RefreshGlobal dashboard
```

This avoids hard dependency while preventing duplicate UI.

---

# 12. Conversation opening and navigation context

This is one of the most important cross-module cases.

A conversation is still a FreeScout conversation.

RefreshGlobal may open it from:

```text
All Mailboxes
```

Refresh may then enhance the ticket UI.

RefreshGlobal must communicate the **origin**, not manipulate Refresh HTML.

Example:

```javascript
FreeScoutInterop.context.setConversationOrigin({
    schema: '1.0',
    sourceModule: 'refresh-global',

    conversationId: 1523,
    mailboxId: 4,

    origin: {
        id: 'refreshglobal.all-mailboxes',
        label: 'All mailboxes',
        href: '/refresh-global/tickets?view=my-open-tickets'
    }
});
```

Refresh may consume this and display:

```text
← All mailboxes
```

RefreshGlobal must not directly modify:

```text
breadcrumb DOM
mobile back button DOM
Refresh toolbar DOM
```

---

# 13. Events

Events must be stable and versioned.

Recommended examples:

```text
interop:ready
context:changed
conversation:opened
conversation:rendered
conversation:updated
list:rendered
view:changed
module:registered
capability:changed
```

Example:

```javascript
FreeScoutInterop.events.emit('conversation:opened', {
    schema: '1.0',
    conversationId: 1523,
    mailboxId: 4,

    origin: {
        module: 'refresh-global',
        href: '/refresh-global/tickets?view=42'
    }
});
```

Listener:

```javascript
FreeScoutInterop.events.on('conversation:opened', function (payload) {
    // Consume structured context.
});
```

---

# 14. Event payload rules

Every cross-module payload should include:

```text
schema version
source module
stable IDs
structured values
```

Avoid sending:

```text
CSS selectors
HTML fragments
internal object references
undocumented callbacks
```

Preferred:

```json
{
  "schema": "1.0",
  "source_module": "refresh-global",
  "conversation_id": 1523,
  "mailbox_id": 4
}
```

---

# 15. Navigation registry

Suggested API:

```javascript
FreeScoutInterop.navigation.register({
    id: 'refreshglobal.all-mailboxes',
    owner: 'refresh-global',
    label: 'All mailboxes',
    href: '/refresh-global/tickets',
    icon: 'inbox-stack',

    placements: [
        'desktop-sidebar',
        'mobile-drawer',
        'mobile-bottom-bar'
    ],

    position: {
        after: 'tickets'
    },

    replaces: {
        id: 'tickets',
        placements: [
            'desktop-sidebar',
            'mobile-bottom-bar'
        ]
    }
});
```

The active UI implementation decides how to render the entry.

A module provides intent, not HTML.

---

# 16. Ticket toolbar registry

Suggested API:

```javascript
FreeScoutInterop.toolbar.register({
    id: 'refreshglobal.move-to-folder',
    owner: 'refresh-global',

    label: 'Move to folder',
    icon: 'folder',

    contexts: [
        'conversation'
    ],

    position: {
        after: 'close'
    },

    handler: function (context) {
        openMoveDialog(context.conversationId);
    }
});
```

Context should contain stable data:

```javascript
{
    conversationId: 1523,
    mailboxId: 4,
    userId: 8,
    mobile: false
}
```

A toolbar owner such as Refresh may render the action on desktop and mobile.

---

# 17. Swipe registry

Only one component should own raw touch gesture handling for a specific rendered list.

Other modules register actions.

Example:

```javascript
FreeScoutInterop.swipe.register({
    id: 'refreshglobal.move',
    owner: 'refresh-global',
    direction: 'left',
    label: 'Move',
    icon: 'folder',
    order: 100,

    handler: function (context) {
        openMoveDialog(context.conversationId);
    }
});
```

This avoids two modules competing for:

```text
touchstart
touchmove
touchend
translateX
```

---

# 18. Server-side interoperability

JavaScript is not enough.

Backend cooperation should use FreeScout/Eventy whenever possible.

Use neutral hook names.

Example:

```php
\Eventy::addFilter('fsinterop.dashboard.context', function ($context) {
    return $context;
});
```

A module may extend the context without directly calling another module.

Example:

```php
\Eventy::addFilter('fsinterop.dashboard.context', function ($context) {
    if (($context['scope'] ?? null) === 'global-mailboxes') {
        $context['mailbox_ids'] = auth()->user()
            ->mailboxesCanView()
            ->pluck('id')
            ->all();
    }

    return $context;
});
```

---

# 19. Dashboard strategy

Refresh and RefreshGlobal should remain free to own separate dashboard renderers.

Recommended:

```text
mailbox scope
=> Refresh dashboard

global-mailboxes scope
=> RefreshGlobal dashboard
```

However, reusable data structures should be standardized where useful.

Example schema:

```json
{
  "schema": "1.1",
  "scope": "global-mailboxes",

  "counts": {
    "open": 41,
    "pending": 17,
    "unassigned": 5
  },

  "agents": [
    {
      "user_id": 12,
      "open": 7,
      "pending": 3,
      "total": 10
    }
  ]
}
```

RefreshGlobal may render this itself.

Refresh may render similar data in its own context.

Sharing the schema is safer than sharing templates.

---

# 20. Search

Search should eventually support a registered provider or search context.

Possible future contract:

```javascript
FreeScoutInterop.search.registerProvider({
    id: 'refreshglobal.global-search',
    owner: 'refresh-global',
    context: 'global-mailboxes',
    endpoint: '/refresh-global/search'
});
```

This is optional for protocol 1.0 if it is not yet needed.

It should still be declared in `interop.json` when introduced.

---

# 21. Compatibility modes

Each optional integration must have an explicit fallback mode.

Recommended states:

```text
native
legacy
degraded
unavailable
```

Example:

```text
Refresh present + Interop API supported
=> native

Refresh present + old version
=> legacy DOM compatibility

Refresh absent
=> standalone

Refresh present but incompatible protocol
=> degraded or blocked integration only
```

RefreshGlobal should expose this in diagnostics.

Example:

```text
Refresh integration

Refresh version:         1.8.0
Interop protocol:        1.0.0
Mode:                    Native
Compatibility:           Full
```

Legacy:

```text
Refresh version:         1.6.2
Interop protocol:        Not available
Mode:                    Legacy
Compatibility:           Limited
DOM fallbacks:           Enabled
```

---

# 22. Canonical change declaration

Any new public interoperability output MUST be declared in `interop.json`.

Examples of "public output":

- emitted event;
- consumed event;
- capability;
- surface;
- action registry;
- schema;
- server-side Eventy hook;
- route used by another module;
- callback contract;
- compatibility requirement.

The implementation workflow must be:

```text
1. Design integration point
2. Add/update interop.json
3. Implement code
4. Add tests
5. Update docs
6. Update changelog
```

Not:

```text
1. Modify DOM
2. Ship
3. Discover breakage later
```

---

# 23. AI agent rules

Every participating repository should contain:

```text
AGENTS.md
```

The file should contain an explicit interoperability rule.

Recommended content:

```markdown
# Interoperability rules

Before changing any code related to:

- navigation
- mobile navigation
- ticket lists
- conversation pages
- toolbars
- dashboard
- swipe gestures
- search
- shared views
- cross-module events
- public routes
- UpdateGuard compatibility

you MUST read:

1. `interop.json`
2. `docs/INTEROPERABILITY.md`

Refresh and RefreshGlobal are sibling FreeScout modules.

Neither module may introduce a hard dependency on the other.

If a public cross-module capability, event, schema, hook, route or action is
added, removed, renamed or changed:

1. update `interop.json` in the same commit;
2. add or update compatibility tests;
3. update `docs/INTEROPERABILITY.md`;
4. update the changelog;
5. preserve the previous contract when possible during a deprecation period.

Do not depend on another module's private DOM selectors or private methods when
a documented interoperability channel exists.
```

This makes the architecture visible to coding agents before they modify code.

---

# 24. AI-readable integration registry

In addition to `interop.json`, the repositories may provide:

```text
docs/interop/
```

Example:

```text
docs/interop/
├── schemas/
│   ├── conversation-origin-1.0.schema.json
│   ├── dashboard-data-1.1.schema.json
│   └── saved-view-1.0.schema.json
│
└── examples/
    ├── refresh.json
    └── refreshglobal.json
```

This helps humans, tests and AI agents understand exact payloads.

---

# 25. CI validation

Add a CI job such as:

```text
Interop Contract
```

It should validate:

- `interop.json` is valid JSON;
- manifest version is supported;
- semantic versions are valid;
- capability IDs are unique;
- event IDs are unique;
- referenced schema files exist;
- declared commands exist where practical;
- no required field is missing.

Optional advanced test:

- inspect registrations in code;
- fail when a registered public capability/event is not declared in `interop.json`.

---

# 26. Cross-module CI

Refresh should test:

```text
Refresh stable tests
Refresh interoperability contract
Refresh + latest stable RefreshGlobal
Refresh + RefreshGlobal main
```

RefreshGlobal should test:

```text
RefreshGlobal standalone
RefreshGlobal + latest stable Refresh
RefreshGlobal + Refresh main
```

A breaking integration change should ideally fail CI before release.

---

# 27. Compatibility matrix

Each module should declare supported peer ranges.

Example RefreshGlobal:

```json
{
  "compatibility": {
    "freescout": ">=1.8.0 <2.0.0",

    "php": ">=8.1 <8.5",

    "peers": {
      "refresh": {
        "required": false,
        "supported_versions": ">=1.7.0 <2.0.0",
        "interop_protocol": ">=1.0.0 <2.0.0",

        "fallback": {
          "supported_versions": ">=1.5.0 <1.7.0",
          "mode": "legacy"
        }
      }
    }
  }
}
```

The peer is optional.

This does **not** make Refresh a dependency.

It only declares:

```text
If Refresh is present, these combinations are supported.
```

---

# 28. Compatibility severity

Compatibility results should be normalized:

```text
COMPATIBLE
DEGRADED
UNKNOWN
INCOMPATIBLE
```

Meaning:

### COMPATIBLE

All declared required versions/capabilities are satisfied.

### DEGRADED

The module can run, but some optional interoperability features are unavailable.

Example:

```text
RefreshGlobal works standalone,
but Refresh is too old for native toolbar integration.
```

### UNKNOWN

No compatibility declaration is available.

### INCOMPATIBLE

A declared hard compatibility requirement is not satisfied.

UpdateGuard may block installation for this state.

---

# 29. UpdateGuard integration

`interop.json` should also contain a dedicated update safety section.

Example:

```json
{
  "updateguard": {
    "schema": "1.0",

    "package": {
      "asset": "RefreshGlobal.zip"
    },

    "integrity": {
      "algorithm": "sha256",
      "asset": "SHA256SUMS",
      "required": true
    },

    "health": {
      "command": "php artisan refreshglobal:health",
      "json_command": "php artisan refreshglobal:health --json",

      "exit_codes": {
        "healthy": 0,
        "unhealthy": 1,
        "error": 2
      },

      "read_only": true,
      "network": false
    },

    "compatibility": {
      "freescout": ">=1.8.0 <2.0.0",
      "php": ">=8.1 <8.5",

      "peers": {
        "refresh": {
          "required": false,
          "supported_versions": ">=1.7.0 <2.0.0",
          "interop_protocol": ">=1.0.0 <2.0.0"
        }
      }
    }
  }
}
```

---

# 30. Release hash contract

Each release should ideally publish:

```text
RefreshGlobal.zip
module.json
interop.json
SHA256SUMS
```

Example `SHA256SUMS`:

```text
4d472a...c92f  RefreshGlobal.zip
9ab287...18cc  module.json
ad022b...d914  interop.json
```

At minimum, the installable package must be hashed.

UpdateGuard should:

```text
download
   |
verify SHA-256
   |
parse interop.json
   |
evaluate compatibility
   |
security scan
   |
backup
   |
install
   |
health check
   |
keep or rollback
```

---

# 31. Health check contract

Each module should expose a stable read-only health check.

Refresh example:

```bash
php artisan refresh:health
```

RefreshGlobal example:

```bash
php artisan refreshglobal:health
```

WorkflowPacks example:

```bash
php artisan workflowpacks:health
```

Recommended exit codes:

```text
0 = healthy
1 = unhealthy
2 = health check execution/configuration error
```

JSON mode:

```bash
php artisan refreshglobal:health --json
```

Example response:

```json
{
  "schema": "1.0",
  "module": "RefreshGlobal",
  "version": "1.20.0",
  "status": "healthy",

  "checks": {
    "files": "pass",
    "classes": "pass",
    "service_provider": "pass",
    "routes": "pass",
    "views": "pass",
    "assets": "pass",
    "database": "pass",
    "interop_manifest": "pass"
  }
}
```

A health check should normally:

- be read-only;
- avoid external network requests;
- avoid sending notifications/emails;
- avoid modifying tickets;
- avoid performing destructive repair automatically.

---

# 32. UpdateGuard preflight compatibility

Before installing a new module version, UpdateGuard should evaluate the target release manifest.

Example:

```text
Installed:
FreeScout 1.8.247
Refresh 1.8.0
RefreshGlobal 1.19.0

Target:
RefreshGlobal 1.20.0
```

Target manifest:

```text
FreeScout >=1.8.0 <2.0.0
Refresh optional
If installed: Refresh >=1.7.0 <2.0.0
Interop >=1.0 <2.0
```

Result:

```text
FreeScout ............. PASS
PHP ................... PASS
Refresh ............... PASS
Interop protocol ...... PASS

Compatibility: COMPATIBLE
```

---

# 33. UpdateGuard blocking policy

Recommended default behavior:

```text
Checksum mismatch
=> BLOCK

Required FreeScout version not satisfied
=> BLOCK

Required PHP version not satisfied
=> BLOCK

Required peer dependency missing
=> BLOCK

Installed optional peer explicitly declared incompatible
=> BLOCK or require explicit administrator override

Optional capability missing
=> WARNING / DEGRADED

No compatibility metadata
=> WARNING / UNKNOWN

Health check fails after update
=> ROLLBACK
```

---

# 34. Incompatible optional peer case

Important example:

RefreshGlobal does not require Refresh.

However, a specific RefreshGlobal release may know that:

```text
Refresh 1.9.0 - 1.9.2
```

contains a breaking interoperability bug.

The manifest can declare:

```json
{
  "peers": {
    "refresh": {
      "required": false,

      "supported_versions": ">=1.7.0 <2.0.0",

      "conflicts": [
        ">=1.9.0 <=1.9.2"
      ]
    }
  }
}
```

UpdateGuard sees Refresh installed at `1.9.1`.

Result:

```text
Refresh is optional,
but the installed version is explicitly incompatible.

Update status: BLOCKED
```

This prevents known-bad combinations without making RefreshGlobal dependent on Refresh.

---

# 35. Degraded compatibility

Example:

Target RefreshGlobal supports:

```text
Refresh native Interop:
>=1.7.0

Legacy Refresh fallback:
>=1.5.0 <1.7.0
```

Installed:

```text
Refresh 1.6.2
```

UpdateGuard may report:

```text
Compatibility: DEGRADED

RefreshGlobal will remain functional.
Refresh integration will use legacy compatibility mode.

Administrator action:
Update Refresh when convenient.
```

This should not necessarily block the update.

---

# 36. Interop protocol mismatch

Example:

```text
Refresh provides:
Interop 2.0

RefreshGlobal target supports:
Interop >=1.0 <2.0
```

If RefreshGlobal also has a standalone path:

```text
RefreshGlobal core functionality:
works

Native Refresh interoperability:
not supported
```

The manifest must explicitly state whether this is:

```text
DEGRADED
```

or:

```text
INCOMPATIBLE
```

Do not let UpdateGuard guess.

---

# 37. Recommended manifest compatibility policy

Add:

```json
{
  "compatibility_policy": {
    "peer_protocol_mismatch": "degraded",
    "explicit_conflict": "blocked",
    "unknown_peer_version": "warning"
  }
}
```

This lets the module author define intent.

---

# 38. UpdateGuard report example

```text
Modules Updates Guard
Preflight Report

Target:
RefreshGlobal 1.20.0

Integrity
---------
Package SHA256 ............... VERIFIED
Manifest SHA256 .............. VERIFIED

Compatibility
-------------
FreeScout 1.8.247 ............ PASS
PHP 8.3 ...................... PASS
Refresh 1.8.0 ................ PASS
FS Interop 1.0 ............... PASS

Security
--------
Local static scan ............ PASS
VirusTotal ................... PASS

Update safety
-------------
Backup ....................... READY
Native health check .......... SUPPORTED
Rollback ..................... SUPPORTED

Decision
--------
SAFE TO UPDATE
```

Blocked example:

```text
Compatibility
-------------
Refresh 1.9.1 ................ BLOCKED

Reason:
Target RefreshGlobal declares Refresh >=1.9.0 <=1.9.2
as incompatible.

Update aborted before installation.
```

---

# 39. Post-update health and rollback

After installation:

```text
Install target version
       |
run native health check
       |
       +---- exit 0 ----> keep update
       |
       +---- exit 1 ----> rollback
       |
       +---- exit 2 ----> policy decision
```

After rollback:

```text
restore previous files
restore required metadata
clear caches if necessary
run previous version health check
generate report
```

---

# 40. Compatibility data source priority

UpdateGuard should read compatibility information in this order:

```text
1. release interop.json
2. installed interop.json
3. module.json compatibility fields if supported
4. UpdateGuard built-in compatibility database
5. unknown
```

Release metadata should have the highest priority for the target version.

---

# 41. Never trust compatibility metadata alone

Compatibility metadata indicates declared support.

It does not prove software safety.

UpdateGuard should still use:

```text
SHA256 verification
security scanning
backup
health checks
rollback
```

---

# 42. Deprecation policy

A public interoperability capability should not disappear in a minor release without warning.

Recommended lifecycle:

```text
ACTIVE
   |
DEPRECATED
   |
SUPPORTED FOR TRANSITION
   |
REMOVED IN MAJOR CONTRACT VERSION
```

Manifest example:

```json
{
  "events": {
    "refresh:list-rendered": {
      "version": "1.0",
      "status": "deprecated",
      "replacement": "list:rendered",
      "remove_in_protocol": "2.0.0"
    }
  }
}
```

---

# 43. Breaking change rules

A change is breaking if it changes:

- event name;
- event payload semantics;
- required field;
- capability behavior;
- route used by another module;
- action registry contract;
- public schema;
- health command;
- health exit code semantics;
- checksum location;
- compatibility interpretation.

Breaking changes require:

```text
protocol major version bump
manifest update
documentation
migration notes
cross-module tests
```

---

# 44. Integration test fixtures

Each repository should contain fixtures for the other module's public contract.

Example:

```text
tests/
└── Interop/
    ├── manifests/
    │   ├── refresh-1.0.json
    │   └── refreshglobal-1.0.json
    │
    ├── schemas/
    └── CompatibilityTest.php
```

Tests should not depend on private implementation.

---

# 45. Diagnostics page

RefreshGlobal should expose an interoperability diagnostics section.

Example:

```text
RefreshGlobal Diagnostics

FreeScout
Version: 1.8.247

Refresh
Installed: Yes
Version: 1.8.0
Interop protocol: 1.0.0

Integration
Navigation: Native
Toolbar: Native
Swipe: Native
Conversation context: Native
Dashboard: Independent
Legacy DOM fallbacks: Disabled

Overall compatibility: FULL
```

This is very useful for support.

---

# 46. UpdateGuard diagnostics

UpdateGuard can show:

```text
Compatibility Sources

Refresh
Manifest: Found
Health check: Supported
SHA256: Supported
Interop protocol: 1.0

RefreshGlobal
Manifest: Found
Health check: Supported
SHA256: Supported
Interop protocol: 1.0
```

---

# 47. Repository documentation

Recommended files in both repositories:

```text
AGENTS.md
interop.json
docs/INTEROPERABILITY.md
docs/interop/schemas/*
CHANGELOG.md
CHANGELOG.fr.md   (optional)
```

For international users, the main changelog should preferably be available in English.

---

# 48. Responsibilities

## FreeScout

Owns:

- core conversations;
- authentication;
- mailbox permissions;
- core routes;
- Eventy;
- FreeScout models and services.

## Refresh

Owns:

- Refresh-specific UI;
- mailbox-context enhancements;
- its own navigation implementation;
- its own toolbar renderer;
- its own mobile rendering;
- its own dashboard;
- its own gesture implementation.

## RefreshGlobal

Owns:

- global multi-mailbox ticket list;
- global dashboard;
- saved/shared views;
- global filters;
- global navigation context;
- its own standalone UI;
- its own backend queries.

## Interop contract

Owns only:

- communication shapes;
- capability names;
- event names;
- context schemas;
- compatibility metadata.

## UpdateGuard

Owns:

- release verification;
- compatibility preflight;
- backups;
- security scanning;
- update orchestration;
- post-update health validation;
- rollback;
- reports.

---

# 49. What the interop contract must NOT become

The interop layer must not become:

```text
a third full UI framework
a copy of Refresh
a copy of RefreshGlobal
a hard dependency
a central business-logic module
```

Keep it small.

Its job is only:

```text
discover
declare
communicate
negotiate
validate
```

---

# 50. Recommended first implementation

## Phase 1 - Manifest and documentation

Add to both projects:

```text
interop.json
AGENTS.md
docs/INTEROPERABILITY.md
```

Define:

```text
protocol 1.0
module discovery
capabilities
events
compatibility
UpdateGuard metadata
```

---

## Phase 2 - JavaScript bridge

Implement:

```text
FreeScoutInterop.modules
FreeScoutInterop.supports()
FreeScoutInterop.events
FreeScoutInterop.context
```

Migrate:

```text
conversation origin
navigation state
ready/render events
```

---

## Phase 3 - UI registries

Implement:

```text
navigation registry
toolbar registry
swipe registry
surface ownership
```

Gradually remove direct DOM coupling.

---

## Phase 4 - Server-side hooks

Add neutral Eventy contracts where required.

Examples:

```text
fsinterop.dashboard.context
fsinterop.navigation.context
fsinterop.conversation.context
```

---

## Phase 5 - UpdateGuard preflight

UpdateGuard reads:

```text
interop.json
SHA256SUMS
health declaration
compatibility ranges
conflicts
```

Then blocks known-incompatible updates before installation.

---

## Phase 6 - CI interoperability matrix

Automate:

```text
Refresh standalone
RefreshGlobal standalone
Refresh + RefreshGlobal stable
Refresh main + RefreshGlobal main
```

---

# 51. Recommended minimum viable contract v1

Protocol `1.0.0` should preferably include only:

```text
module registration
capability discovery
event bus
conversation origin/context
navigation registry
toolbar registry
swipe registry
surface ownership
compatibility metadata
UpdateGuard metadata
```

Avoid making protocol 1.0 too large.

Add future capabilities without breaking the base contract.

---

# 52. Example complete Refresh manifest

```json
{
  "manifest_version": 1,

  "module": {
    "id": "refresh",
    "name": "Refresh",
    "version_source": "module.json"
  },

  "interop": {
    "protocol": "1.0.0",

    "provides": {
      "capabilities": {
        "navigation": "1.0",
        "ticket-toolbar": "1.0",
        "swipe-actions": "1.0",
        "conversation-ui": "1.0",
        "dashboard": "1.1",
        "navigation-context": "1.0"
      },

      "events": {
        "conversation:rendered": "1.0",
        "conversation:updated": "1.0",
        "list:rendered": "1.0"
      }
    },

    "consumes": {
      "optional": {
        "refresh-global": {
          "protocol": ">=1.0.0 <2.0.0",

          "capabilities": {
            "global-ticket-list": ">=1.0 <2.0",
            "navigation-origin": ">=1.0 <2.0"
          }
        }
      }
    }
  },

  "updateguard": {
    "schema": "1.0",

    "package": {
      "asset": "Refresh.zip"
    },

    "integrity": {
      "algorithm": "sha256",
      "asset": "SHA256SUMS",
      "required": true
    },

    "health": {
      "command": "php artisan refresh:health",
      "json_command": "php artisan refresh:health --json",

      "exit_codes": {
        "healthy": 0,
        "unhealthy": 1,
        "error": 2
      },

      "read_only": true,
      "network": false
    }
  }
}
```

---

# 53. Example complete RefreshGlobal manifest

```json
{
  "manifest_version": 1,

  "module": {
    "id": "refresh-global",
    "name": "RefreshGlobal",
    "version_source": "module.json"
  },

  "interop": {
    "protocol": "1.0.0",

    "provides": {
      "capabilities": {
        "global-ticket-list": "1.0",
        "global-dashboard": "1.0",
        "saved-views": "1.0",
        "shared-views": "1.0",
        "navigation-origin": "1.0"
      },

      "events": {
        "refreshglobal:view-changed": "1.0",
        "refreshglobal:conversation-opened": "1.0"
      }
    },

    "consumes": {
      "optional": {
        "refresh": {
          "protocol": ">=1.0.0 <2.0.0",

          "capabilities": {
            "navigation": ">=1.0 <2.0",
            "ticket-toolbar": ">=1.0 <2.0",
            "swipe-actions": ">=1.0 <2.0",
            "conversation-ui": ">=1.0 <2.0",
            "navigation-context": ">=1.0 <2.0"
          }
        }
      }
    }
  },

  "compatibility": {
    "freescout": ">=1.8.0 <2.0.0",
    "php": ">=8.1 <8.5",

    "peers": {
      "refresh": {
        "required": false,
        "supported_versions": ">=1.7.0 <2.0.0",
        "interop_protocol": ">=1.0.0 <2.0.0",

        "fallback": {
          "supported_versions": ">=1.5.0 <1.7.0",
          "mode": "legacy"
        },

        "conflicts": []
      }
    }
  },

  "updateguard": {
    "schema": "1.0",

    "package": {
      "asset": "RefreshGlobal.zip"
    },

    "integrity": {
      "algorithm": "sha256",
      "asset": "SHA256SUMS",
      "required": true
    },

    "health": {
      "command": "php artisan refreshglobal:health",
      "json_command": "php artisan refreshglobal:health --json",

      "exit_codes": {
        "healthy": 0,
        "unhealthy": 1,
        "error": 2
      },

      "read_only": true,
      "network": false
    }
  }
}
```

---

# 54. Final rule

The most important rule of the entire architecture is:

> **Refresh and RefreshGlobal remain independent FreeScout modules. They cooperate through a small, versioned and declared interoperability contract.**

And the most important maintenance rule is:

> **No undocumented cross-module integration is allowed.**

If a new interaction is added:

```text
declare it
version it
test it
document it
announce compatibility
```

before release.

This is what allows both projects to evolve quickly without silently breaking each other, while also giving UpdateGuard enough structured information to stop clearly incompatible updates before they reach production.
