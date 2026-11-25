<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\FlowTimers;
use Modules\RefreshGlobal\Services\SchemaCache;
use Modules\RefreshGlobal\Services\WorkflowPacksLink;
use Modules\RefreshGlobal\Tests\TestCase;

/** Tickets waiting in a flow of Workflow Packs (1.28.0). The tests that read its tables are skipped when it is not installed. */
class FlowTimersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WorkflowPacksLink::$assumeActive = false;
        WorkflowPacksLink::reset();
        FlowTimers::reset();
        SchemaCache::forget();
    }

    protected function tearDown(): void
    {
        WorkflowPacksLink::$assumeActive = false;
        WorkflowPacksLink::reset();
        FlowTimers::reset();
        SchemaCache::forget();
        parent::tearDown();
    }

    protected function wp($engine = true)
    {
        if (!\Schema::hasTable(FlowTimers::RUNS)) {
            $this->markTestSkipped('Workflow Packs 3.20+ is not installed.');
        }
        WorkflowPacksLink::$assumeActive = true;
        \Eventy::addFilter('workflowpacks.info', function () use ($engine) {
            return ['version' => '4.0.2', 'api' => 1, 'engine' => $engine, 'max_tickets' => 5000];
        }, 1000, 1);
        WorkflowPacksLink::reset();
    }

    protected function data(array $blocks, array $links)
    {
        $out = ['blocks' => [], 'links' => []];
        foreach ($blocks as $id => $b) {
            $out['blocks'][] = ['id' => $id, 'type' => $b[0], 'params' => $b[1] ?? []];
        }
        foreach ($links as $l) {
            $out['links'][] = ['from' => $l[0], 'port' => $l[2] ?? 'next', 'to' => $l[1]];
        }

        return $out;
    }

    protected function waiting($conversation, array $data, $minutes = 30, $block = 'w')
    {
        $now = date('Y-m-d H:i:s');
        $flow = \DB::table(FlowTimers::FLOWS)->insertGetId(['name' => 'Flow <b>one</b>', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $version = \DB::table(FlowTimers::VERSIONS)->insertGetId(['flow_id' => $flow, 'number' => 1, 'data' => json_encode($data), 'created_at' => $now, 'updated_at' => $now]);
        \DB::table(FlowTimers::RUNS)->insert(['flow_id' => $flow, 'version_id' => $version, 'conversation_id' => $conversation->id, 'block' => $block, 'state' => 'waiting',
            'wake_at' => \Carbon\Carbon::now()->addSeconds($minutes * 60)->toDateTimeString(), 'resume' => 'next', 'created_at' => $now, 'updated_at' => $now]);
        FlowTimers::reset();
    }

    public function testNextSteps()
    {
        $next = function ($blocks, $links, $resume = 'next') {
            $r = FlowTimers::next(FlowTimers::graph(json_encode($this->data($blocks, $links))), 'w', $resume);

            return [implode(' | ', $r['steps']), $r['depends']];
        };
        $w = ['wait.duration', ['delay' => ['number' => 1, 'metric' => 'h']]];
        $close = ['action.status', ['status' => 'closed']];
        $this->assertSame(['Status: Closed', false], $next(['w' => $w, 'a' => $close], [['w', 'a']]));
        $this->assertSame(['Status: Closed → Internal note → Another wait', false], $next(['w' => $w, 'a' => $close, 'n' => ['action.note', ['text' => 'SECRET']], 'x' => $w], [['w', 'a'], ['a', 'n'], ['n', 'x']]));
        $this->assertSame(['Status: Closed | no further action', true], $next(['w' => $w, 'c' => ['condition.ticket'], 'a' => $close], [['w', 'c'], ['c', 'a', 'yes']]));
        $this->assertSame(['Remove the assignment | Move to the Deleted folder', true], $next(['w' => $w, 'c' => ['condition.ticket'], 'a' => ['action.assign', ['user' => '-1']], 'd' => ['action.delete']], [['w', 'c'], ['c', 'a', 'yes'], ['c', 'd', 'no']]));
        $this->assertSame(['no further action', false], $next(['w' => $w], []));
        $this->assertSame(['Another action', false], $next(['w' => $w, 'a' => ['action.of_another_module']], [['w', 'a']]));
        $this->assertSame(['Another action', false], $next(['w' => $w, 'a' => ['action.status', ['status' => ['x']]]], [['w', 'a']]));
        // the block itself again (retry), a loop of conditions, broken data
        $this->assertSame(['Status: Closed', false], $next(['w' => $close], [], null));
        $this->assertSame(['no further action', true], $next(['w' => $w, 'c' => ['condition.ticket']], [['w', 'c'], ['c', 'c', 'yes']]));
        foreach (['', 'x', '[]', '{"blocks":"x","links":[1]}', null] as $broken) {
            $this->assertSame(['no further action'], FlowTimers::next(FlowTimers::graph($broken), 'w', 'next')['steps']);
        }
    }

    public function testTimeLeft()
    {
        foreach ([[-5, 'now'], [0, 'now'], [20, '1 min'], [1800, '30 min'], [3600, '1 h'], [3900, '1 h 05'], [47 * 3600, '47 h'], [51 * 3600, '2 d 3 h'], [72 * 3600, '3 d']] as $c) {
            $this->assertSame($c[1], FlowTimers::left($c[0]));
        }
    }

    public function testBadgeInTheListAndOnTheTicket()
    {
        $this->wp();
        $c = $this->s['t_support_open'];
        $this->waiting($c, $this->data(['w' => ['wait.duration'], 'a' => ['action.status', ['status' => 'closed']], 'n' => ['action.note', ['text' => 'SECRET']]], [['w', 'a']]));
        $html = $this->actingAs($this->s['bob'])->get(route('refreshglobal.tickets'))->getContent();
        $this->assertStringContainsString('>30 min → Status: Closed</span>', $html);
        $this->assertStringContainsString('name="refreshglobal-timer"', $html);
        $this->assertSame(1, substr_count($html, 'data-rg-timer='));
        $this->assertStringNotContainsString('<b>one', $html);
        $this->assertStringNotContainsString('SECRET', $html);
        FlowTimers::reset();
        $this->assertStringContainsString('>30 min → Status: Closed</span>', $this->actingAs($this->s['bob'])->get($c->url())->getContent());

        // one read of the runs for a page
        FlowTimers::reset();
        \DB::enableQueryLog();
        $this->actingAs($this->s['bob'])->get(route('refreshglobal.tickets'));
        $n = count(array_filter(\DB::getQueryLog(), function ($q) {
            return strpos($q['query'], FlowTimers::RUNS) !== false && stripos($q['query'], 'information_schema') === false;
        }));
        \DB::disableQueryLog();
        $this->assertSame(1, $n);

        // finished: nothing
        \DB::table(FlowTimers::RUNS)->where('conversation_id', $c->id)->update(['state' => 'done']);
        FlowTimers::reset();
        $this->assertSame([], FlowTimers::forConversations([$c->id]));
    }

    public function testNothingWithoutTheEngine()
    {
        $this->wp(false);
        $c = $this->s['t_support_open'];
        $this->waiting($c, $this->data(['w' => ['wait.duration']], []));
        $this->assertSame([], FlowTimers::forConversations([$c->id]));
        $this->assertStringNotContainsString('rg-timer', $this->actingAs($this->s['bob'])->get(route('refreshglobal.tickets'))->getContent());
    }
}
