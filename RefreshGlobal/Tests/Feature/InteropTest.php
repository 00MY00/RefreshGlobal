<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Services\Dashboard\Sla;
use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\Interop\ConversationContext;
use Modules\RefreshGlobal\Services\Interop\Manifest;
use Modules\RefreshGlobal\Services\Interop\NavigationContext;
use Modules\RefreshGlobal\Services\Interop\PeerStatus;
use Modules\RefreshGlobal\Services\Interop\SemVer;
use Modules\RefreshGlobal\Services\Interop\ServerHooks;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * FreeScout Interop contract (docs/INTEROPERABILITY.md): the module's interop.json, version ranges, cooperation modes
 * with Refresh (native / legacy / standalone / unavailable and COMPATIBLE / DEGRADED / UNKNOWN / INCOMPATIBLE), the
 * neutral server hooks, and what the pages give to the browser side.
 */
class InteropTest extends TestCase
{
    /** A peer manifest (Refresh implementing the contract) as an object, without files. */
    protected function peer($protocol, array $capabilities)
    {
        return new Manifest(['manifest_version' => 1, 'module' => ['id' => 'refresh', 'name' => 'Refresh'],
            'interop' => ['protocol' => $protocol, 'provides' => ['capabilities' => $capabilities]]], sys_get_temp_dir());
    }

    protected function cooperation(array $facts, Manifest $peer = null, array $override = [])
    {
        $data = Manifest::own()->data();
        foreach ($override as $path => $value) {
            $ref = &$data;
            foreach (explode('.', $path) as $key) {
                $ref = &$ref[$key];
            }
            $ref = $value;
            unset($ref);
        }

        return PeerStatus::evaluate(new Manifest($data, Manifest::own()->root()), 'refresh', $facts + ['installed' => true, 'active' => true, 'version' => ''], $peer);
    }

    public function testOwnManifestIsValid()
    {
        $manifest = Manifest::own();
        $this->assertNotNull($manifest);
        $this->assertSame([], $manifest->validate());
        $this->assertSame('refresh-global', $manifest->id());
        $this->assertSame('1.0.0', $manifest->protocol());
        // single source of truth: what the module declares is what it gives the browser
        $this->assertArrayHasKey('global-ticket-list', $manifest->capabilities());
        $this->assertSame(['refreshglobal.move-to-folder', 'refreshglobal.delete-forever'], $manifest->registrations('toolbar'));
    }

    public function testValidationFindsProblems()
    {
        $bad = new Manifest(['manifest_version' => 9, 'module' => ['id' => ''], 'interop' => ['protocol' => 'x',
            'provides' => ['capabilities' => ['a' => 'z', 'old' => ['version' => '1.0', 'status' => 'deprecated']], 'schemas' => ['s' => ['version' => '1.0', 'file' => 'nope.json']],
                'routes' => ['r' => ['path' => 'x']],
                'registrations' => ['toolbar' => [['id' => 'd'], ['id' => 'd']]]],
            'consumes' => ['optional' => ['refresh' => ['protocol' => '~1']]]],
            'compatibility' => ['freescout' => '>=1.8', 'php' => '>=7.1', 'peers' => ['refresh' => ['required' => 'no']]],
            'updateguard' => ['health' => ['command' => 'x', 'json_command' => 'y', 'exit_codes' => ['healthy' => 1], 'read_only' => true, 'network' => true]]],
            sys_get_temp_dir());
        $errors = implode("\n", $bad->validate());
        foreach (['manifest_version', 'module.id', 'not a version', 'capability a', 'nope.json', 'duplicate id d', 'invalid protocol range',
            'required must be true or false', 'healthy must be 0', 'read_only and without network',
            'capability old: a deprecated element needs remove_in_protocol', 'route r: method'] as $expected) {
            $this->assertStringContainsString($expected, $errors);
        }
    }

    public function testDeprecationPolicy()
    {
        $data = Manifest::own()->data();
        $data['interop']['provides']['events']['refreshglobal:view-changed'] += ['status' => 'deprecated', 'replacement' => 'list:rendered', 'remove_in_protocol' => '2.0.0'];
        $manifest = new Manifest($data, Manifest::own()->root());
        $this->assertSame([], $manifest->validate(), 'a deprecated element stays valid during its transition');
        $this->assertSame(['event refreshglobal:view-changed' => ['replacement' => 'list:rendered', 'remove_in_protocol' => '2.0.0']], $manifest->deprecations());
        $this->assertSame([], Manifest::own()->deprecations());

        // removal announced in the current major: refused (a removal needs a major protocol version)
        $data['interop']['provides']['events']['refreshglobal:view-changed']['remove_in_protocol'] = '1.5.0';
        $this->assertNotEmpty((new Manifest($data, Manifest::own()->root()))->validate());
    }

    public function testSemVer()
    {
        $this->assertTrue(SemVer::satisfies('1.6.0', '>=1.4.0 <1.7.0'));
        $this->assertFalse(SemVer::satisfies('1.7.0', '>=1.4.0 <1.7.0'));
        $this->assertTrue(SemVer::satisfies('1.3', '^1.2'));
        $this->assertFalse(SemVer::satisfies('2.0', '^1.2'));
        $this->assertTrue(SemVer::satisfies('1.0', '*'));
        $this->assertFalse(SemVer::satisfies('1.0', '~1.0'), 'unknown syntax never matches');
        $this->assertFalse(SemVer::isRange('>=a'));
        $this->assertSame(1, SemVer::compare('1.10', '1.9.9'));
    }

    public function testCooperationModes()
    {
        // Refresh absent: RefreshGlobal alone, compatible (the peer is optional)
        $s = PeerStatus::evaluate(Manifest::own(), 'refresh', ['installed' => false, 'active' => false, 'version' => '']);
        $this->assertSame(['standalone', 'COMPATIBLE'], [$s['mode'], $s['compatibility']]);

        // Refresh of the tested range, without contract: legacy DOM fallbacks, degraded
        $s = $this->cooperation(['version' => '1.6.0']);
        $this->assertSame(['legacy', 'DEGRADED', true], [$s['mode'], $s['compatibility'], $s['legacy_fallbacks']]);
        $this->assertSame('legacy', $s['capabilities']['ticket-toolbar']);

        // Refresh speaking the contract with every capability: native, compatible, no fallback
        $all = ['navigation' => '1.0', 'navigation-context' => '1.0', 'ticket-toolbar' => '1.2', 'swipe-actions' => '1.0', 'conversation-ui' => '1.0', 'dashboard-handoff' => '1.0'];
        $s = $this->cooperation(['version' => '1.9.0'], $this->peer('1.0.0', $all));
        $this->assertSame(['native', 'COMPATIBLE', false], [$s['mode'], $s['compatibility'], $s['legacy_fallbacks']]);

        // native but a capability missing: that one is unavailable (version outside the tested legacy range), degraded
        $s = $this->cooperation(['version' => '1.9.0'], $this->peer('1.0.0', array_diff_key($all, ['swipe-actions' => 1])));
        $this->assertSame(['native', 'DEGRADED', 'unavailable'], [$s['mode'], $s['compatibility'], $s['capabilities']['swipe-actions']]);

        // an explicit conflict of the manifest: incompatible, integration off
        $s = $this->cooperation(['version' => '1.9.1'], $this->peer('1.0.0', $all), ['compatibility.peers.refresh.conflicts' => ['>=1.9.0 <=1.9.2']]);
        $this->assertSame(['unavailable', 'INCOMPATIBLE'], [$s['mode'], $s['compatibility']]);

        // a protocol of another major: policy "degraded", nothing native
        $s = $this->cooperation(['version' => '3.0.0'], $this->peer('2.0.0', $all));
        $this->assertSame(['unavailable', 'DEGRADED'], [$s['mode'], $s['compatibility']]);

        // an untested version without contract: unknown (warning), DOM fallbacks still tried in the supported range
        $s = $this->cooperation(['version' => '1.9.0']);
        $this->assertSame(['legacy', 'UNKNOWN'], [$s['mode'], $s['compatibility']]);
    }

    public function testServerHooks()
    {
        $modules = \Eventy::filter('fsinterop.modules', []);
        $this->assertSame('1.0.0', $modules['refresh-global']['protocol']);
        $this->assertSame(Manifest::own()->capabilities(), $modules['refresh-global']['capabilities']);

        $bob = $this->s['bob'];
        Fixtures::conversation($this->s['support'], 'interop pending', ['status' => Conversation::STATUS_PENDING]);
        $data = \Eventy::filter('fsinterop.dashboard.data', null, ['scope' => ServerHooks::SCOPE_GLOBAL, 'user_id' => $bob->id]);
        $this->assertSame('1.1', $data['schema']);
        $this->assertEqualsCanonicalizing([$this->s['support']->id, $this->s['sales']->id], $data['mailbox_ids']);
        $this->assertGreaterThan(0, $data['counts']['pending']);
        $this->assertSame($data['counts']['open'] + $data['counts']['pending'], array_sum(array_column($data['agents'], 'total')));
        // another scope: not answered
        $this->assertEmpty(\Eventy::filter('fsinterop.dashboard.data', null, ['scope' => 'mailbox']));

        // a module may narrow the dashboard's mailboxes, never widen them
        \Eventy::addFilter('fsinterop.dashboard.context', function ($context) {
            $context['mailbox_ids'] = [$this->s['sales']->id, $this->s['billing']->id];

            return $context;
        }, 20, 1);
        $ctx = ServerHooks::dashboardContext($bob, [$this->s['support']->id, $this->s['sales']->id]);
        $this->assertSame([$this->s['sales']->id], $ctx['mailbox_ids']);
    }

    public function testDashboardHandoff()
    {
        // fsinterop.dashboard.owner: what Refresh (with the contract) asks before rendering its own block
        \Modules\RefreshGlobal\Services\Settings::setGlobalDashboard(true);
        $ask = function ($user) {
            return \Eventy::filter('fsinterop.dashboard.owner', null, ['schema' => '1.0', 'page' => 'dashboard', 'user_id' => $user->id]);
        };
        $this->assertSame('refresh-global', $ask($this->s['bob']));
        $this->assertEmpty(\Eventy::filter('fsinterop.dashboard.owner', null, ['schema' => '1.0', 'page' => 'other', 'user_id' => $this->s['bob']->id]));
        $noMailbox = Fixtures::user('Nomail');
        $this->assertEmpty($ask($noMailbox), 'nothing to show: Refresh keeps its dashboard');
        \Modules\RefreshGlobal\Services\Settings::setGlobalDashboard(false);
        $this->assertEmpty($ask($this->s['bob']));

        // where the dashboard goes: enabled, Refresh active, Refresh hands over natively, Refresh's block found
        $G = GlobalDashboard::class;
        $this->assertNull($G::decideMode(false, true, true, true));
        $this->assertSame($G::MODE_STANDALONE, $G::decideMode(true, false, false, false));
        $this->assertSame($G::MODE_NATIVE, $G::decideMode(true, true, true, false));
        $this->assertSame($G::MODE_REFRESH, $G::decideMode(true, true, false, true), 'legacy replacement');
        $this->assertNull($G::decideMode(true, true, false, false), 'unknown Refresh output: left untouched');
    }

    public function testSlaTargetsHook()
    {
        $this->assertNotSame('hook:refresh', Sla::source());
        // a module (Refresh with the contract) declares its targets: they win over every other source
        \Eventy::addFilter('fsinterop.sla.targets', function ($targets) {
            return ['schema' => '1.0', 'source_module' => 'refresh', 'first_response_hours' => 8, 'resolution_hours' => 40];
        }, 20, 1);
        $this->assertSame(['first_response_hours' => 8, 'resolution_hours' => 40, 'source' => 'hook:refresh'], Sla::targets());
        // invalid answers are ignored
        \Eventy::addFilter('fsinterop.sla.targets', function ($targets) {
            return ['first_response_hours' => 0, 'resolution_hours' => 'x'];
        }, 30, 1);
        $this->assertNotSame('hook:refresh', Sla::source());
    }

    public function testNavigationContext()
    {
        $bob = $this->s['bob'];
        $this->actingAs($bob);
        // RefreshGlobal claims its surfaces on its own pages only
        $ctx = NavigationContext::resolve(NavigationContext::base('refreshglobal.tickets', [], $bob->id));
        $this->assertSame('global-mailboxes', $ctx['context']);
        $this->assertSame('refresh-global', $ctx['owners']['saved-views@global-mailboxes']);
        $mailbox = NavigationContext::resolve(NavigationContext::base('mailboxes.view', ['id' => (string) $this->s['support']->id], $bob->id));
        $this->assertSame(['mailbox', $this->s['support']->id, []], [$mailbox['context'], $mailbox['mailbox_id'], $mailbox['owners']]);
        $ticket = NavigationContext::base('conversations.view', ['id' => '42'], $bob->id);
        $this->assertSame(['conversation', 42], [$ticket['context'], $ticket['conversation_id']]);

        // a claim made before RefreshGlobal's is kept, never overwritten
        \Eventy::addFilter('fsinterop.navigation.context', function ($context) {
            $context['owners']['search@global-mailboxes'] = 'other-module';

            return $context;
        }, 10, 1);
        // a listener can neither change the base fields nor add malformed owners
        \Eventy::addFilter('fsinterop.navigation.context', function ($context) {
            $context['owners']['bad key'] = 'x';
            $context['context'] = 'mailbox';

            return $context;
        }, 30, 1);
        $ctx = NavigationContext::resolve(NavigationContext::base('refreshglobal.tickets', [], $bob->id));
        $this->assertSame('other-module', $ctx['owners']['search@global-mailboxes']);
        $this->assertSame('refresh-global', $ctx['owners']['ticket-list@global-mailboxes']);
        $this->assertArrayNotHasKey('bad key', $ctx['owners']);
        $this->assertSame('global-mailboxes', $ctx['context']);
    }

    public function testConversationContext()
    {
        $ticket = $this->s['t_support_open'];
        $ctx = ConversationContext::resolve($ticket, $this->s['bob']);
        $this->assertSame(['1.0', $ticket->id, $this->s['support']->id, 'active'], [$ctx['schema'], $ctx['conversation_id'], $ctx['mailbox_id'], $ctx['status']]);
        $this->assertSame(route('refreshglobal.tickets'), $ctx['modules']['refresh-global']['all_mailboxes_url']);
        $this->assertIsArray($ctx['modules']['refresh-global']['mail_tags']);
        // the test mailboxes are not IMAP mailboxes: moving is not offered
        $this->assertSame([], $ctx['modules']['refresh-global']['actions']);

        // rights: nothing for a user who may not view the ticket, also through the raw filter
        $carol = $this->s['carol'];
        $this->assertNull(ConversationContext::resolve($ticket, $carol));
        $raw = \Eventy::filter('fsinterop.conversation.context', ConversationContext::base($ticket, $carol), $ticket, $carol);
        $this->assertArrayNotHasKey('refresh-global', $raw['modules']);

        // other modules add their own entry; the base fields can not be changed
        \Eventy::addFilter('fsinterop.conversation.context', function ($context) {
            $context['modules']['other-module'] = ['x' => 1];
            $context['mailbox_id'] = 999;

            return $context;
        }, 30, 1);
        $ctx = ConversationContext::resolve($ticket, $this->s['bob']);
        $this->assertSame(['x' => 1], $ctx['modules']['other-module']);
        $this->assertSame($this->s['support']->id, $ctx['mailbox_id']);
    }

    public function testPagesGiveTheContractToTheBrowser()
    {
        $page = $this->ticketsPage($this->s['bob'])->getContent();
        $this->assertMatchesRegularExpression('/<meta name="refreshglobal-interop" content="([^"]+)"/', $page);
        preg_match('/<meta name="refreshglobal-interop" content="([^"]+)"/', $page, $m);
        $cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertSame('1.0.0', $cfg['protocol']);
        $this->assertSame(Manifest::own()->capabilities(), $cfg['capabilities']);
        $this->assertSame(Manifest::own()->consumes('refresh')['capabilities'], $cfg['consumes']['capabilities']);
        $this->assertSame(route('refreshglobal.tickets'), $cfg['navigation']['href']);
        // surfaces decided on the server (fsinterop.navigation.context), versions from interop.json
        $this->assertSame('global-mailboxes', $cfg['page']['context']);
        $this->assertSame('refresh-global', $cfg['page']['owners']['ticket-list@global-mailboxes']);
        $this->assertSame('refresh-global', $cfg['page']['owners']['search@global-mailboxes']);
        $this->assertSame(Manifest::own()->surfaces(), $cfg['surfaces']);
        // search: a RefreshGlobal extension outside the shared protocol (RefreshGlobalInterop.search)
        $this->assertSame('refreshglobal.global-search', $cfg['search']['id']);
        $this->assertSame(route('refreshglobal.search'), $cfg['search']['endpoint']);
        // the bridge, then RefreshGlobal's participation, then shell.js
        $scripts = \Eventy::filter('javascripts', []);
        $order = array_map(function ($s) {
            return basename($s);
        }, array_values(array_filter($scripts, function ($s) {
            return strpos($s, 'refreshglobal') !== false;
        })));
        $this->assertSame(['fs-interop.js', 'interop-adapter.js', 'shell.js'], array_values(array_intersect($order, ['fs-interop.js', 'interop-adapter.js', 'shell.js'])));
        // the diagnostics page shows the cooperation
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('refreshglobal.diagnostic')), 'rg-interop');
    }
}
