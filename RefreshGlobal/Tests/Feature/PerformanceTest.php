<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use App\Thread;
use Carbon\Carbon;
use Modules\RefreshGlobal\Services\Dashboard\Statistics;
use Modules\RefreshGlobal\Services\FlowChanges;
use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\Perf\Hardware;
use Modules\RefreshGlobal\Services\Perf\Probe;
use Modules\RefreshGlobal\Services\Perf\Recorder;
use Modules\RefreshGlobal\Services\SchemaCache;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Services\WorkflowPacksLink;
use Modules\RefreshGlobal\Tests\TestCase;

/** 1.23.0: fewer and lighter requests, performance page and sensors. */
class PerformanceTest extends TestCase
{
    protected function tearDown(): void
    {
        Hardware::$root = '';
        WorkflowPacksLink::$assumeActive = false;
        WorkflowPacksLink::reset();
        parent::tearDown();
    }

    protected function queries(callable $f)
    {
        $n = [];
        \DB::listen(function ($q) use (&$n) {
            $n[] = $q->sql;
        });
        $f();

        return $n;
    }

    /* ---------------------------------------------------------------- optimisations */

    public function testSettingsAreReadInOneQueryAndFollowChanges()
    {
        Settings::forgetOptions();
        $sql = $this->queries(function () {
            for ($i = 0; $i < 5; $i++) {
                Settings::globalDashboard();
                Settings::keepPosition();
                Settings::mailMove();
                Settings::autoRefresh();
            }
        });
        $this->assertCount(1, array_filter($sql, function ($q) {
            return strpos($q, 'options') !== false;
        }));
        // a change (settings page: App\Option::set) is seen at once
        Settings::setKeepPosition(false);
        $this->assertFalse(Settings::keepPosition());
        Settings::setKeepPosition(true);
        $this->assertTrue(Settings::keepPosition());
        \App\Option::set(Settings::AUTO_REFRESH, 60);
        $this->assertSame(60, (int) Settings::option(Settings::AUTO_REFRESH));
    }

    public function testFirstResponseOnlyReadsTheTicketsOfTheDayWithTheSameResult()
    {
        $user = $this->s['admin'];
        $ids = [$this->s['support']->id];
        $base = function () use ($user, $ids) {
            return GlobalDashboard::baseQuery($user, $ids);
        };
        $reply = function (Conversation $c, $at) {
            $t = new Thread();
            $t->conversation_id = $c->id;
            $t->type = Thread::TYPE_MESSAGE;
            $t->state = Thread::STATE_PUBLISHED;
            $t->status = Thread::STATUS_ACTIVE;
            $t->body = 'x';
            $t->source_via = Thread::PERSON_USER;
            $t->source_type = Thread::SOURCE_TYPE_WEB;
            $t->created_by_user_id = $this->s['admin']->id;
            $t->save();
            Thread::where('id', $t->id)->update(['created_at' => $at]);
        };
        $today = Carbon::now()->startOfDay()->addMinutes(1);
        $c1 = $this->s['t_support_open'];
        Conversation::where('id', $c1->id)->update(['created_at' => $today->copy()->subMinutes(30), 'updated_at' => Carbon::now()]);
        $reply($c1, $today);
        // answered long ago, answered again today: not a first response of today
        $c2 = $this->s['t_support_pending'];
        Conversation::where('id', $c2->id)->update(['created_at' => Carbon::now()->subDays(20), 'updated_at' => Carbon::now()]);
        $reply($c2, Carbon::now()->subDays(10));
        $reply($c2, $today);
        // untouched for a month: not read
        $c3 = $this->s['t_support_closed'];
        Conversation::where('id', $c3->id)->update(['created_at' => Carbon::now()->subDays(40), 'updated_at' => Carbon::now()->subDays(30), 'last_reply_at' => Carbon::now()->subDays(30)]);
        $reply($c3, Carbon::now()->subDays(30));

        $this->assertSame(30, (new Statistics($base))->firstResponseMinutes(Carbon::now()->startOfDay()));
    }

    public function testFingerprintAsksForTheAggregatesOnly()
    {
        $access = new MailboxAccess($this->s['admin']);
        $q = new GlobalTicketQuery($access, GlobalTicketQuery::normalize([], $access));
        $sql = $this->queries(function () use ($q) {
            $q->fingerprint();
        });
        $this->assertStringNotContainsString('.*', end($sql));
        $before = $q->fingerprint();
        Conversation::where('id', $this->s['t_support_open']->id)->update(['updated_at' => Carbon::now()->addMinute()]);
        $this->assertNotSame($before, $q->fingerprint());
    }

    public function testSchemaAnswersAreKeptOnlyWhenPositive()
    {
        SchemaCache::forget();
        $this->assertTrue(SchemaCache::has('refreshglobal_saved_views', ['visibility']));
        $this->assertCount(0, $this->queries(function () {
            SchemaCache::has('refreshglobal_saved_views', ['visibility']);
        }));
        $this->assertFalse(SchemaCache::has('refreshglobal_no_such_table'));
        $this->assertNotCount(0, $this->queries(function () {
            SchemaCache::has('refreshglobal_no_such_table');
        }));
        $this->assertFalse(SchemaCache::has('refreshglobal_saved_views', ['no_such_column']));
    }

    public function testDeletedFlowsAreReadByTheScheduledTaskNotByAClick()
    {
        WorkflowPacksLink::$assumeActive = true;
        \Eventy::addFilter('workflowpacks.info', function () {
            return ['version' => '3.20.0', 'api' => 1, 'engine' => true, 'max_tickets' => 5000];
        }, 1000, 1);
        $calls = 0;
        \Eventy::addFilter('workflowpacks.flow_stats', function () use (&$calls) {
            $calls++;

            return ['ok' => true, 'flows' => [['flow_id' => 91, 'exists' => false]]];
        }, 1000, 2);
        WorkflowPacksLink::reset();
        $t = $this->s['t_support_open'];
        \DB::table(FlowChanges::TABLE)->insert(['event_id' => 990000001, 'field' => 'status', 'conversation_id' => $t->id, 'mailbox_id' => $t->mailbox_id,
            'flow_id' => 91, 'flow_name' => 'PERF', 'after' => 'closed', 'origin' => 'new_mail', 'temporary' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        $access = new MailboxAccess($this->s['admin']);
        FlowChanges::stats(FlowChanges::filters([], $access), $access);
        $this->assertSame(0, $calls);
        \Artisan::call('refreshglobal:flow-history', ['--requested' => true]);
        \Artisan::call('refreshglobal:flow-history', ['--requested' => true]);
        $this->assertSame(1, $calls, 'every 6 hours');
        $row = array_values(array_filter(FlowChanges::computeStats(FlowChanges::filters(['flow' => 91], $access), $access)['flows'], function ($r) {
            return $r['flow_id'] === 91;
        }))[0];
        $this->assertFalse($row['exists']);
    }

    /* ---------------------------------------------------------------- page and sensors */

    public function testPageAndRights()
    {
        $this->actingAs($this->s['bob'])->get(route('refreshglobal.performance'))->assertStatus(403);
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.performance.record'), ['minutes' => 15])->assertStatus(403);
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.performance.clear'))->assertStatus(403);
        $r = $this->actingAs($this->s['admin'])->get(route('refreshglobal.performance'));
        $r->assertStatus(200);
        foreach (['db_ping', 'db_write', 'opcache', 'cpu', 'disk', 'cache', 'sizes'] as $key) {
            $this->seeIn($r, 'data-key="'.$key.'"');
        }
        $this->dontSeeIn($r, 'refreshglobal::perf.', 'every text is translated');
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('refreshglobal.diagnostic')), route('refreshglobal.performance'));
        $this->assertSame(0, \DB::table(Recorder::TABLE)->where('route', 'probe')->count(), 'the probe leaves nothing');
        // every warning has its advice
        foreach (array_merge(Hardware::items(), Probe::server()) as $i) {
            if (in_array($i['level'], ['warn', 'bad'], true)) {
                $this->assertNotSame('refreshglobal::perf.advice_'.$i['key'], __('refreshglobal::perf.advice_'.$i['key']));
            }
        }
    }

    public function testRecordingStartsAndStops()
    {
        $this->assertFalse(Recorder::active());
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.performance.record'), ['minutes' => 15])->assertStatus(302);
        $this->assertTrue(Recorder::active());
        $this->assertLessThanOrEqual(time() + 900, Recorder::until());
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.performance.record'), ['minutes' => 0]);
        $this->assertFalse(Recorder::active());
        // over: stops by itself
        \App\Option::set(Recorder::OPTION, time() - 1);
        $this->assertFalse(Recorder::active());
        $this->assertFalse((bool) strpos($this->ticketsPage($this->s['admin'])->getContent(), 'name="refreshglobal-perf"'));
    }

    public function testMeasuresHoldNoPersonalData()
    {
        Recorder::start(15);
        Recorder::begin();
        Recorder::sql("select * from customers where email = 'private@example.test' and id in (12, 13) and phone = \"555\"", 150.0);
        Recorder::sql('select 1', 1.0);
        Recorder::sql('select 1', 1.0);
        $request = \Illuminate\Http\Request::create('/conversation/123456?secret=abc', 'GET');
        $response = response('x');
        Recorder::finish($request, $response);
        $row = \DB::table(Recorder::TABLE)->orderBy('id', 'desc')->first();
        $this->assertSame(3, (int) $row->sql_n);
        $this->assertSame(1, (int) $row->repeats);
        $json = json_encode($row);
        foreach (['private@', '123456', 'secret', '555'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertStringContainsString('email = ?', $json);
        $this->assertStringContainsString('total;dur=', (string) $response->headers->get('Server-Timing'));

        // what the browser saw: once, for an id that exists, sane values
        $post = function (array $data) {
            return $this->actingAs($this->s['bob'])->post(route('refreshglobal.performance.browser'), $data)->json()['status'];
        };
        $this->assertSame('ignored', $post(['id' => 'zz', 'ttfb' => 1, 'ready' => 1, 'load' => 1]));
        $this->assertSame('ignored', $post(['id' => str_repeat('0', 16), 'ttfb' => 1, 'ready' => 1, 'load' => 1]));
        $this->assertSame('success', $post(['id' => $row->uid, 'ttfb' => 120.4, 'ready' => 300, 'load' => -5]));
        $this->assertSame('ignored', $post(['id' => $row->uid, 'ttfb' => 999, 'ready' => 999, 'load' => 999]));
        $row = \DB::table(Recorder::TABLE)->where('id', $row->id)->first();
        $this->assertSame(120, (int) $row->b_ttfb);
        $this->assertNull($row->b_load);
        Recorder::stop();
        $this->assertSame('ignored', $post(['id' => $row->uid, 'ttfb' => 1, 'ready' => 1, 'load' => 1]));
    }

    public function testSummaryTellsWhereTheTimeGoes()
    {
        Recorder::clear();
        for ($i = 0; $i < 4; $i++) {
            \DB::table(Recorder::TABLE)->insert(['uid' => bin2hex(random_bytes(8)), 'route' => 'conversations.view', 'total' => 900, 'sql_n' => 80, 'sql_ms' => 700,
                'b_ttfb' => 1000, 'b_load' => 1200, 'details' => json_encode(['modules' => ['Refresh' => [20, 100.0]], 'slow' => [['Refresh', 150, 'select ?']]]), 'created_at' => date('Y-m-d H:i:s')]);
        }
        $s = Recorder::summary();
        $this->assertSame(['sql' => 700, 'php' => 200, 'wait' => 100, 'browser' => 200, 'n' => 4], $s['share']);
        $this->assertSame(900, (int) $s['pages'][0]['median']);
        $this->assertSame('Refresh', $s['modules'][0]['module']);
        $this->assertSame(4, $s['slow'][0]['n']);
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('refreshglobal.performance')), e(__('refreshglobal::perf.verdict_sql')));
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.performance.clear'))->assertStatus(302);
        $this->assertSame(0, \DB::table(Recorder::TABLE)->count());
    }

    public function testHardwareOfAContainer()
    {
        $root = sys_get_temp_dir().'/rg-hw-'.bin2hex(random_bytes(4));
        $files = [
            '/proc/sys/kernel/osrelease' => '6.8.12-4-pve', '/run/systemd/container' => 'lxc',
            '/proc/cpuinfo' => "processor\t: 0\nmodel name\t: Test CPU\ncpu MHz\t\t: 1800.0\nprocessor\t: 1\n",
            '/proc/meminfo' => "MemTotal: 1000000 kB\nMemAvailable: 30000 kB\nSwapTotal: 0 kB\nSwapFree: 0 kB\n",
            '/proc/stat' => 'cpu  100 0 100 700 80 0 0 20 0 0', '/sys/fs/cgroup/cpu.max' => '50000 100000',
            '/sys/fs/cgroup/io.pressure' => "some avg10=30.00 avg60=20.00 avg300=12.00 total=1\nfull avg10=0.00 avg60=0.00 avg300=0.00 total=0",
            '/proc/mounts' => "rpool/data/subvol-1 / zfs rw,relatime 0 0\n", '/sys/block/sda/queue/rotational' => '1', '/sys/block/sda/device/model' => 'TEST DISK',
            '/proc/sys/kernel/hostname' => 'private-host-name',
        ];
        foreach ($files as $path => $content) {
            @mkdir(dirname($root.$path), 0700, true);
            file_put_contents($root.$path, $content);
        }
        Hardware::$root = $root;
        $items = [];
        foreach (Hardware::items() as $i) {
            $items[$i['key']] = $i;
        }
        Hardware::$root = '';
        foreach (array_keys($files) as $path) {
            unlink($root.$path);
        }
        $this->assertStringContainsString('container lxc, Proxmox', $items['machine']['value']);
        $this->assertStringContainsString('Test CPU × 2', $items['processor']['value']);
        $this->assertSame('bad', $items['cpu_limit']['level']);
        $this->assertSame('bad', $items['memory']['level']);
        $this->assertSame('bad', $items['pressure_io']['level']);
        $this->assertStringContainsString('zfs — TEST DISK HDD', $items['disk_type']['value']);
        $this->assertArrayNotHasKey('pressure_cpu', $items, 'what can not be read is left out');
        $this->assertStringNotContainsString('private-host-name', json_encode($items));
    }
}
