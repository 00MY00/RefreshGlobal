<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Services\Interop\Manifest;
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
            'provides' => ['capabilities' => ['a' => 'z'], 'schemas' => ['s' => ['version' => '1.0', 'file' => 'nope.json']],
                'registrations' => ['toolbar' => [['id' => 'd'], ['id' => 'd']]]],
            'consumes' => ['optional' => ['refresh' => ['protocol' => '~1']]]],
            'compatibility' => ['freescout' => '>=1.8', 'php' => '>=7.1', 'peers' => ['refresh' => ['required' => 'no']]],
            'updateguard' => ['health' => ['command' => 'x', 'json_command' => 'y', 'exit_codes' => ['healthy' => 1], 'read_only' => true, 'network' => true]]],
            sys_get_temp_dir());
        $errors = implode("\n", $bad->validate());
        foreach (['manifest_version', 'module.id', 'not a version', 'capability a', 'nope.json', 'duplicate id d', 'invalid protocol range',
            'required must be true or false', 'healthy must be 0', 'read_only and without network'] as $expected) {
            $this->assertStringContainsString($expected, $errors);
        }
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
        $all = ['navigation' => '1.0', 'navigation-context' => '1.0', 'ticket-toolbar' => '1.2', 'swipe-actions' => '1.0', 'conversation-ui' => '1.0'];
        $s = $this->cooperation(['version' => '1.8.0'], $this->peer('1.0.0', $all));
        $this->assertSame(['native', 'COMPATIBLE', false], [$s['mode'], $s['compatibility'], $s['legacy_fallbacks']]);

        // native but a capability missing: that one is unavailable (version outside the tested legacy range), degraded
        $s = $this->cooperation(['version' => '1.8.0'], $this->peer('1.0.0', array_diff_key($all, ['swipe-actions' => 1])));
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
