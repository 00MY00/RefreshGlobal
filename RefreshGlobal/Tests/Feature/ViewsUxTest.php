<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Services\SavedViews;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * 1.22.0: the list opens without closed tickets, "+ New view" dialog, "Reorder" of "My views" (drag and drop / arrows,
 * saved by POST refreshglobal.views.order).
 */
class ViewsUxTest extends TestCase
{
    protected function view($user, $name, array $filters = [])
    {
        $this->actingAs($user)->post(route('refreshglobal.views.store'), $filters + ['name' => $name])->assertStatus(302);

        return SavedView::forUser($user)->where('name', $name)->first();
    }

    protected function order($user)
    {
        return array_values(array_map(function ($v) {
            return $v['name'];
        }, array_filter(SavedViews::visibleFor($user), function ($v) {
            return $v['section'] === 'mine';
        })));
    }

    /* ---------------------------------------------------------------- default statuses */

    public function testListOpensWithoutClosedTickets()
    {
        $p = $this->s['prefix'];
        foreach ([[], ['reset' => 1]] as $query) {
            $r = $this->ticketsPage($this->s['admin'], $query);
            $this->seeIn($r, $p.'-SUPPORT-OPEN');
            $this->seeIn($r, $p.'-SUPPORT-PENDING');
            $this->dontSeeIn($r, $p.'-SUPPORT-CLOSED', 'closed tickets not shown by default');
            $this->dontSeeIn($r, $p.'-SUPPORT-SPAM');
            // not a filter of the user's: no chip, no count, "All mailboxes" stays the active entry
            $this->dontSeeIn($r, 'rg-active-filters');
            $this->dontSeeIn($r, 'status%5B0%5D=1&amp;status%5B1%5D=2', 'default statuses not written in the links');
        }
        // closed ones on demand: status filter (Statuses of the panel), quick view of the dashboard
        $this->seeIn($this->ticketsPage($this->s['admin'], ['status' => [Conversation::STATUS_CLOSED]]), $p.'-SUPPORT-CLOSED');
        $r = $this->ticketsPage($this->s['admin'], ['status' => [1, 2, 3]]);
        $this->seeIn($r, $p.'-SUPPORT-CLOSED');
        $this->seeIn($r, $p.'-SUPPORT-OPEN');
        $this->seeIn($this->ticketsPage($this->s['admin'], ['rv' => 'closed']), $p.'-SUPPORT-CLOSED');
        // another filter keeps the default statuses
        $r = $this->ticketsPage($this->s['admin'], ['mb' => [$this->s['support']->id]]);
        $this->seeIn($r, $p.'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $p.'-SUPPORT-CLOSED');

        // a saved view keeps what it was saved with: no status = every status but spam (views of earlier versions)
        $old = new SavedView();
        $old->user_id = $this->s['admin']->id;
        $old->name = 'Old view';
        $old->filters = ['filters' => ['mailboxes' => [$this->s['support']->id], 'status' => []]];
        $old->schema_version = SavedViews::SCHEMA_VERSION;
        $old->visibility = SavedView::PRIVATE_VIEW;
        $old->sort_order = 10;
        $old->save();
        SavedViews::bump();
        $this->seeIn($this->ticketsPage($this->s['admin'], ['view' => $old->id]), $p.'-SUPPORT-CLOSED');
        // a new view of the list as it opens keeps the default statuses explicitly
        $v = $this->view($this->s['admin'], 'Open ones', ['status' => [1, 2]]);
        $this->assertSame([1, 2], SavedViews::definition($v)['filters']['status']);
        $this->dontSeeIn($this->ticketsPage($this->s['admin'], ['view' => $v->id]), $p.'-SUPPORT-CLOSED');

        // the export and the automatic refresh follow the list
        $response = $this->actingAs($this->s['admin'])->get(route('refreshglobal.export'));
        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString($p.'-SUPPORT-OPEN', $csv);
        $this->assertStringNotContainsString($p.'-SUPPORT-CLOSED', $csv);
    }

    /* ---------------------------------------------------------------- "New view" */

    public function testNewViewIsEasyToFind()
    {
        $r = $this->ticketsPage($this->s['bob']);
        // the dialog and its form, the filters it keeps, "+ New view" at the end of "My views"
        $this->seeIn($r, 'id="rg-new-view"');
        $this->seeIn($r, 'action="'.route('refreshglobal.views.store').'"');
        $this->seeIn($r, 'rg-new-view-open rg-views-new');
        $this->seeIn($r, __('refreshglobal::messages.new_view'));
        $this->seeIn($r, __('refreshglobal::messages.new_view_help'));
        $this->seeIn($r, 'rg-new-view-chips', 'default statuses listed in the summary');
        // no "Reorder" with fewer than two views of one's own
        $this->dontSeeIn($r, 'rg-views-order');

        // with filters: "Save as a view" in the active filters bar, the filters in the summary
        $r = $this->ticketsPage($this->s['bob'], ['mb' => [$this->s['sales']->id], 'q' => 'quote']);
        $this->seeIn($r, 'rg-af-save rg-new-view-open');
        $this->seeIn($r, '« quote »');

        // the management page leads to the dialog
        $this->seeIn($this->actingAs($this->s['bob'])->get(route('refreshglobal.views')), e(route('refreshglobal.tickets')).'#rg-new-view');

        // saving from the dialog: only the name is needed, private by default
        $v = $this->view($this->s['bob'], 'Quotes', ['mb' => [$this->s['sales']->id], 'q' => 'quote', 'status' => [1, 2]]);
        $this->assertSame(SavedView::PRIVATE_VIEW, $v->visibility);
        $this->seeIn($this->ticketsPage($this->s['bob'], ['view' => $v->id]), $this->s['prefix'].'-SALES-OPEN');
    }

    /* ---------------------------------------------------------------- reorder */

    public function testReorderOwnViews()
    {
        $bob = $this->s['bob'];
        $a = $this->view($bob, 'Alpha');
        $b = $this->view($bob, 'Bravo');
        $c = $this->view($bob, 'Charlie');
        $other = $this->view($this->s['admin'], 'Admin view', ['visibility' => 'global']);
        $otherOrder = (int) $other->fresh()->sort_order;
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->order($bob));

        // "Reorder" with its address and texts
        $r = $this->ticketsPage($bob);
        $this->seeIn($r, 'rg-views-order');
        $this->seeIn($r, 'data-url="'.route('refreshglobal.views.order').'"');
        $this->seeIn($r, 'data-rg-view-id="'.$c->id.'"');

        // new order; the view of another user is ignored, a missing one goes after
        $r = $this->actingAs($bob)->post(route('refreshglobal.views.order'), ['ids' => [$c->id, $other->id, $a->id]]);
        $r->assertStatus(200);
        $this->assertSame('success', $r->json()['status']);
        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->order($bob));
        $this->assertSame($otherOrder, (int) $other->fresh()->sort_order, 'another user\'s view is never changed');
        // the panel follows the new order
        $page = $this->ticketsPage($bob)->getContent();
        $this->assertLessThan(strpos($page, 'data-rg-view-id="'.$a->id.'"'), strpos($page, 'data-rg-view-id="'.$c->id.'"'));

        // only someone else's views: nothing changes
        $r = $this->actingAs($bob)->post(route('refreshglobal.views.order'), ['ids' => [$other->id]]);
        $this->assertSame(0, $r->json()['ordered']);
        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->order($bob));
        // invalid requests
        $this->actingAs($bob)->postJson(route('refreshglobal.views.order'), ['ids' => 'x'])->assertStatus(422);
        $this->actingAs($bob)->postJson(route('refreshglobal.views.order'), [])->assertStatus(422);
        $this->actingAs($bob)->postJson(route('refreshglobal.views.order'), ['ids' => ['abc']])->assertStatus(422);
    }
}
