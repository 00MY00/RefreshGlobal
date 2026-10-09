<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Access rights: a user never gets a ticket, a counter or an export line of a mailbox they can not see,
 * even when forcing mailbox ids in the address.
 */
class AccessTest extends TestCase
{
    public function testUserSeesOnlyTicketsOfHisMailboxes()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['bob']);
        $r->assertStatus(200);
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-');
        $this->dontSeeIn($r, $s['prefix'].'-ARCHIVED');
    }

    public function testForcedMailboxIdIsIgnored()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['bob'], ['mb' => [$s['billing']->id, $s['archived']->id]]);
        $r->assertStatus(200);
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-');
        $this->dontSeeIn($r, $s['prefix'].'-ARCHIVED');
        // a forbidden id mixed with an allowed one: only the allowed one is used
        $r = $this->ticketsPage($s['bob'], ['mb' => [$s['billing']->id, $s['sales']->id]]);
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-');
    }

    public function testCountersOnlyCountAllowedMailboxes()
    {
        $s = $this->s;
        $access = new MailboxAccess($s['bob']);
        $filters = GlobalTicketQuery::normalize(['mb' => [$s['billing']->id]], $access);
        $this->assertSame([], $filters['mailboxes']);
        $this->assertSame(1, $filters['dropped_mailboxes']);

        $counts = (new GlobalTicketQuery($access, $filters))->countsByMailbox();
        $this->assertArrayNotHasKey($s['billing']->id, $counts);
        $this->assertArrayNotHasKey($s['archived']->id, $counts);
        // support: open + pending + closed (spam excluded); sales: open + formula (draft excluded)
        $this->assertSame(3, $counts[$s['support']->id]);
        $this->assertSame(2, $counts[$s['sales']->id]);

        $by_status = (new GlobalTicketQuery($access, $filters))->countsByStatus();
        $this->assertSame(array_sum($counts), array_sum($by_status));
    }

    public function testArchivedMailboxHiddenFromRegularUser()
    {
        $s = $this->s;
        $this->assertNotContains($s['archived']->id, (new MailboxAccess($s['dave']))->allowedIds());
        $r = $this->ticketsPage($s['dave'], ['mb' => [$s['archived']->id]]);
        $this->dontSeeIn($r, $s['prefix'].'-ARCHIVED');
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');
    }

    public function testOnlyAssignedPermissionIsApplied()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['carol']);
        $r->assertStatus(200);
        $this->seeIn($r, $s['prefix'].'-BILLING-CAROL');
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-OTHER');
        // asking for "unassigned" or another user does not widen it
        $r = $this->ticketsPage($s['carol'], ['assignee' => 'none']);
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-OTHER');
    }

    public function testDraftsAndDeletedAreNotListed()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['admin']);
        $this->dontSeeIn($r, $s['prefix'].'-SALES-DRAFT');
        $this->dontSeeIn($r, $s['prefix'].'-BILLING-DELETED');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-SPAM');
        $this->seeIn($r, $s['prefix'].'-BILLING-OTHER');
        $this->seeIn($r, $s['prefix'].'-ARCHIVED'); // admins see archived mailboxes, like in FreeScout
    }

    public function testSpamOnlyWhenAskedFor()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['bob'], ['status' => [\App\Conversation::STATUS_SPAM]]);
        $this->seeIn($r, $s['prefix'].'-SUPPORT-SPAM');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
    }

    public function testTicketLinksGoToNativeConversationPage()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['bob'], ['q' => $s['prefix'].'-SALES-OPEN']);
        $this->seeIn($r, route('conversations.view', ['id' => $s['t_sales_open']->id]));
    }

    public function testSearchByCustomerNameAndNumber()
    {
        $s = $this->s;
        $r = $this->ticketsPage($s['bob'], ['q' => 'Alice Martin']);
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-SALES-OPEN');
        $r = $this->ticketsPage($s['bob'], ['q' => '#'.$s['t_sales_open']->number]);
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');
    }

    public function testGuestIsRedirectedToLogin()
    {
        $this->get(route('refreshglobal.tickets'))->assertStatus(302);
        $this->get(route('refreshglobal.export'))->assertStatus(302);
    }

    public function testDiagnosticIsAdminOnly()
    {
        $this->actingAs($this->s['bob'])->get(route('refreshglobal.diagnostic'))->assertStatus(403);
        $this->actingAs($this->s['admin'])->get(route('refreshglobal.diagnostic'))->assertStatus(200);
    }
}
