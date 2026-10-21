/*
 * Tests of the FreeScout Interop bridge (RefreshGlobal/Public/js/fs-interop.js), with Node only (no dependency):
 *   node tests/interop/fs-interop.test.js
 * Each test loads the bridge in a fresh fake window (vm), as a browser would load it from one or several modules.
 */
'use strict';
var fs = require('fs');
var vm = require('vm');
var path = require('path');
var assert = require('assert');

var SOURCE = fs.readFileSync(path.join(__dirname, '../../RefreshGlobal/Public/js/fs-interop.js'), 'utf8');

/** A fake browser window with a working sessionStorage and a console that records warnings. */
function newWindow() {
    var store = {};
    var warnings = [];
    var win = {
        sessionStorage: {
            getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; },
            setItem: function (k, v) { store[k] = String(v); },
            removeItem: function (k) { delete store[k]; }
        },
        console: { warn: function () { warnings.push(Array.prototype.slice.call(arguments).join(' ')); } },
        warnings: warnings
    };
    win.window = win;
    return win;
}

/** Loads a copy of the bridge into the window (optionally with another protocol version, for the upgrade test). */
function load(win, protocol) {
    var code = protocol ? SOURCE.replace(/var PROTOCOL = '[^']+';/, "var PROTOCOL = '" + protocol + "';") : SOURCE;
    vm.runInNewContext(code, { window: win, Date: Date, JSON: JSON, Object: Object, String: String, parseInt: parseInt });
    return win.FreeScoutInterop;
}

var tests = [];
function test(name, fn) { tests.push({ name: name, fn: fn }); }

test('bootstrap is idempotent: a second copy keeps the registries', function () {
    var win = newWindow();
    var a = load(win);
    a.modules.register({ id: 'refresh', version: '1.8.0', protocol: '1.0.0', capabilities: { navigation: '1.0' } });
    var b = load(win);
    assert.strictEqual(a, b);
    assert.ok(b.modules.get('refresh'));
});

test('loading order does not matter: modules.when() waits for the module', function () {
    var win = newWindow();
    var api = load(win);
    var seen = null;
    api.modules.when('refresh', function (m) { seen = m.version; });
    assert.strictEqual(seen, null);
    api.modules.register({ id: 'refresh', version: '1.8.0', protocol: '1.0.0', capabilities: {} });
    assert.strictEqual(seen, '1.8.0');
    var again = null;
    api.modules.when('refresh', function (m) { again = m.id; });
    assert.strictEqual(again, 'refresh');
});

test('supports(): capability ranges and protocol major', function () {
    var api = load(newWindow());
    api.modules.register({ id: 'refresh', version: '1.8.0', protocol: '1.2.0', capabilities: { 'ticket-toolbar': '1.3', navigation: '2.0' } });
    assert.strictEqual(api.supports('refresh', 'ticket-toolbar', '>=1.0 <2.0'), true);
    assert.strictEqual(api.supports('refresh', 'navigation', '>=1.0 <2.0'), false);
    assert.strictEqual(api.supports('refresh', 'swipe-actions', '*'), false);
    assert.strictEqual(api.supports('absent', 'navigation', '*'), false);
    api.modules.register({ id: 'future', version: '3.0.0', protocol: '2.0.0', capabilities: { navigation: '1.0' } });
    assert.strictEqual(api.supports('future', 'navigation', '*'), false, 'another major protocol is never assumed compatible');
});

test('semver: ranges of the contract', function () {
    var s = load(newWindow()).semver;
    assert.ok(s.satisfies('1.0', '>=1.0 <2.0'));
    assert.ok(s.satisfies('1.9.9', '>=1.0.0 <2.0.0'));
    assert.ok(!s.satisfies('2.0', '>=1.0 <2.0'));
    assert.ok(s.satisfies('1.4.3', '^1.2'));
    assert.ok(!s.satisfies('2.1', '^1.2'));
    assert.ok(s.satisfies('1.2.0', '1.2'));
    assert.ok(s.satisfies('5', '*'));
    assert.ok(!s.satisfies('1.0', '~1.0'), 'unknown syntax never matches');
    assert.ok(!s.satisfies('abc', '*'));
    assert.strictEqual(s.compare('1.10.0', '1.9.9'), 1);
});

test('events: payload copied, a throwing listener is isolated', function () {
    var win = newWindow();
    var api = load(win);
    var got = [];
    api.events.on('list:rendered', function () { throw new Error('boom'); });
    api.events.on('list:rendered', function (p) { p.changed = true; got.push(p); });
    var payload = { schema: '1.0', source_module: 'x' };
    assert.strictEqual(api.events.emit('list:rendered', payload), 2);
    assert.strictEqual(got.length, 1);
    assert.strictEqual(payload.changed, undefined, 'listeners get a copy');
    assert.ok(win.warnings.some(function (w) { return w.indexOf('listener of list:rendered') >= 0; }));
    var n = 0;
    api.events.once('x', function () { n++; });
    api.events.emit('x');
    api.events.emit('x');
    assert.strictEqual(n, 1);
});

test('context: conversation origin kept for the tab and announced', function () {
    var win = newWindow();
    var api = load(win);
    var changed = null;
    api.events.on('context:changed', function (p) { changed = p; });
    assert.strictEqual(api.context.setConversationOrigin({ sourceModule: 'refresh-global', conversationId: 12 }), false, 'origin.href required');
    assert.ok(api.context.setConversationOrigin({ sourceModule: 'refresh-global', conversationId: 12,
        origin: { id: 'refreshglobal.all-mailboxes', label: 'All mailboxes', href: '/refresh-global/tickets#rg-back=12' } }));
    assert.strictEqual(changed.kind, 'conversation-origin');
    // a new page of the same tab (new bridge, same sessionStorage)
    var win2 = newWindow();
    win2.sessionStorage = win.sessionStorage;
    var api2 = load(win2);
    assert.strictEqual(api2.context.getConversationOrigin(12).origin.label, 'All mailboxes');
    assert.strictEqual(api2.context.getConversationOrigin(99), null, 'another ticket');
    api2.context.clearConversationOrigin();
    assert.strictEqual(api2.context.getConversationOrigin(), null);
});

test('registries: required fields, replace by id, order, isolated handlers', function () {
    var api = load(newWindow());
    assert.strictEqual(api.toolbar.register({ id: 'a', owner: 'm', label: 'A' }), false, 'handler required');
    var calls = [];
    api.toolbar.register({ id: 'b', owner: 'm', label: 'B', order: 200, handler: function (c) { calls.push(c.conversationId); } });
    api.toolbar.register({ id: 'a', owner: 'm', label: 'A', order: 100, handler: function () { throw new Error('x'); } });
    api.toolbar.register({ id: 'b', owner: 'm', label: 'B2', order: 200, handler: function (c) { calls.push(c.conversationId); } });
    var list = api.toolbar.list();
    // arrays of the vm context: compared as JSON
    assert.strictEqual(JSON.stringify(list.map(function (i) { return i.id; })), JSON.stringify(['a', 'b']));
    assert.strictEqual(list[1].label, 'B2');
    api.toolbar.run('a', {});
    api.toolbar.run('b', { conversationId: 7 });
    assert.deepStrictEqual(calls, [7]);
    assert.strictEqual(api.swipe.register({ id: 's', owner: 'm', label: 'S', handler: function () {} }), false, 'direction required');
    assert.ok(api.navigation.register({ id: 'n', owner: 'm', label: 'N', href: '/x' }));
    assert.strictEqual(api.navigation.list(function (i) { return i.owner === 'other'; }).length, 0);
});

test('surfaces: owner per context', function () {
    var api = load(newWindow());
    api.surfaces.register({ module: 'refresh', surface: 'ticket-list', context: 'mailbox' });
    api.surfaces.register({ module: 'refresh-global', surface: 'ticket-list', context: 'global-mailboxes' });
    assert.strictEqual(api.surfaces.owner('ticket-list', 'mailbox').module, 'refresh');
    assert.strictEqual(api.surfaces.owner('ticket-list', 'global-mailboxes').module, 'refresh-global');
    assert.strictEqual(api.surfaces.owner('dashboard', 'mailbox'), null);
});

test('a newer copy of the same major only adds what is missing', function () {
    var win = newWindow();
    var old = load(win, '1.0.0');
    delete old.swipe; // an older bridge without the swipe registry
    old.modules.register({ id: 'refresh', version: '1', protocol: '1.0.0', capabilities: {} });
    var now = load(win, '1.1.0');
    assert.strictEqual(now, old);
    assert.ok(now.swipe && typeof now.swipe.register === 'function');
    assert.ok(now.modules.get('refresh'), 'registries kept');
});

test('search providers (protocol 1.1): required fields, context, run', function () {
    var api = load(newWindow());
    assert.strictEqual(api.protocol, '1.1.0');
    assert.strictEqual(api.search.registerProvider({ id: 'p', owner: 'm', context: 'global-mailboxes' }), false, 'endpoint required');
    assert.ok(api.search.registerProvider({ id: 'refreshglobal.global-search', owner: 'refresh-global', context: 'global-mailboxes',
        endpoint: '/refresh-global/search', handler: function (r) { return 'results for ' + r.query; } }));
    assert.ok(api.search.register({ id: 'everywhere', owner: 'other', context: '*', endpoint: '/x' }));
    assert.ok(api.search.register({ id: 'mailbox-only', owner: 'refresh', context: 'mailbox', endpoint: '/y' }));
    assert.strictEqual(JSON.stringify(api.search.forContext('global-mailboxes').map(function (p) { return p.id; })),
        JSON.stringify(['refreshglobal.global-search', 'everywhere']));
    assert.strictEqual(api.search.run('refreshglobal.global-search', { query: 'invoice' }), 'results for invoice');
    assert.strictEqual(api.search.run('everywhere', {}), undefined, 'no handler: nothing run');
});

test('a newer copy adds the search registry to an older bridge, on its event bus', function () {
    var win = newWindow();
    var old = load(win, '1.0.0');
    delete old.search; // a protocol 1.0 bridge (e.g. shipped by another module) loaded first
    var announced = [];
    old.events.on('search:registered', function (p) { announced.push(p.id); });
    var now = load(win);
    assert.strictEqual(now, old);
    assert.strictEqual(now.protocol, '1.1.0', 'the page now offers protocol 1.1');
    assert.ok(now.search.registerProvider({ id: 'refreshglobal.global-search', owner: 'refresh-global', context: 'global-mailboxes', endpoint: '/s' }));
    assert.deepStrictEqual(announced, ['refreshglobal.global-search']);
});

test('an older copy loaded after a newer one changes nothing', function () {
    var win = newWindow();
    var now = load(win);
    var older = load(win, '1.0.0');
    assert.strictEqual(older, now);
    assert.strictEqual(now.protocol, '1.1.0');
    assert.ok(now.search);
});

var failed = 0;
tests.forEach(function (t) {
    try {
        t.fn();
        console.log('ok   ' + t.name);
    } catch (e) {
        failed++;
        console.log('FAIL ' + t.name + '\n     ' + (e && e.message));
    }
});
console.log((tests.length - failed) + '/' + tests.length + ' passed');
process.exit(failed ? 1 : 0);
