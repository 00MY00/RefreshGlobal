<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Services\FlowChanges;
use Modules\RefreshGlobal\Services\FlowTemp;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\WorkflowPacksLink;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Flows of Workflow Packs 3.20+ (1.20.0): statistics, untreated e-mails, temporary flows.
 * Workflow Packs is simulated with its documented Eventy contract (docs/api-flux.md of Workflow Packs): the filters
 * answer at priority 1000 (after any real listener), the action is fired with \Eventy::action. Without it (switched
 * off in memory only), nothing exists.
 */
class FlowStatsTest extends TestCase
{
    protected $wpWasActive = false;
    /** @var array calls received by the simulated filters */
    protected $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        WorkflowPacksLink::$assumeActive = false;
        WorkflowPacksLink::reset();
        FlowChanges::forgetRegistration();
        FlowChanges::$batch = WorkflowPacksLink::MAX_IDS;
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        if ($this->wpWasActive) {
            \App\Module::setActive('workflowpacks', true, false);
        }
        WorkflowPacksLink::$assumeActive = false;
        WorkflowPacksLink::reset();
        FlowChanges::forgetRegistration();
        FlowChanges::$batch = WorkflowPacksLink::MAX_IDS;
        parent::tearDown();
    }

    /** Workflow Packs 3.20 with its engine on, as seen through workflowpacks.info. */
    protected function present(array $info = ['version' => '3.20.0', 'api' => 1, 'engine' => true, 'max_tickets' => 5000])
    {
        WorkflowPacksLink::$assumeActive = true;
        \Eventy::addFilter('workflowpacks.info', function () use ($info) {
            return $info;
        }, 1000, 1);
        WorkflowPacksLink::reset();
        if (WorkflowPacksLink::available()) {
            FlowChanges::register();
        }
    }

    protected function absent()
    {
        $this->wpWasActive = \App\Module::isActive('workflowpacks');
        if ($this->wpWasActive) {
            \App\Module::setActive('workflowpacks', false, false);
        }
        WorkflowPacksLink::reset();
    }

    protected function change($conversation, array $more = [])
    {
        static $event = 900000000;

        return $more + ['v' => 1, 'conversation_id' => $conversation->id, 'mailbox_id' => $conversation->mailbox_id,
            'flow_id' => 12, 'flow_name' => 'Close notifications', 'version' => 3, 'run_id' => 845, 'event_id' => ++$event,
            'block' => 'b4', 'field' => 'status', 'before' => 'active', 'after' => 'closed', 'origin' => 'new_mail',
            'temporary' => false, 'at' => date('Y-m-d H:i:s')];
    }

    protected function rows($conversation)
    {
        return \DB::table(FlowChanges::TABLE)->where('conversation_id', $conversation->id)->get();
    }

    /* ---------------------------------------------------------------- without Workflow Packs 3.20+ */

    public function testNothingExistsWithoutWorkflowPacks()
    {
        $this->absent();
        $this->assertFalse(WorkflowPacksLink::available());

        \DB::enableQueryLog();
        $r = $this->ticketsPage($this->s['admin']);
        $r->assertStatus(200);
        foreach (\DB::getQueryLog() as $q) {
            $this->assertStringNotContainsString('workflowpacks', $q['query'], 'no request to the tables of Workflow Packs');
        }
        \DB::disableQueryLog();
        $this->dontSeeIn($r, 'rg-flows-sec');
        $this->dontSeeIn($r, route('refreshglobal.flows'));

        $this->actingAs($this->s['admin'])->get(route('refreshglobal.flows'))->assertStatus(404);
        $this->actingAs($this->s['admin'])->get(route('refreshglobal.flows.untreated'))->assertStatus(404);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'))->assertStatus(404);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.backfill'), ['days' => 5])->assertStatus(404);

        // the action is not listened to
        \Eventy::action('workflowpacks.ticket_changed', $this->s['t_support_open']->id, $this->change($this->s['t_support_open']));
        $this->assertCount(0, $this->rows($this->s['t_support_open']));
        $this->assertSame(0, \Artisan::call('refreshglobal:flow-history', ['--requested' => true]));
    }

    public function testOlderVersionEngineOffOrNoApiMeansAbsent()
    {
        foreach ([['version' => '3.19.0', 'api' => 1, 'engine' => true], ['version' => '3.20.0', 'api' => 1, 'engine' => false],
            ['version' => '3.20.0', 'api' => 0, 'engine' => true], ['api' => 1, 'engine' => true]] as $info) {
            WorkflowPacksLink::$assumeActive = true;
            \Eventy::addFilter('workflowpacks.info', function () use ($info) {
                return $info;
            }, 1000, 1);
            WorkflowPacksLink::reset();
            $this->assertFalse(WorkflowPacksLink::available(), json_encode($info));
        }
        $this->actingAs($this->s['admin'])->get(route('refreshglobal.flows'))->assertStatus(404);
    }

    /* ---------------------------------------------------------------- recording */

    public function testChangesAreRecordedOnceAndValidated()
    {
        $this->present();
        $t = $this->s['t_support_open'];
        $change = $this->change($t, ['flow_name' => "Close\x07 notifications ".str_repeat('x', 300)]);
        \Eventy::action('workflowpacks.ticket_changed', $t->id, $change);
        \Eventy::action('workflowpacks.ticket_changed', $t->id, $change); // same step and field: once
        $rows = $this->rows($t);
        $this->assertCount(1, $rows);
        $this->assertSame('closed', $rows[0]->after);
        $this->assertSame('new_mail', $rows[0]->origin);
        $this->assertSame((int) $t->mailbox_id, (int) $rows[0]->mailbox_id);
        $this->assertLessThanOrEqual(191, mb_strlen($rows[0]->flow_name));
        $this->assertStringNotContainsString("\x07", $rows[0]->flow_name);

        // same step, another field: a second row
        \Eventy::action('workflowpacks.ticket_changed', $t->id, ['field' => 'note', 'after' => true, 'before' => null] + $change);
        $this->assertCount(2, $this->rows($t));

        // refused: other format version, unknown field name, other ticket in the payload, not an array, unknown values
        foreach ([['v' => 2], ['field' => 'Bad field!'], ['conversation_id' => $t->id + 1], ['event_id' => 'abc'], ['flow_id' => -3]] as $bad) {
            \Eventy::action('workflowpacks.ticket_changed', $t->id, $this->change($t, $bad));
        }
        \Eventy::action('workflowpacks.ticket_changed', $t->id, 'not an array');
        $this->assertCount(2, $this->rows($t));
        \Eventy::action('workflowpacks.ticket_changed', $t->id, $this->change($t, ['after' => 'something else', 'origin' => 'mars', 'at' => 'yesterday']));
        $last = $this->rows($t)->last();
        $this->assertNull($last->after, 'unknown status not kept');
        $this->assertSame('new_mail', $last->origin);
        $this->assertNotNull($last->created_at);

        $temp = $this->change($t, ['origin' => 'temporary', 'temporary' => true, 'field' => 'assignee', 'after' => $this->s['bob']->id]);
        \Eventy::action('workflowpacks.ticket_changed', $t->id, $temp);
        $row = \DB::table(FlowChanges::TABLE)->where('event_id', $temp['event_id'])->first();
        $this->assertSame(1, (int) $row->temporary);
        $this->assertSame((string) $this->s['bob']->id, $row->after);
    }

    public function testDeletedForGoodTicketsLoseTheirRows()
    {
        $this->present();
        $t = $this->s['t_support_open'];
        \Eventy::action('workflowpacks.ticket_changed', $t->id, $this->change($t));
        $this->assertCount(1, $this->rows($t));
        \Eventy::action('conversations.before_delete_forever', [$t->id]);
        $this->assertCount(0, $this->rows($t));
    }

    /* ---------------------------------------------------------------- statistics and rights */

    public function testStatisticsFollowTheMailboxRights()
    {
        $this->present();
        $support = $this->s['t_support_open'];
        $billing = $this->s['t_billing_carol'];
        $billingOther = Fixtures::conversation($this->s['billing'], $this->s['prefix'].'-BILLING-OTHER');
        \Eventy::action('workflowpacks.ticket_changed', $support->id, $this->change($support, ['flow_id' => 71, 'flow_name' => 'SUPPORT-FLOW']));
        \Eventy::action('workflowpacks.ticket_changed', $billing->id, $this->change($billing, ['flow_id' => 72, 'flow_name' => 'BILLING-FLOW']));
        \Eventy::action('workflowpacks.ticket_changed', $billingOther->id, $this->change($billingOther, ['flow_id' => 72, 'flow_name' => 'BILLING-FLOW', 'field' => 'folder', 'after' => 'Invoices']));
        // Workflow Packs says the billing flow was deleted since
        \Eventy::addFilter('workflowpacks.flow_stats', function () {
            return ['ok' => true, 'flows' => [['flow_id' => 72, 'exists' => false], ['flow_id' => 71, 'exists' => true]]];
        }, 1000, 2);

        $r = $this->actingAs($this->s['bob'])->get(route('refreshglobal.flows'));
        $r->assertStatus(200);
        $this->seeIn($r, 'SUPPORT-FLOW');
        $this->dontSeeIn($r, 'BILLING-FLOW');
        // a forced mailbox is ignored
        $this->dontSeeIn($this->actingAs($this->s['bob'])->get(route('refreshglobal.flows', ['mb' => $this->s['billing']->id])), 'BILLING-FLOW');
        $this->dontSeeIn($r, route('refreshglobal.flows.backfill'), 'backfill: administrators only');

        $r = $this->actingAs($this->s['admin'])->get(route('refreshglobal.flows'));
        $this->seeIn($r, 'SUPPORT-FLOW');
        $this->seeIn($r, 'BILLING-FLOW');
        $this->seeIn($r, 'rg-flows-badge-deleted');
        $this->seeIn($r, route('refreshglobal.flows.backfill'));

        // "only assigned": Carol counts only her own ticket
        $access = new MailboxAccess($this->s['carol']);
        $stats = FlowChanges::stats(FlowChanges::filters([], $access), $access);
        $this->assertSame(1, $stats['totals']['tickets']);
        $this->assertSame(1, $stats['totals']['closed']);
        $access = new MailboxAccess($this->s['admin']);
        $stats = FlowChanges::stats(FlowChanges::filters(['flow' => 72], $access), $access);
        $this->assertSame(2, $stats['totals']['tickets']);
        $this->assertSame(1, $stats['totals']['closed']);
        $this->assertSame(1, $stats['flows'][0]['types']['folder']);
        $this->assertFalse($stats['flows'][0]['exists']);
        $stats = FlowChanges::stats(FlowChanges::filters(['origin' => 'reapply'], $access), $access);
        $this->assertSame(0, $stats['totals']['changes'], 'origin filter');
    }

    /* ---------------------------------------------------------------- untreated e-mails and temporary flow */

    protected function fakeUntreated(array $ids, $truncated = false)
    {
        \Eventy::addFilter('workflowpacks.untreated', function ($default = [], $o = []) use ($ids, $truncated) {
            $this->calls['untreated'][] = $o;

            return ['ok' => true, 'total' => count($ids) + ($truncated ? 10 : 0), 'ids' => $ids, 'truncated' => $truncated];
        }, 1000, 2);
    }

    protected function fakeTempFlow(array $answer)
    {
        \Eventy::addFilter('workflowpacks.temp_flow', function ($default = [], $input = []) use ($answer) {
            $this->calls['temp'][] = $input;

            return $answer;
        }, 1000, 2);
    }

    public function testUntreatedListOnlyShowsAllowedTickets()
    {
        $this->present();
        $this->fakeUntreated([$this->s['t_support_open']->id, $this->s['t_billing_carol']->id]);
        $r = $this->actingAs($this->s['bob'])->get(route('refreshglobal.flows.untreated', ['mb' => $this->s['billing']->id]));
        $r->assertStatus(200);
        $this->seeIn($r, $this->s['prefix'].'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $this->s['prefix'].'-BILLING-');
        $asked = $this->calls['untreated'][0]['mailboxes'];
        sort($asked);
        $allowed = (new MailboxAccess($this->s['bob']))->allowedIds();
        sort($allowed);
        $this->assertSame($allowed, $asked, 'Workflow Packs is asked for the allowed mailboxes only');
        $this->dontSeeIn($r, route('refreshglobal.flows.temp'), 'button for administrators only');
        $this->seeIn($r, __('refreshglobal::flows.temp_admins_only'));
    }

    public function testTemporaryFlowIsForAdministratorsOnly()
    {
        $this->present();
        $this->fakeUntreated([$this->s['t_support_open']->id]);
        $this->fakeTempFlow(['ok' => true, 'flow_id' => 57, 'edit_url' => url('/workflow-packs/flows?flow=57')]);
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.flows.temp'))->assertStatus(403);
        $this->assertArrayNotHasKey('temp', $this->calls);
        $this->assertSame('forbidden', FlowTemp::create($this->s['bob'], new MailboxAccess($this->s['bob']), [])['error']);
    }

    public function testTemporaryFlowUsesTheListOfTheServerAndOpensTheEditor()
    {
        $this->present();
        $ids = [$this->s['t_support_open']->id, $this->s['t_billing_carol']->id];
        $this->fakeUntreated($ids);
        $edit = url('/workflow-packs/flows?flow=57');
        $this->fakeTempFlow(['ok' => true, 'flow_id' => 57, 'flow_name' => 'Untreated', 'state' => 'draft', 'tickets' => 2, 'edit_url' => $edit]);
        $r = $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'), ['ticket_ids' => [999999], 'name' => 'Untreated', 'from_flow' => 12]);
        $r->assertRedirect($edit);
        $input = $this->calls['temp'][0];
        sort($ids);
        $this->assertSame($ids, $input['ticket_ids'], 'the list computed by the server, not the one sent');
        $this->assertFalse($input['launch']);
        $this->assertSame('refreshglobal', $input['origin']);
        $this->assertSame(12, $input['from_flow']);
        $this->assertTrue(\DB::table(FlowTemp::TABLE)->where('flow_id', 57)->exists());

        // an editor address outside this FreeScout is never followed
        $this->fakeTempFlow(['ok' => true, 'flow_id' => 58, 'edit_url' => 'https://elsewhere.example.test/flows?flow=58']);
        $r = $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'));
        $r->assertSessionHas('rg_flow_error', 'internal');
        $this->assertSame('', WorkflowPacksLink::safeUrl(rtrim(url('/'), '/').'.example.test/x'));
    }

    public function testEveryErrorCodeHasItsMessage()
    {
        $this->present();
        $this->fakeUntreated([$this->s['t_support_open']->id]);
        foreach (FlowTemp::ERRORS as $code) {
            $this->fakeTempFlow(['ok' => false, 'error' => $code]);
            $r = $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'));
            $r->assertSessionHas('rg_flow_error', $code);
            $page = $this->actingAs($this->s['admin'])->withSession(['rg_flow_error' => $code])->get(route('refreshglobal.flows.untreated'));
            $this->seeIn($page, e(__('refreshglobal::flows.error_'.$code, ['max' => 5000])));
            $this->assertNotSame('refreshglobal::flows.error_'.$code, __('refreshglobal::flows.error_'.$code));
        }
        // an unknown code becomes "internal"
        $this->fakeTempFlow(['ok' => false, 'error' => 'something_new']);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'))->assertSessionHas('rg_flow_error', 'internal');
        // more than 5000 tickets: refused before asking Workflow Packs
        $this->calls = [];
        $this->fakeUntreated([$this->s['t_support_open']->id], true);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'))->assertSessionHas('rg_flow_error', 'too_many_tickets');
        $this->assertArrayNotHasKey('temp', $this->calls);
    }

    public function testTemporaryFlowStateAndEnd()
    {
        $this->present();
        $this->fakeUntreated([$this->s['t_support_open']->id]);
        $this->fakeTempFlow(['ok' => true, 'flow_id' => 61, 'tickets' => 1, 'edit_url' => url('/workflow-packs/flows?flow=61')]);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.temp'));
        \Eventy::addFilter('workflowpacks.temp_flow_status', function () {
            return ['ok' => true, 'flow_id' => 61, 'state' => 'running', 'done' => 1, 'total' => 3, 'edit_url' => url('/workflow-packs/flows?flow=61')];
        }, 1000, 2);
        $recent = FlowTemp::recent();
        $this->assertSame('running', $recent[0]['state']);
        $page = $this->actingAs($this->s['admin'])->get(route('refreshglobal.flows.untreated'));
        $this->seeIn($page, __('refreshglobal::flows.state_running'));
        $this->seeIn($page, 'data-rg-reload');

        \Eventy::action('workflowpacks.temp_flow_finished', 61, ['flow_id' => 61, 'origin' => 'refreshglobal', 'tickets' => 1, 'handled' => 1, 'reason' => 'done']);
        $row = \DB::table(FlowTemp::TABLE)->where('flow_id', 61)->first();
        $this->assertSame('deleted', $row->state);
        $this->assertSame(1, (int) $row->handled);
        $this->assertNotNull($row->finished_at);
    }

    /* ---------------------------------------------------------------- backfill */

    public function testBackfillReadsByBatchesAndIsIdempotent()
    {
        $this->present();
        FlowChanges::$batch = 2;
        for ($i = 0; $i < 5; $i++) {
            Fixtures::conversation($this->s['support'], $this->s['prefix'].'-BACKFILL-'.$i);
        }
        \Eventy::addFilter('workflowpacks.ticket_changes', function ($default = [], $ids = []) {
            $this->calls['changes'][] = $ids;
            $out = [];
            foreach ($ids as $id) {
                $out[$id] = [['v' => 1, 'conversation_id' => $id, 'mailbox_id' => $this->s['support']->id, 'flow_id' => 5,
                    'flow_name' => 'Backfilled', 'event_id' => 800000000 + $id, 'field' => 'status', 'after' => 'closed',
                    'origin' => 'reapply', 'temporary' => false, 'at' => date('Y-m-d H:i:s')]];
            }

            return $out;
        }, 1000, 3);
        $expected = Conversation::where('mailbox_id', $this->s['support']->id)->count();

        $r = FlowChanges::backfill([$this->s['support']->id], 30);
        $this->assertSame($expected, $r['tickets']);
        $this->assertSame($expected, $r['recorded']);
        foreach ($this->calls['changes'] as $batch) {
            $this->assertLessThanOrEqual(2, count($batch));
        }
        $this->assertGreaterThanOrEqual(3, count($this->calls['changes']));
        $this->assertSame(0, FlowChanges::backfill([$this->s['support']->id], 30)['recorded'], 'nothing counted twice');

        // the administrators' button: requested, then run by the scheduled command
        \DB::table(FlowChanges::TABLE)->where('flow_id', 5)->delete();
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.flows.backfill'), ['days' => 30])->assertStatus(403);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.flows.backfill'), ['days' => 30, 'mb' => $this->s['support']->id])
            ->assertRedirect();
        $this->assertSame('requested', FlowChanges::backfillStatus()['state']);
        $this->assertSame(0, \Artisan::call('refreshglobal:flow-history', ['--requested' => true]));
        $status = FlowChanges::backfillStatus();
        $this->assertSame('done', $status['state']);
        $this->assertSame($expected, (int) $status['recorded']);
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('refreshglobal.flows')), 'rg-flows-progress');
    }

    public function testLinksShownWithWorkflowPacks()
    {
        $this->present();
        $r = $this->ticketsPage($this->s['bob']);
        $this->seeIn($r, 'rg-flows-sec');
        $this->seeIn($r, route('refreshglobal.flows.untreated'));
        $this->actingAs($this->s['bob'])->get(route('refreshglobal.flows'))->assertStatus(200);
    }
}
