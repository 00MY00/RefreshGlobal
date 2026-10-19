<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Dashboard, "Unresolved tickets" card: open and pending tickets per agent counted apart and sorted by their total
 * (like Refresh 1.5.0's card), with the user's rights; no query per agent; rows of an older version still shown.
 */
class DashboardUnresolvedTest extends TestCase
{
    /** @var \App\Mailbox */
    protected $mb;

    /** @var \App\User */
    protected $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        // a mailbox of its own: the counts only hold the tickets made here
        $this->mb = Fixtures::mailbox($this->s['prefix'].' Dash');
        $this->viewer = Fixtures::user('Viewer', [$this->mb]);
    }

    protected function tickets($user, $open, $pending, $mailbox = null)
    {
        $mailbox = $mailbox ?: $this->mb;
        for ($i = 0; $i < $open; $i++) {
            Fixtures::conversation($mailbox, 'open '.$i, ['user_id' => $user ? $user->id : null]);
        }
        for ($i = 0; $i < $pending; $i++) {
            Fixtures::conversation($mailbox, 'pending '.$i, ['user_id' => $user ? $user->id : null, 'status' => Conversation::STATUS_PENDING]);
        }
    }

    protected function rows()
    {
        $out = [];
        foreach (GlobalDashboard::unresolvedFor($this->viewer) as $r) {
            $out[$r['name']] = [$r['open'], $r['pending'], $r['total']];
        }

        return $out;
    }

    protected function agent($first_name)
    {
        return Fixtures::user($first_name, [$this->mb]);
    }

    public function testOpenOnlyPendingOnlyAndBoth()
    {
        $open = $this->agent('Opal');
        $pending = $this->agent('Penny');
        $both = $this->agent('Bea');
        $this->tickets($open, 3, 0);
        $this->tickets($pending, 0, 2);
        $this->tickets($both, 1, 4);
        // closed and spam tickets are not unresolved
        Fixtures::conversation($this->mb, 'closed', ['user_id' => $open->id, 'status' => Conversation::STATUS_CLOSED]);
        Fixtures::conversation($this->mb, 'spam', ['user_id' => $open->id, 'status' => Conversation::STATUS_SPAM]);
        // deleted and draft tickets neither
        Fixtures::conversation($this->mb, 'deleted', ['user_id' => $open->id, 'state' => Conversation::STATE_DELETED]);

        $rows = $this->rows();
        $this->assertSame([3, 0, 3], $rows[$open->getFullName()]);
        $this->assertSame([0, 2, 2], $rows[$pending->getFullName()]);
        $this->assertSame([1, 4, 5], $rows[$both->getFullName()]);
    }

    public function testSortedByOpenPlusPendingThenByName()
    {
        // the example of the request: Alice 7 + 5, Bob 10 + 0, Charlie 2 + 3
        $alice = $this->agent('Alice');
        $bob = $this->agent('Bobby');
        $charlie = $this->agent('Charlie');
        $this->tickets($bob, 10, 0);
        $this->tickets($charlie, 2, 3);
        $this->tickets($alice, 7, 5);
        $this->assertSame([$alice->getFullName(), $bob->getFullName(), $charlie->getFullName()], array_keys($this->rows()));

        // equal totals: by name
        $zed = $this->agent('Zed');
        $abe = $this->agent('Abe');
        $this->tickets($zed, 1, 0);
        $this->tickets($abe, 0, 1);
        $names = array_keys($this->rows());
        $this->assertSame([$abe->getFullName(), $zed->getFullName()], array_slice($names, -2));
    }

    public function testUnassignedRowSplitToo()
    {
        $this->tickets(null, 2, 1);
        $rows = GlobalDashboard::unresolvedFor($this->viewer);
        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['user_id']);
        $this->assertSame([2, 1, 3], [$rows[0]['open'], $rows[0]['pending'], $rows[0]['total']]);
        // links: both statuses, open only, pending only, unassigned
        $this->assertStringContainsString('assignee=none', $rows[0]['url']);
        $this->assertStringContainsString('status%5B0%5D='.Conversation::STATUS_ACTIVE, $rows[0]['url_open']);
        $this->assertStringNotContainsString('status%5B1%5D', $rows[0]['url_open']);
        $this->assertStringContainsString('status%5B0%5D='.Conversation::STATUS_PENDING, $rows[0]['url_pending']);
    }

    public function testOnlyAllowedMailboxesAreCounted()
    {
        $agent = $this->agent('Agnes');
        $other = Fixtures::mailbox($this->s['prefix'].' Hidden');
        $this->tickets($agent, 2, 1);
        $this->tickets($agent, 5, 5, $other); // the viewer can not see this mailbox
        $this->assertSame([2, 1, 3], $this->rows()[$agent->getFullName()]);

        // "only assigned tickets" right: the user's own tickets only
        $carol = Fixtures::user('Carla', [$this->mb], false, true);
        $this->tickets($carol, 1, 1);
        $rows = [];
        foreach (GlobalDashboard::unresolvedFor($carol) as $r) {
            $rows[$r['user_id']] = $r['total'];
        }
        $this->assertSame([$carol->id => 2], $rows);
    }

    public function testEmptyDashboard()
    {
        $this->assertSame([], GlobalDashboard::unresolvedFor($this->viewer));
        $html = view('refreshglobal::partials.unresolved_card', ['by_agent' => [], 'rg_unresolved' => '#'])->render();
        $this->assertStringContainsString('rf-widget-row', $html);
        $this->assertStringNotContainsString('rg-unres-row', $html);
    }

    public function testRowsOfAnOlderVersionStillShown()
    {
        // cached by a module older than 1.14.0: name, n, url only
        $old = [['name' => 'Old Agent', 'n' => 7, 'url' => 'http://example.test/list']];
        $html = view('refreshglobal::partials.unresolved_card', ['by_agent' => $old, 'rg_unresolved' => '#'])->render();
        $this->assertStringContainsString('Old Agent', $html);
        $this->assertMatchesRegularExpression('#data-label="[^"]*"[^>]*>7</a>#', $html);

        $this->tickets($this->agent('Nina'), 3, 2);
        $new = GlobalDashboard::unresolvedFor($this->viewer);
        $html = view('refreshglobal::partials.unresolved_card', ['by_agent' => $new, 'rg_unresolved' => '#'])->render();
        $this->assertMatchesRegularExpression('#>3</a>\s*<a[^>]*>2</a>#', $html);
    }

    public function testNoQueryPerAgent()
    {
        $log = [];
        $count = function () use (&$log) {
            $viewer = $this->viewer->fresh();
            GlobalDashboard::unresolvedFor($viewer); // warm-up: FreeScout's own per-request caches
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            GlobalDashboard::unresolvedFor($viewer);
            $log = array_column(\DB::getQueryLog(), 'query');
            \DB::disableQueryLog();

            return count($log);
        };
        $this->tickets($this->agent('One'), 1, 1);
        $few = $count();
        $first = $log;
        foreach (['Two', 'Three', 'Four', 'Five', 'Six'] as $name) {
            $this->tickets($this->agent($name), 1, 1);
        }
        $this->tickets(null, 1, 0);
        $this->assertCount(7, GlobalDashboard::unresolvedFor($this->viewer));
        $this->assertSame($few, $count(), "1 agent:\n".implode("\n", $first)."\n6 agents + unassigned:\n".implode("\n", $log));
        $this->assertLessThanOrEqual(4, $few);
    }
}
