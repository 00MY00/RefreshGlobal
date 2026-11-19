<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Carbon\Carbon;
use Modules\RefreshGlobal\Services\Dashboard\Chart;
use Modules\RefreshGlobal\Services\Dashboard\Sla;
use Modules\RefreshGlobal\Services\Dashboard\TicketViews;
use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Dashboard of all mailboxes WITHOUT Refresh (docs/INTEROPERABILITY.md §2.1): the module's own quick views, figures,
 * chart and SLA targets, and its place above FreeScout's dashboard when Refresh is not active.
 * Refresh is switched off in memory only (App\Module::setActive(…, false, false)), never in the database.
 */
class DashboardStandaloneTest extends TestCase
{
    protected $refreshWasActive = false;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::setGlobalDashboard(true);
        $this->refreshWasActive = \App\Module::isActive('refresh');
        if ($this->refreshWasActive) {
            \App\Module::setActive('refresh', false, false);
        }
    }

    protected function tearDown(): void
    {
        if ($this->refreshWasActive) {
            \App\Module::setActive('refresh', true, false);
        }
        parent::tearDown();
    }

    public function testDashboardShownAboveFreeScoutsWithoutRefresh()
    {
        $r = $this->actingAs($this->s['bob'])->get(route('dashboard'));
        $r->assertStatus(200);
        $this->seeIn($r, 'rg-dash rg-dash-standalone');
        $this->seeIn($r, 'refreshglobal-dashboard.css');
        $this->seeIn($r, 'class="rf-chart"');
        $this->seeIn($r, $this->s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($r, $this->s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($r, $this->s['prefix'].'-BILLING-');
        // tiles lead to the "All mailboxes" page, whose quick views work without Refresh
        $this->seeIn($r, e(route('refreshglobal.tickets', ['rv' => 'overdue'])));
        // the page tells the browser that RefreshGlobal owns the global dashboard
        $this->seeIn($r, 'dashboard@global-mailboxes');

        Settings::setGlobalDashboard(false);
        $this->dontSeeIn($this->actingAs($this->s['bob'])->get(route('dashboard')), 'rg-dash-standalone');
    }

    public function testQuickViewsWithoutRefresh()
    {
        $p = $this->s['prefix'];
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'pending']);
        $this->seeIn($r, $p.'-SUPPORT-PENDING');
        $this->dontSeeIn($r, $p.'-SUPPORT-OPEN');
        $this->seeIn($r, 'rg-rview-chip');

        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'unassigned']);
        $this->seeIn($r, $p.'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $p.'-SUPPORT-PENDING'); // assigned to bob
        // rights still apply
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'unresolved', 'mb' => [$this->s['billing']->id]]);
        $this->dontSeeIn($r, $p.'-BILLING-');
    }

    public function testOverdueAndNewFollowTheSla()
    {
        // Refresh is switched off in memory only, its listeners stay registered: silence its SLA answer, as without Refresh
        \Eventy::addFilter('fsinterop.sla.targets', function () {
            return null;
        }, 1000, 1);
        $old = Fixtures::conversation($this->s['support'], 'sla old', ['created_at' => Carbon::now()->subHours(Sla::DEFAULT_RESOLUTION_HOURS + 2)]);
        $fresh = Fixtures::conversation($this->s['support'], 'sla fresh');
        $ids = function ($view) {
            return TicketViews::apply(GlobalDashboard::baseQuery($this->s['bob'], [$this->s['support']->id]), $view)->pluck('conversations.id')->all();
        };
        $this->assertContains($old->id, $ids('overdue'));
        $this->assertNotContains($fresh->id, $ids('overdue'));
        $this->assertContains($fresh->id, $ids('new'), 'never answered');
        $this->assertSame('refreshglobal', Sla::source());

        // the module's own targets apply without Refresh
        \App\Option::set(Sla::RESOLUTION, 1000);
        \App\Option::set(Sla::FIRST_RESPONSE, 1000);
        $this->assertSame(1000, Sla::resolutionHours());
        $this->assertNotContains($old->id, $ids('overdue'));
        \App\Option::set(Sla::RESOLUTION, -5);
        $this->assertSame(Sla::DEFAULT_RESOLUTION_HOURS, Sla::resolutionHours(), 'invalid value: default');
    }

    public function testTilesAndStatistics()
    {
        $base = function () {
            return GlobalDashboard::baseQuery($this->s['bob'], [$this->s['support']->id, $this->s['sales']->id]);
        };
        $tiles = [];
        foreach (GlobalDashboard::tiles($base) as $tile) {
            $tiles[$tile['key']] = $tile['count'];
        }
        $this->assertSame(GlobalDashboard::TILES, array_keys($tiles));
        $this->assertSame($tiles['open'] + $tiles['pending'], $tiles['unresolved']);
        $this->assertGreaterThanOrEqual(1, $tiles['pending']);

        $summary = (new \Modules\RefreshGlobal\Services\Dashboard\Statistics($base))->summary();
        $this->assertCount(24, $summary['h_today']);
        $this->assertGreaterThanOrEqual(1, $summary['stats']['received'][0], 'the scenario tickets were created today');
        $this->assertSame(array_sum($summary['h_today']), $summary['stats']['received'][0]);
        $this->assertSame(1, $summary['stats']['resolved'][0]);
        $this->assertSame(100, $summary['stats']['sla'][0]);
    }

    /**
     * Closed tickets (1.19.0): "Closed today" = the tickets of the "Resolved" figure, "Closed" = every closed ticket;
     * both are tiles and quick views of the "All mailboxes" page, within the user's rights.
     */
    public function testClosedViews()
    {
        $p = $this->s['prefix'];
        $closed = ['status' => Conversation::STATUS_CLOSED];
        $today = Fixtures::conversation($this->s['support'], $p.'-CLOSED-TODAY', $closed + ['closed_at' => Carbon::now()->toDateTimeString()]);
        $old = Fixtures::conversation($this->s['support'], $p.'-CLOSED-OLD', $closed + ['closed_at' => Carbon::now()->subDays(3)->toDateTimeString()]);
        $hidden = Fixtures::conversation($this->s['billing'], $p.'-CLOSED-BILLING', $closed + ['closed_at' => Carbon::now()->toDateTimeString()]);
        $base = function () {
            return GlobalDashboard::baseQuery($this->s['bob'], [$this->s['support']->id, $this->s['sales']->id]);
        };
        $ids = function ($view) use ($base) {
            return TicketViews::apply($base(), $view)->pluck('conversations.id')->all();
        };
        $this->assertContains($today->id, $ids('closed-today'));
        $this->assertNotContains($old->id, $ids('closed-today'));
        $this->assertContains($today->id, $ids('closed'));
        $this->assertContains($old->id, $ids('closed'));
        $this->assertNotContains($hidden->id, $ids('closed'), 'mailbox not allowed');
        $this->assertTrue(TicketViews::exists('closed-today') && TicketViews::exists('closed'));

        $tiles = [];
        foreach (GlobalDashboard::tiles($base) as $tile) {
            $tiles[$tile['key']] = $tile;
        }
        $summary = (new \Modules\RefreshGlobal\Services\Dashboard\Statistics($base))->summary();
        $this->assertSame($summary['stats']['resolved'][0], $tiles['closed-today']['count'], 'same tickets as "Resolved"');
        $this->assertSame(route('refreshglobal.tickets', ['rv' => 'closed']), $tiles['closed']['url']);
        $this->assertNotContains($today->id, $ids('unresolved'), 'closed tickets stay out of the other views');

        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'closed-today']);
        $this->seeIn($r, $p.'-CLOSED-TODAY');
        $this->dontSeeIn($r, $p.'-CLOSED-OLD');
        $this->dontSeeIn($r, $p.'-SUPPORT-OPEN');
        $this->seeIn($r, 'rg-rview-chip');
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'closed', 'mb' => [$this->s['billing']->id]]);
        $this->dontSeeIn($r, $p.'-CLOSED-BILLING');
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'closed']);
        $this->seeIn($r, $p.'-CLOSED-OLD');
        $this->dontSeeIn($r, $p.'-CLOSED-BILLING');

        $this->seeIn($this->actingAs($this->s['bob'])->get(route('dashboard')), e(route('refreshglobal.tickets', ['rv' => 'closed-today'])));
    }

    /**
     * "Today" follows the time zone FreeScout stores its dates in (APP_TIMEZONE), whatever it is: a ticket created just
     * after midnight counts today, one created just before midnight counts yesterday (until 1.17.2 the bounds were
     * converted to UTC, shifting the day by the offset).
     */
    public function testDaysFollowTheApplicationTimeZone()
    {
        $previous = config('app.timezone');
        config(['app.timezone' => 'Pacific/Auckland']); // +12/+13: any UTC conversion would be visible
        date_default_timezone_set('Pacific/Auckland');
        try {
            $mailbox = Fixtures::mailbox($this->s['prefix'].' TZ');
            $today = Carbon::now('Pacific/Auckland')->startOfDay();
            $afterMidnight = Fixtures::conversation($mailbox, 'tz after midnight', ['created_at' => $today->copy()->addMinutes(10)->toDateTimeString()]);
            $beforeMidnight = Fixtures::conversation($mailbox, 'tz before midnight', ['created_at' => $today->copy()->subMinutes(10)->toDateTimeString()]);
            $stats = new \Modules\RefreshGlobal\Services\Dashboard\Statistics(function () use ($mailbox) {
                return \App\Conversation::where('mailbox_id', $mailbox->id);
            });
            $this->assertSame(1, $stats->received($today), 'the ticket created at 00:10 counts today');
            $this->assertSame(1, $stats->received($today->copy()->subDay()), 'the ticket created at 23:50 counts yesterday');
            $hours = $stats->createdPerHour($today);
            $this->assertSame(1, $hours[0], 'created during hour 0 of today');
            $this->assertNotNull($afterMidnight->id);
            $this->assertNotNull($beforeMidnight->id);
        } finally {
            config(['app.timezone' => $previous]);
            date_default_timezone_set($previous);
        }
    }

    public function testChartAndFormats()
    {
        $svg = Chart::hourly(array_fill(0, 24, 2), array_fill(0, 24, 1), 5);
        $this->assertStringStartsWith('<svg class="rf-chart"', $svg);
        $this->assertSame(1, substr_count($svg, 'class="rf-chart-today"'));
        $this->assertSame(6, substr_count($svg, 'rf-chart-today-dot'), 'today: hours 0 to 5');
        $this->assertSame(24, substr_count($svg, 'rf-chart-yest-dot'));
        $this->assertSame(['50%', 'up'], Chart::delta(3, 2));
        $this->assertSame(['', null], Chart::delta(3, 0));
        app()->setLocale('en');
        $this->assertSame('--', Chart::duration(null));
        $this->assertSame('12 min', Chart::duration(12));
        $this->assertSame('3 h 5 min', Chart::duration(185));
        $this->assertSame('2 d 1 h', Chart::duration(49 * 60));
    }

    public function testUndeliveredCard()
    {
        $t = $this->s['t_sales_open'];
        \App\Thread::where('conversation_id', $t->id)->delete();
        $thread = new \App\Thread();
        $thread->conversation_id = $t->id;
        $thread->type = \App\Thread::TYPE_MESSAGE;
        $thread->state = \App\Thread::STATE_PUBLISHED;
        $thread->status = \App\Thread::STATUS_ACTIVE;
        $thread->body = 'reply';
        $thread->created_by_user_id = $this->s['bob']->id;
        $thread->send_status = \App\SendLog::STATUS_SEND_ERROR;
        $thread->source_via = \App\Thread::PERSON_USER;
        $thread->source_type = \App\Thread::SOURCE_TYPE_WEB;
        $thread->save();

        $base = function () {
            return GlobalDashboard::baseQuery($this->s['bob'], [$this->s['support']->id, $this->s['sales']->id]);
        };
        $rows = GlobalDashboard::undeliveredPerMailbox($base, collect([$this->s['support'], $this->s['sales']]));
        $this->assertCount(1, $rows);
        $this->assertSame([$this->s['sales']->name, 1], [$rows[0]['name'], $rows[0]['n']]);
        $this->assertSame(route('refreshglobal.tickets', ['rv' => 'undelivered', 'mb' => [$this->s['sales']->id]]), $rows[0]['url']);
        $this->assertSame(Conversation::STATUS_ACTIVE, (int) $t->fresh()->status);
    }
}
