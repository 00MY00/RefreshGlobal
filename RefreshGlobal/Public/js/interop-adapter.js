/*
 * RefreshGlobal — participation in the FreeScout Interop contract (browser side). Loaded on every page after
 * Public/js/fs-interop.js and before Public/js/shell.js (FreeScout's "javascripts" filter).
 *
 * What it does, from the data of <meta name="refreshglobal-interop"> (written by the server from interop.json, the
 * single source of truth: Providers/RefreshGlobalServiceProvider::registerInterop()):
 *   1. registers RefreshGlobal (id, version, protocol, capabilities of its manifest);
 *   2. declares the surfaces it renders on the current page, as decided on the server by the filter
 *      fsinterop.navigation.context (Services/Interop/NavigationContext.php: "page.owners" of the meta);
 *   3. declares its INTENTS — never HTML — in the shared registries: the "All mailboxes" navigation entry, the ticket
 *      toolbar actions ("Move to a folder", "Delete forever" for a ticket in the trash) and the swipe action;
 *   4. gives the origin of a ticket opened from the "All mailboxes" list (FreeScoutInterop.context) and announces the
 *      list shown (refreshglobal:view-changed), the ticket opened (refreshglobal:conversation-opened) and the
 *      dashboard of all mailboxes (refreshglobal:dashboard-rendered);
 *   5. tells Public/js/shell.js, capability by capability, whether Refresh cooperates natively
 *      (window.RefreshGlobalInterop.native()) — otherwise shell.js keeps its documented DOM fallbacks (legacy mode);
 *   6. offers RefreshGlobalInterop.search(query, limit), a module extension outside the shared protocol.
 * Nothing here touches another module's HTML.
 */
(function (root) {
    'use strict';

    var FI = root.FreeScoutInterop;
    if (!FI) {
        return; // the bridge did not load: shell.js stays in legacy mode (native() is undefined → false)
    }

    /* ------------------------------------------------------------------ configuration */

    /** <meta name="..."> JSON content, or null. */
    function readMeta(name) {
        try {
            return JSON.parse(document.querySelector('meta[name="' + name + '"]').getAttribute('content'));
        } catch (e) {
            return null;
        }
    }

    var cfg = readMeta('refreshglobal-interop');
    if (!cfg || !cfg.module) {
        return;
    }
    var PEER = 'refresh';

    /* ------------------------------------------------------------------ 5. negotiation (used by shell.js) */

    /**
     * True when the peer (Refresh) provides this capability in a version RefreshGlobal supports (ranges of
     * interop.json, interop.consumes.optional.refresh). Evaluated when asked: Refresh may register after this file.
     */
    function native(capability) {
        var ranges = (cfg.consumes && cfg.consumes.capabilities) || {};
        if (!Object.prototype.hasOwnProperty.call(ranges, capability)) {
            return false;
        }
        var peer = FI.modules.get(PEER);
        if (!peer || !FI.semver.satisfies(peer.protocol, cfg.consumes.protocol || '*')) {
            return false;
        }
        return FI.supports(PEER, capability, ranges[capability]);
    }

    /** Overall mode with the peer, for diagnostics in the browser console. */
    function mode() {
        if (!FI.modules.get(PEER)) {
            return document.querySelector('.rf-rail, .rf-m-tabs, body.rf-shell') ? 'legacy' : 'standalone';
        }
        var caps = Object.keys((cfg.consumes && cfg.consumes.capabilities) || {});
        var nativeCount = caps.filter(native).length;
        return nativeCount === caps.length ? 'native' : (nativeCount ? 'partial' : 'legacy');
    }

    root.RefreshGlobalInterop = {
        native: native,
        mode: mode,
        /** Status per consumed capability: {capability: "native" | "legacy"} (console: RefreshGlobalInterop.status()). */
        status: function () {
            var out = {};
            Object.keys((cfg.consumes && cfg.consumes.capabilities) || {}).forEach(function (cap) {
                out[cap] = native(cap) ? 'native' : 'legacy';
            });
            return out;
        }
    };

    /* ------------------------------------------------------------------ 1. registration */

    FI.modules.register({
        id: cfg.module.id,
        version: cfg.module.version,
        protocol: cfg.protocol,
        capabilities: cfg.capabilities || {}
    });

    /* ------------------------------------------------------------------ 2. surfaces of this page */

    /**
     * Surfaces claimed by RefreshGlobal on this page ("surface@scope" => module id, server side), with the versions of
     * interop.json (provides.surfaces). The server knows the page: no guessing from the HTML.
     */
    function registerSurfaces() {
        var owners = (cfg.page && cfg.page.owners) || {};
        Object.keys(owners).forEach(function (key) {
            if (owners[key] !== cfg.module.id) {
                return;
            }
            var parts = key.split('@');
            FI.surfaces.register({ module: cfg.module.id, surface: parts[0], context: parts[1], version: (cfg.surfaces || {})[key] || '1.0' });
        });
    }

    registerSurfaces();
    var page = document.body ? document.body.getAttribute('data-rg-page') : null;

    /* ------------------------------------------------------------------ 3. intents */

    /** "All mailboxes" navigation entry (the module that renders the navigation places it). */
    function registerNavigation() {
        if (!cfg.navigation || !cfg.navigation.href) {
            return;
        }
        FI.navigation.register({
            id: 'refreshglobal.all-mailboxes',
            owner: cfg.module.id,
            label: cfg.navigation.label,
            href: cfg.navigation.href,
            icon: cfg.navigation.icon || 'inbox-stack',
            placements: ['desktop-sidebar', 'mobile-drawer', 'mobile-bottom-bar'],
            position: { after: 'tickets' },
            active: !!cfg.navigation.active,
            order: 100,
            // setting "replace Refresh's Tickets entry": the navigation owner hides its own entry in these places
            replaces: cfg.navigation.replace ? { id: 'tickets', placements: ['desktop-sidebar', 'mobile-bottom-bar'] } : undefined
        });
    }

    /** Ticket toolbar and swipe actions of "Move to a folder" (handlers open the dialog of Public/js/shell.js). */
    function registerMoveActions() {
        var move = readMeta('refreshglobal-move');
        if (!move || !move.t) {
            return;
        }
        var open = function (context) {
            if (root.rgMoveDialog && context && context.conversationId) {
                root.rgMoveDialog([parseInt(context.conversationId, 10)]);
            }
        };
        if (move.ticket) {
            FI.toolbar.register({
                id: 'refreshglobal.move-to-folder',
                owner: cfg.module.id,
                label: move.t.title,
                shortLabel: move.t.button,
                icon: 'folder',
                contexts: ['conversation'],
                position: { after: 'close' },
                order: 100,
                handler: open
            });
        }
        FI.swipe.register({
            id: 'refreshglobal.move-to-folder',
            owner: cfg.module.id,
            direction: 'left',
            label: move.t.button,
            icon: 'folder',
            contexts: ['ticket-list'],
            order: 100,
            handler: open
        });
    }

    /**
     * "Delete forever" for a ticket in the trash: FreeScout's own button (its confirmation and rights); declared only
     * when FreeScout prints it on this ticket page.
     */
    function registerDeleteForever() {
        var native = document.querySelector('.conv-delete-forever');
        if (!native || !cfg.labels || !cfg.labels.deleteForever) {
            return;
        }
        FI.toolbar.register({
            id: 'refreshglobal.delete-forever',
            owner: cfg.module.id,
            label: (native.textContent || '').trim() || cfg.labels.deleteForever,
            icon: 'trash',
            contexts: ['conversation-deleted'],
            order: 200,
            handler: function () {
                native.click();
            }
        });
    }

    /**
     * Search over all mailboxes — a RefreshGlobal extension, NOT part of the shared protocol 1.0 (search will be
     * proposed for the contract later): RefreshGlobalInterop.search(query, limit) asks the module's endpoint (the
     * user's rights apply on the server) and resolves with search results 1.0; undefined without fetch().
     */
    function search(query, limit) {
        var endpoint = cfg.search && cfg.search.endpoint;
        return endpoint ? searchRequest(endpoint, { query: query, limit: limit }) : undefined;
    }

    /** GET endpoint?q=…&limit=… with the session cookie; a Promise of the JSON results (rejected on an HTTP error). */
    function searchRequest(endpoint, request) {
        if (!root.fetch) {
            return undefined;
        }
        var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(String(request.query || ''))
            + (request.limit ? '&limit=' + parseInt(request.limit, 10) : '');
        return root.fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            });
    }

    root.RefreshGlobalInterop.search = search;

    /**
     * The dashboard of all mailboxes is on this page: announced (refreshglobal:dashboard-rendered, schema
     * dashboard-rendered 1.0) so that another module can react without reading RefreshGlobal's HTML.
     */
    function announceDashboard() {
        var dash = document.querySelector('.rg-dash');
        if (!dash) {
            return;
        }
        FI.events.emit('refreshglobal:dashboard-rendered', {
            schema: '1.0',
            source_module: cfg.module.id,
            scope: 'global-mailboxes',
            placement: dash.getAttribute('data-placement') || ''
        });
    }

    registerNavigation();
    document.addEventListener('DOMContentLoaded', function () {
        registerMoveActions();
        registerDeleteForever();
        announceDashboard();
    });

    /* ------------------------------------------------------------------ 4. context and events of the list */

    /** The list shown (#rg-state of Resources/views/tickets.blade.php): where a ticket opened from it goes back to. */
    function listState() {
        var s = document.getElementById('rg-state');
        if (!s) {
            return null;
        }
        return {
            href: s.getAttribute('data-back-url') || '',
            label: s.getAttribute('data-back-label') || '',
            count: s.getAttribute('data-back-count') || '',
            viewId: s.getAttribute('data-view-id') || '',
            modified: s.getAttribute('data-view-modified') === '1'
        };
    }

    /** A ticket of the list is being opened: its origin, for the module that renders the ticket page. */
    function announceOpening(conversationId) {
        var list = listState();
        if (!list || !list.href || !conversationId) {
            return;
        }
        var payload = {
            schema: '1.0',
            sourceModule: cfg.module.id,
            conversationId: parseInt(conversationId, 10),
            mailboxId: null,
            origin: {
                id: list.viewId ? 'refreshglobal.view.' + list.viewId : 'refreshglobal.all-mailboxes',
                label: list.label,
                href: list.href.split('#')[0] + '#rg-back=' + conversationId,
                count: list.count === '' ? null : parseInt(list.count, 10)
            }
        };
        FI.context.setConversationOrigin(payload);
        FI.events.emit('refreshglobal:conversation-opened', {
            schema: '1.0', source_module: cfg.module.id, conversation_id: payload.conversationId, origin: payload.origin
        });
    }

    if (page === 'tickets') {
        document.addEventListener('DOMContentLoaded', function () {
            var list = listState();
            if (list) {
                FI.events.emit('refreshglobal:view-changed', {
                    schema: '1.0', source_module: cfg.module.id, view_id: list.viewId ? parseInt(list.viewId, 10) : null,
                    modified: list.modified, href: list.href, label: list.label, count: list.count === '' ? null : parseInt(list.count, 10)
                });
            }
        });
        // capture phase: before Refresh's own handlers navigate (cards open the ticket on a tap of the row)
        document.addEventListener('click', function (e) {
            var row = e.target && e.target.closest ? e.target.closest('.table-conversations tr.conv-row') : null;
            if (!row || (e.target.closest && e.target.closest('.conv-checkbox, .conv-star, label'))) {
                return;
            }
            var box = row.querySelector('.conv-checkbox');
            announceOpening(box ? box.value : null);
        }, true);
    }
})(window);
