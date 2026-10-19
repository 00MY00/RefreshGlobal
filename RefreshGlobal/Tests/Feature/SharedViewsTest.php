<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use App\User;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\SavedViews;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Saved views shared with users or everyone (Services/SavedViews.php): who sees, edits, duplicates, deletes; "me"
 * resolved for the viewer; mailbox rights never bypassed; missing resources, corrupt and old definitions; cache;
 * number of queries; export / import; defaults, pins, order. Scenario: bob (support + sales), carol (billing, only
 * assigned tickets), dave (support + archived), admin (every mailbox).
 */
class SharedViewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SavedViews::reset();
        if (!SavedViews::available()) {
            $this->markTestSkipped('Tables of the shared views missing (migration not run).');
        }
    }

    protected function store($user, array $data)
    {
        return $this->actingAs($user)->post(route('refreshglobal.views.store'), $data);
    }

    protected function view($name)
    {
        return SavedView::where('name', $name)->orderBy('id', 'desc')->first();
    }

    protected function names($user)
    {
        return array_column(SavedViews::visibleFor($user), 'name');
    }

    public function testPrivateViewIsOnlyForItsOwner()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Bob private', 'status' => [Conversation::STATUS_ACTIVE]])->assertStatus(302);
        $view = $this->view('Bob private');
        $this->assertSame(SavedView::PRIVATE_VIEW, $view->visibility);
        $this->assertSame(2, $view->schema_version);
        $this->seeIn($this->ticketsPage($s['bob'], ['view' => $view->id]), 'Bob private');

        $this->assertNotContains('Bob private', $this->names($s['dave']));
        $this->dontSeeIn($this->ticketsPage($s['dave'], ['view' => $view->id]), 'Bob private');
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.duplicate', ['id' => $view->id]))->assertStatus(404);
        $this->actingAs($s['dave'])->get(route('refreshglobal.views.export', ['id' => $view->id]))->assertStatus(404);
        // an administrator does not see the private views of others either
        $this->assertNotContains('Bob private', $this->names($s['admin']));
        $this->actingAs($s['admin'])->delete(route('refreshglobal.views.destroy', ['id' => $view->id]))->assertStatus(404);
    }

    public function testSharedWithChosenUsers()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Support queue', 'mb' => [$s['support']->id], 'visibility' => 'users', 'users' => [$s['dave']->id]])->assertStatus(302);
        $view = $this->view('Support queue');
        $this->assertSame(SavedView::SHARED_USERS, $view->visibility);
        $this->assertSame([$s['dave']->id], array_column(SavedViews::sharedWith($view), 'id'));

        // dave (chosen) sees and opens it, in "Shared with me"
        $row = collect(SavedViews::visibleFor($s['dave']))->first(function ($r) use ($view) { return $r['id'] === $view->id; });
        $this->assertSame('shared', $row['section']);
        $r = $this->ticketsPage($s['dave'], ['view' => $view->id]);
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($r, 'rg-view-banner');

        // carol (not chosen) does not
        $this->assertNotContains('Support queue', $this->names($s['carol']));
        $this->dontSeeIn($this->ticketsPage($s['carol'], ['view' => $view->id]), 'Support queue');

        // bob can not share with a user outside his mailboxes (carol: billing)
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'users', 'users' => [$s['dave']->id, $s['carol']->id]])->assertStatus(302);
        $this->assertSame([$s['dave']->id], array_column(SavedViews::sharedWith($view), 'id'));
    }

    public function testGlobalViewForEveryone()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'All open', 'status' => [Conversation::STATUS_ACTIVE], 'visibility' => 'global'])->assertStatus(302);
        $view = $this->view('All open');
        $this->assertSame(SavedView::GLOBAL_VIEW, $view->visibility);
        foreach (['carol', 'dave', 'admin'] as $who) {
            $this->assertContains('All open', $this->names($s[$who]), $who);
        }
        // setting: only administrators publish views for everyone
        Settings::setFlag(Settings::VIEWS_GLOBAL_ADMINS, true);
        $this->store($s['bob'], ['name' => 'Not allowed', 'visibility' => 'global'])->assertStatus(403);
        $this->store($s['admin'], ['name' => 'Admin global', 'visibility' => 'global'])->assertStatus(302);
        $this->assertSame(SavedView::GLOBAL_VIEW, $this->view('Admin global')->visibility);
    }

    public function testEditingRights()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Team view', 'mb' => [$s['support']->id], 'visibility' => 'users', 'users' => [$s['dave']->id]]);
        $view = $this->view('Team view');
        $before = $view->filters;

        // dave changes a filter: only his page, the shared definition is unchanged
        $r = $this->ticketsPage($s['dave'], ['view' => $view->id, 'vm' => 1, 'mb' => [$s['support']->id], 'status' => [Conversation::STATUS_PENDING]]);
        $this->seeIn($r, 'rg-view-modified');
        $this->dontSeeIn($r, route('refreshglobal.views.update', ['id' => $view->id]));
        $this->assertEquals($before, $view->fresh()->filters);
        // and he can not update it, rename it, share it or delete it
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.update', ['id' => $view->id]), ['status' => [Conversation::STATUS_PENDING]])->assertStatus(403);
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.rename', ['id' => $view->id]), ['name' => 'x'])->assertStatus(403);
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'global'])->assertStatus(403);
        $this->actingAs($s['dave'])->delete(route('refreshglobal.views.destroy', ['id' => $view->id]))->assertStatus(403);
        $this->assertEquals($before, $view->fresh()->filters);

        // an administrator edits any shared view, and may let dave edit it
        $this->actingAs($s['admin'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'users', 'users' => [$s['dave']->id], 'can_edit' => [$s['dave']->id]])->assertStatus(302);
        $this->assertTrue(SavedViews::sharedWith($view)[0]['can_edit']);
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.update', ['id' => $view->id]), ['mb' => [$s['support']->id], 'status' => [Conversation::STATUS_PENDING]])->assertStatus(302);
        $this->assertSame([Conversation::STATUS_PENDING], SavedViews::definition($view->fresh())['filters']['status']);
        // "may edit" is not "may share": dave still can not change who sees it
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'global'])->assertStatus(403);
        // the owner can not grant "may edit" himself (administrators only)
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'users', 'users' => [$s['dave']->id], 'can_edit' => []]);
        $this->assertTrue(SavedViews::sharedWith($view)[0]['can_edit']);

        // admin edits a global view of bob
        $this->store($s['bob'], ['name' => 'Bob global', 'visibility' => 'global']);
        $g = $this->view('Bob global');
        $this->actingAs($s['admin'])->post(route('refreshglobal.views.rename', ['id' => $g->id]), ['name' => 'Renamed by admin'])->assertStatus(302);
        $this->assertSame('Renamed by admin', $g->fresh()->name);
        // the audit keeps who did it
        $this->assertTrue(\DB::table('refreshglobal_view_audit')->where('view_id', $g->id)->where('action', 'renamed')->where('user_id', $s['admin']->id)->exists());
    }

    public function testDuplicate()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Support - Open', 'mb' => [$s['support']->id], 'visibility' => 'global']);
        $view = $this->view('Support - Open');
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.duplicate', ['id' => $view->id]))->assertStatus(302);
        $copy = SavedView::where('user_id', $s['dave']->id)->orderBy('id', 'desc')->first();
        $this->assertSame(SavedView::PRIVATE_VIEW, $copy->visibility);
        $this->assertStringStartsWith('Support - Open (', $copy->name);
        $this->assertSame([$s['support']->id], SavedViews::definition($copy)['filters']['mailboxes']);
        // the copy is free to change, the original is not touched
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.update', ['id' => $copy->id]), ['status' => [Conversation::STATUS_CLOSED]])->assertStatus(302);
        $this->assertSame([], SavedViews::definition($view->fresh())['filters']['status']);

        // duplication not allowed by the owner
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'global', 'allow_duplicate' => 0]);
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.duplicate', ['id' => $view->id]))->assertStatus(403);
    }

    public function testDelete()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'To delete', 'visibility' => 'users', 'users' => [$s['dave']->id]]);
        $view = $this->view('To delete');
        SavedViews::setPref($s['dave'], $view, 'pinned', true);
        $this->actingAs($s['bob'])->delete(route('refreshglobal.views.destroy', ['id' => $view->id]))->assertStatus(302);
        $this->assertNull(SavedView::find($view->id));
        $this->assertSame(0, \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->count());
        $this->assertSame(0, \DB::table('refreshglobal_view_prefs')->where('view_id', $view->id)->count());
        $this->assertNotContains('To delete', $this->names($s['dave']));
    }

    public function testMeIsTheViewerNotTheAuthor()
    {
        $s = $this->s;
        $mine = Fixtures::conversation($s['support'], $s['prefix'].'-DAVE-OWN mine', ['user_id' => $s['dave']->id]);
        // "My open tickets" made by bob: assignee = me, active + pending, for everyone
        $this->store($s['bob'], ['name' => 'My tickets', 'assignee' => 'me', 'status' => [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING], 'visibility' => 'global']);
        $view = $this->view('My tickets');
        $this->assertSame('me', SavedViews::definition($view)['filters']['assignee']);

        // the query of dave filters on dave, never on bob
        $access = new MailboxAccess($s['dave']);
        $query = (new GlobalTicketQuery($access, SavedViews::filtersFor($view, $access)))->base();
        $this->assertContains((int) $s['dave']->id, array_map('intval', $query->getQuery()->getBindings()));
        $this->assertNotContains((int) $s['bob']->id, array_map('intval', $query->getQuery()->getBindings()));
        $ids = $query->pluck('conversations.id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($s['t_support_pending']->id, $ids); // bob's ticket

        $r = $this->ticketsPage($s['dave'], ['view' => $view->id]);
        $this->seeIn($r, $s['prefix'].'-DAVE-OWN');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-PENDING');
        // and for bob, his own
        $this->seeIn($this->ticketsPage($s['bob'], ['view' => $view->id]), $s['prefix'].'-SUPPORT-PENDING');
    }

    public function testViewNeverBypassesMailboxRights()
    {
        $s = $this->s;
        // global view of the admin over support and billing; bob only has support (and sales)
        $this->store($s['admin'], ['name' => 'Support and billing', 'mb' => [$s['support']->id, $s['billing']->id], 'visibility' => 'global']);
        $view = $this->view('Support and billing');
        $this->assertEqualsCanonicalizing([$s['support']->id, $s['billing']->id], SavedViews::definition($view)['filters']['mailboxes']);
        $r = $this->ticketsPage($s['bob'], ['view' => $view->id]);
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-');
        $this->seeIn($r, 'rg-dropped'); // "1 mailbox of this view is not yours"
        // nor through the export or the automatic refresh
        $csv = $this->actingAs($s['bob'])->get(route('refreshglobal.export', ['view' => $view->id]));
        $this->assertStringNotContainsString($s['prefix'].'-BILLING-', $csv->getContent());
        // nor by forcing the mailbox in the URL
        $r = $this->ticketsPage($s['bob'], ['view' => $view->id, 'vm' => 1, 'mb' => [$s['billing']->id]]);
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-');
        // carol: only her assigned tickets, also in a shared view
        $r = $this->ticketsPage($s['carol'], ['view' => $view->id]);
        $this->seeIn($r, $s['prefix'].'-BILLING-CAROL');
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-OTHER');
    }

    public function testMissingResourcesDegrade()
    {
        $s = $this->s;
        // a mailbox that no longer exists
        $view = SavedViews::create($s['bob'], 'Gone mailbox', GlobalTicketQuery::normalize([], new MailboxAccess($s['bob'])));
        $def = SavedViews::definition($view);
        $def['filters']['mailboxes'] = [$s['support']->id, 987654321];
        $view->filters = $def;
        $view->save();
        $r = $this->ticketsPage($s['bob'], ['view' => $view->id]);
        $r->assertStatus(200);
        $this->seeIn($r, e(trans_choice('refreshglobal::messages.view_issue_mailbox_missing', 1, ['count' => 1])));
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');

        // an agent that was deleted
        $gone = Fixtures::user('Gone', [$s['support']]);
        $view2 = SavedViews::create($s['bob'], 'Gone agent', GlobalTicketQuery::normalize(['assignee' => (string) $gone->id], new MailboxAccess($s['bob'])));
        $gone->status = User::STATUS_DELETED;
        $gone->save();
        $r = $this->ticketsPage($s['bob'], ['view' => $view2->id]);
        $r->assertStatus(200);
        $this->seeIn($r, e(__('refreshglobal::messages.view_issue_user_missing')));

        // a user the view is shared with, deleted: left out of the sharing, nothing breaks
        $this->store($s['bob'], ['name' => 'Shared to gone', 'visibility' => 'users', 'users' => [$s['dave']->id]]);
        $shared = $this->view('Shared to gone');
        $s['dave']->status = User::STATUS_DELETED;
        $s['dave']->save();
        $this->assertSame([], SavedViews::sharedWith($shared));
        $this->actingAs($s['bob'])->get(route('refreshglobal.views'))->assertStatus(200);
    }

    public function testCorruptAndOldDefinitions()
    {
        $s = $this->s;
        // unreadable definition: the whole list, a notice, no error
        $id = \DB::table('refreshglobal_saved_views')->insertGetId(['user_id' => $s['bob']->id, 'name' => 'Corrupt', 'filters' => '{not json',
            'is_default' => false, 'visibility' => 'private', 'schema_version' => 2, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $r = $this->ticketsPage($s['bob'], ['view' => $id]);
        $r->assertStatus(200);
        $this->seeIn($r, e(__('refreshglobal::messages.view_issue_corrupt')));
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');

        // a view saved by RefreshGlobal 1.14 (schema 1: flat filters) is read and upgraded
        $old = ['mailboxes' => [$s['sales']->id], 'status' => [Conversation::STATUS_ACTIVE], 'assignee' => '', 'q' => '', 'rview' => '', 'wfolder' => '', 'mailtag' => '', 'sort' => 'created', 'order' => 'asc'];
        $id = \DB::table('refreshglobal_saved_views')->insertGetId(['user_id' => $s['bob']->id, 'name' => 'Old one', 'filters' => json_encode($old),
            'is_default' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $view = SavedView::find($id);
        $this->assertSame(1, $view->schema_version);
        $def = SavedViews::definition($view);
        $this->assertSame(2, $def['schema_version']);
        $this->assertSame([$s['sales']->id], $def['filters']['mailboxes']);
        $this->assertSame(['field' => 'created', 'direction' => 'asc'], $def['sorting']);
        $r = $this->ticketsPage($s['bob'], ['view' => $id]);
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        // saving it again writes the current format
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.update', ['id' => $id]), ['mb' => [$s['sales']->id]])->assertStatus(302);
        $this->assertSame(2, $view->fresh()->schema_version);
        $this->assertArrayHasKey('filters', $view->fresh()->filters);
    }

    public function testCacheIsClearedByChanges()
    {
        $s = $this->s;
        $this->assertNotContains('Fresh share', $this->names($s['dave'])); // list cached for dave
        $this->store($s['bob'], ['name' => 'Fresh share', 'visibility' => 'users', 'users' => [$s['dave']->id]]);
        $this->assertContains('Fresh share', $this->names($s['dave']));
        $view = $this->view('Fresh share');
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'private']);
        $this->assertNotContains('Fresh share', $this->names($s['dave']));
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.share', ['id' => $view->id]), ['visibility' => 'global']);
        $this->assertContains('Fresh share', $this->names($s['dave']));
        $this->actingAs($s['bob'])->delete(route('refreshglobal.views.destroy', ['id' => $view->id]));
        $this->assertNotContains('Fresh share', $this->names($s['dave']));
    }

    public function testVisibleListHasNoQueryPerView()
    {
        $s = $this->s;
        $count = function () use ($s) {
            SavedViews::bump();
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            SavedViews::visibleFor($s['dave']);
            $n = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $n;
        };
        $this->store($s['bob'], ['name' => 'Q1', 'visibility' => 'users', 'users' => [$s['dave']->id]]);
        $few = $count();
        for ($i = 2; $i <= 9; $i++) {
            $this->store($i % 2 ? $s['admin'] : $s['bob'], ['name' => 'Q'.$i, 'visibility' => $i % 3 ? 'global' : 'users', 'users' => [$s['dave']->id]]);
        }
        SavedViews::setPref($s['dave'], $this->view('Q4'), 'pinned', true);
        $this->assertGreaterThanOrEqual(9, count(SavedViews::visibleFor($s['dave'])));
        $this->assertSame($few, $count());
        $this->assertLessThanOrEqual(4, $few);
    }

    public function testDefaultsPinsAndOrder()
    {
        $s = $this->s;
        // an administrator sets a global view as default for everyone
        $this->store($s['admin'], ['name' => 'Org default', 'status' => [Conversation::STATUS_PENDING], 'visibility' => 'global', 'default_scope' => 'all']);
        $org = $this->view('Org default');
        $this->assertSame('all', $org->default_scope);
        $r = $this->ticketsPage($s['bob']);
        $this->seeIn($r, $s['prefix'].'-SUPPORT-PENDING');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        // a normal user can not set defaults for others
        $this->store($s['bob'], ['name' => 'Bob tries', 'visibility' => 'global', 'default_scope' => 'all']);
        $this->assertSame('', $this->view('Bob tries')->default_scope);
        // bob's own default comes first
        $this->store($s['bob'], ['name' => 'Bob default', 'mb' => [$s['sales']->id], 'is_default' => 1]);
        $r = $this->ticketsPage($s['bob']);
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-PENDING');

        // pin: per user, shown in "Pinned views", the view itself unchanged
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.pin', ['id' => $org->id]))->assertStatus(302);
        $row = collect(SavedViews::visibleFor($s['dave']))->first(function ($r) use ($org) { return $r['id'] === $org->id; });
        $this->assertTrue($row['pinned']);
        $this->seeIn($this->ticketsPage($s['dave'], ['reset' => 1]), 'rg-views-pinned');
        $this->assertFalse(collect(SavedViews::visibleFor($s['bob']))->first(function ($r) use ($org) { return $r['id'] === $org->id; })['pinned']);

        // order of my views
        $this->store($s['bob'], ['name' => 'Bob A']);
        $this->store($s['bob'], ['name' => 'Bob B']);
        $mine = function () use ($s) {
            return array_values(array_filter(array_map(function ($r) { return $r['section'] === 'mine' ? $r['name'] : null; }, SavedViews::visibleFor($s['bob']))));
        };
        $before = $mine();
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.move', ['id' => $this->view('Bob B')->id]), ['direction' => 'up'])->assertStatus(302);
        $after = $mine();
        $this->assertLessThan(array_search('Bob A', $after), array_search('Bob B', $after));
        $this->assertGreaterThan(array_search('Bob A', $before), array_search('Bob B', $before));
        // not someone else's view
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.move', ['id' => $org->id]), ['direction' => 'up'])->assertStatus(403);
    }

    public function testExportImport()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Portable', 'mb' => [$s['support']->id], 'assignee' => (string) $s['bob']->id, 'status' => [Conversation::STATUS_ACTIVE]]);
        $view = $this->view('Portable');
        $r = $this->actingAs($s['bob'])->get(route('refreshglobal.views.export', ['id' => $view->id]));
        $r->assertStatus(200);
        $data = json_decode($r->getContent(), true);
        $this->assertSame('refreshglobal-view', $data['format']);
        $this->assertSame($s['support']->email, $data['definition']['filters']['mailboxes'][0]['email']);
        // no user id leaves the installation
        $this->assertSame('', $data['definition']['filters']['assignee']);

        $this->actingAs($s['dave'])->post(route('refreshglobal.views.import'), ['json' => json_encode($data)])->assertStatus(302);
        $imported = SavedView::where('user_id', $s['dave']->id)->where('name', 'Portable')->first();
        $this->assertNotNull($imported);
        $this->assertSame(SavedView::PRIVATE_VIEW, $imported->visibility);
        $this->assertSame([$s['support']->id], SavedViews::definition($imported)['filters']['mailboxes']);
        // carol does not see support: the mailbox is left out
        $this->actingAs($s['carol'])->post(route('refreshglobal.views.import'), ['json' => json_encode($data)]);
        $this->assertSame([], SavedViews::definition(SavedView::where('user_id', $s['carol']->id)->where('name', 'Portable')->first())['filters']['mailboxes']);
        // not an exported view
        $this->actingAs($s['dave'])->post(route('refreshglobal.views.import'), ['json' => '{"x":1}'])->assertSessionHas('flash_error');
    }

    public function testManagePageAndMobileRendering()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'On the page', 'visibility' => 'users', 'users' => [$s['dave']->id]]);
        $r = $this->actingAs($s['bob'])->get(route('refreshglobal.views'));
        $r->assertStatus(200);
        $this->seeIn($r, 'rg-views-table');
        $this->seeIn($r, 'On the page');
        $this->seeIn($r, 'data-label=');
        $r = $this->actingAs($s['dave'])->get(route('refreshglobal.views'));
        $this->seeIn($r, 'On the page');
        // the save form of the list page: stacked fields, user list that scrolls and can be filtered
        $page = $this->ticketsPage($s['bob'])->getContent();
        $this->assertStringContainsString('rg-user-list', $page);
        $this->assertStringContainsString('rg-user-filter', $page);
        $css = file_get_contents(__DIR__.'/../../Public/css/refreshglobal.css');
        $this->assertMatchesRegularExpression('/@media \(max-width: 767px\)[^@]*\.rg-views-table thead \{ display: none; \}/s', $css);
        $this->assertStringNotContainsString('width: 600px', $css);
    }
}
