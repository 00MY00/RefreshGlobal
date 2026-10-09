<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/** Deleting a ticket: back to "All mailboxes" or next ticket, trash or permanent (Http/Middleware/AfterDelete.php). */
class DeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Settings::setDeleteGoesNext(false);
        Settings::setDeletePermanently(false);
    }

    protected function deleteTicket($conversation, $action = 'delete_conversation')
    {
        return $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), [
            'action'          => $action,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function testBackToTheLastAllMailboxesList()
    {
        $list = ['mb' => [$this->s['support']->id]];
        $this->ticketsPage($this->s['admin'], $list)->assertStatus(200);
        $r = $this->deleteTicket($this->s['t_support_open']);
        $r->assertStatus(200);
        $this->assertSame('success', $r->json()['status']);
        $this->assertSame(route('refreshglobal.tickets', $list), $r->json()['redirect_url']);
        // default: to FreeScout's trash
        $this->assertSame(Conversation::STATE_DELETED, (int) Conversation::find($this->s['t_support_open']->id)->state);
    }

    public function testWithoutListGoesToTheAllMailboxesPage()
    {
        $r = $this->deleteTicket($this->s['t_sales_open']);
        $this->assertSame(route('refreshglobal.tickets', ['reset' => 1]), $r->json()['redirect_url']);
    }

    public function testNextTicketOfTheList()
    {
        Settings::setDeleteGoesNext(true);
        // support, oldest first: OPEN, PENDING, CLOSED (same dates: by id)
        $this->ticketsPage($this->s['admin'], ['mb' => [$this->s['support']->id], 'sort' => 'created', 'order' => 'asc'])->assertStatus(200);
        $r = $this->deleteTicket($this->s['t_support_open']);
        $this->assertSame(route('conversations.view', ['id' => $this->s['t_support_pending']->id]), $r->json()['redirect_url']);

        // last of the list: the one before it
        $r = $this->deleteTicket($this->s['t_support_closed']);
        $this->assertSame(route('conversations.view', ['id' => $this->s['t_support_pending']->id]), $r->json()['redirect_url']);
    }

    public function testPermanentDeletion()
    {
        Settings::setDeletePermanently(true);
        $id = $this->s['t_support_open']->id;
        $this->assertSame('success', $this->deleteTicket($this->s['t_support_open'])->json()['status']);
        $this->assertNull(Conversation::find($id));
    }

    public function testPermanentBulkDeletion()
    {
        Settings::setDeletePermanently(true);
        $ids = [$this->s['t_support_open']->id, $this->s['t_sales_open']->id];
        $r = $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), [
            'action'          => 'bulk_delete_conversation',
            'conversation_id' => $ids,
        ]);
        $this->assertSame('success', $r->json()['status']);
        $this->assertSame(0, Conversation::whereIn('id', $ids)->count());
    }

    public function testBulkDeletionGoesToTrashByDefault()
    {
        $id = $this->s['t_support_open']->id;
        $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), [
            'action'          => 'bulk_delete_conversation',
            'conversation_id' => [$id],
        ]);
        $this->assertSame(Conversation::STATE_DELETED, (int) Conversation::find($id)->state);
    }

    public function testOtherActionsAreUntouched()
    {
        $this->ticketsPage($this->s['admin'])->assertStatus(200);
        $r = $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), [
            'action'          => 'conversation_change_status',
            'status'          => Conversation::STATUS_CLOSED,
            'conversation_id' => $this->s['t_support_open']->id,
        ]);
        $this->assertStringNotContainsString('refresh-global', (string) ($r->json()['redirect_url'] ?? ''));
    }

    public function testMailboxAboveEachTicket()
    {
        Settings::setShowMailbox(true);
        $r = $this->ticketsPage($this->s['bob']);
        $this->seeIn($r, 'rg-subject-mailbox');
        $this->seeIn($r, e($this->s['support']->email));

        Settings::setShowMailbox(false);
        $r = $this->ticketsPage($this->s['bob']);
        $this->dontSeeIn($r, 'rg-subject-mailbox');
        // the "Mailbox" column of the table layout stays
        $this->seeIn($r, 'rg-col-mailbox');
        Settings::setShowMailbox(true);
    }
}
