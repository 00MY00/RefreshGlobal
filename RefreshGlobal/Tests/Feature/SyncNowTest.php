<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\SyncNow;
use Modules\RefreshGlobal\Tests\TestCase;

/** "Fetch e-mails" button: only with the SyncNow module, only IMAP mailboxes of the list, only allowed users. */
class SyncNowTest extends TestCase
{
    protected function imap($mailbox)
    {
        $mailbox->in_protocol = \App\Mailbox::IN_PROTOCOL_IMAP;
        $mailbox->in_server = 'imap.example.test';
        $mailbox->save();

        return $mailbox;
    }

    public function testButtonFollowsTheModule()
    {
        $this->imap($this->s['support']);
        $page = $this->ticketsPage($this->s['admin']);
        if (!SyncNow::available()) {
            // SyncNow not installed: no button, nothing to check
            $this->assertSame([], SyncNow::mailboxes($this->s['admin']));
            $this->dontSeeIn($page, 'rg-sync-btn');
            $report = (new CompatibilityChecker($this->s['admin']))->run();
            $codes = [];
            foreach ($report['results'] as $r) {
                $codes[$r['code']] = $r['status'];
            }
            $this->assertSame('skipped', $codes['RG-ROUTE-05']);

            return;
        }
        // SyncNow installed: button with the IMAP mailboxes only (POP3 / not configured ones are left out)
        $this->seeIn($page, 'rg-sync-btn');
        $ids = array_column(SyncNow::mailboxes($this->s['admin']), 'id');
        $this->assertContains((int) $this->s['support']->id, $ids);
        $this->assertNotContains((int) $this->s['sales']->id, $ids);
        // the page's mailbox filter is applied
        $this->assertSame([], SyncNow::mailboxes($this->s['admin'], [$this->s['sales']->id]));
    }

    public function testUserWithoutSyncNowPermissionHasNoButton()
    {
        $this->imap($this->s['support']);
        $this->assertSame([], SyncNow::mailboxes($this->s['bob']));
        $this->dontSeeIn($this->ticketsPage($this->s['bob']), 'rg-sync-btn');
    }

    public function testOnlyMailboxesOfTheUser()
    {
        if (!SyncNow::available()) {
            $this->markTestSkipped('SyncNow is not installed in this FreeScout.');
        }
        $this->imap($this->s['support']);
        $this->imap($this->s['billing']);
        $bob = $this->s['bob'];
        $bob->permissions = array_replace((array) $bob->permissions, [SyncNow::PERMISSION => true]);
        $bob->save();
        $ids = array_column(SyncNow::mailboxes($bob->fresh()), 'id');
        $this->assertContains((int) $this->s['support']->id, $ids);
        $this->assertNotContains((int) $this->s['billing']->id, $ids); // not one of bob's mailboxes
    }
}
