<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Services\AutoFolders;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Tests\Support\WorkflowPacks;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Automatic folders of Workflow Packs (Services/AutoFolders.php): views panel, list filter (wf), badge, overview page.
 * Folder names carry the test prefix: a FreeScout with Workflow Packs installed may hold real folders too.
 */
class AutoFoldersTest extends TestCase
{
    /** @var string "RGTxxxFactures" */
    protected $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = $this->s['prefix'].'Factures';
    }

    protected function on()
    {
        AutoFolders::$assumeActive = true;
        AutoFolders::reset();
        $s = $this->s;
        WorkflowPacks::file($s['t_support_open'], $this->f.'/Fournisseurs');
        WorkflowPacks::file($s['t_sales_open'], $this->f);
        WorkflowPacks::file($s['t_support_spam'], $this->f);            // spam: not counted, like the list
        WorkflowPacks::file($s['t_billing_other'], $this->f.'/Clients', 'cleaned'); // removed by the clean-up
        WorkflowPacks::file($s['t_billing_carol'], $this->s['prefix'].'Newsletters');
    }

    public function testNothingWithoutWorkflowPacks()
    {
        if (\App\Module::isActive('workflowpacks')) {
            $this->markTestSkipped('Workflow Packs is installed in this FreeScout.');
        }
        WorkflowPacks::file($this->s['t_support_open'], $this->f);
        $this->assertFalse(AutoFolders::available());
        $page = $this->ticketsPage($this->s['admin'], ['wf' => $this->f]);
        $page->assertStatus(200);
        $this->dontSeeIn($page, 'data-sec="rg-folders"');
        $this->dontSeeIn($page, 'rg-folder-badge');
        // the parameter is ignored: the whole list
        $this->seeIn($page, $this->s['prefix'].'-SALES-OPEN');
        // checks of the optional module: not applicable
        $codes = [];
        foreach ((new CompatibilityChecker($this->s['admin']))->run()['results'] as $r) {
            $codes[$r['code']] = $r['status'];
        }
        $this->assertSame('skipped', $codes['RG-DB-09']);
        $this->assertSame('skipped', $codes['RG-ROUTE-07']);
        // overview: explicit message
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('refreshglobal.folders')), __('refreshglobal::messages.folders_no_wp'));
    }

    public function testPanelTreeWithCounts()
    {
        $this->on();
        $page = $this->ticketsPage($this->s['admin']);
        $this->seeIn($page, 'data-sec="rg-folders"');
        $tree = [];
        foreach (AutoFolders::tree(AutoFolders::folders($this->rightsBase($this->s['admin'])), (new \Modules\RefreshGlobal\Services\GlobalTicketQuery(
            new \Modules\RefreshGlobal\Services\MailboxAccess($this->s['admin']), []))->countsByFolder()) as $node) {
            if (strpos($node['folder'], $this->s['prefix']) === 0) {
                $tree[$node['folder']] = [$node['name'], $node['depth'], $node['count']];
            }
        }
        // parent counts its sub-folders; spam and cleaned rows are left out; a folder comes before its sub-folders
        $this->assertSame([
            $this->f                    => ['[WP] '.$this->f, 0, 2],
            $this->f.'/Fournisseurs'    => ['Fournisseurs', 1, 1],
            $this->s['prefix'].'Newsletters' => ['[WP] '.$this->s['prefix'].'Newsletters', 0, 1],
        ], $tree);
        // links of the panel filter the list
        $this->seeIn($page, e(route('refreshglobal.tickets', ['wf' => $this->f.'/Fournisseurs'])));
        $this->seeIn($page, route('refreshglobal.folders'));
    }

    public function testFilterIncludesSubFoldersAndCombines()
    {
        $this->on();
        $p = $this->s['prefix'];
        $page = $this->ticketsPage($this->s['admin'], ['wf' => $this->f]);
        $this->seeIn($page, $p.'-SUPPORT-OPEN');
        $this->seeIn($page, $p.'-SALES-OPEN');
        $this->dontSeeIn($page, $p.'-BILLING-OTHER'); // cleaned: no longer in the folder
        $this->dontSeeIn($page, $p.'-BILLING-CAROL');
        $this->dontSeeIn($page, $p.'-SUPPORT-SPAM');
        // title, removable chip, hidden field of the filters form
        $this->seeIn($page, '[WP] '.$this->f.' · ');
        $this->seeIn($page, 'name="wf" value="'.$this->f.'"');

        $page = $this->ticketsPage($this->s['admin'], ['wf' => $this->f.' / Fournisseurs']);
        $this->seeIn($page, $p.'-SUPPORT-OPEN');
        $this->dontSeeIn($page, $p.'-SALES-OPEN');

        // combined with the other filters
        $page = $this->ticketsPage($this->s['admin'], ['wf' => $this->f, 'mb' => [$this->s['sales']->id]]);
        $this->dontSeeIn($page, $p.'-SUPPORT-OPEN');
        $this->seeIn($page, $p.'-SALES-OPEN');

        // LIKE wildcards are plain characters
        $page = $this->ticketsPage($this->s['admin'], ['wf' => $p.'F%']);
        $this->dontSeeIn($page, $p.'-SALES-OPEN');
    }

    public function testBadgeOnEachTicket()
    {
        $this->on();
        $page = $this->ticketsPage($this->s['admin']);
        $this->seeIn($page, 'rg-folder-badge');
        $this->seeIn($page, '</svg> [WP] '.$this->f.' › Fournisseurs</span>');
        $this->dontSeeIn($page, '[WP] '.$this->f.' › Clients'); // cleaned
    }

    public function testRightsOfTheUser()
    {
        $this->on();
        $p = $this->s['prefix'];
        // Bob: support + sales only — the billing folder (Newsletters) is not shown, not even its name
        $page = $this->ticketsPage($this->s['bob']);
        $this->seeIn($page, '[WP] '.$this->f);
        $this->dontSeeIn($page, $p.'Newsletters');
        $page = $this->ticketsPage($this->s['bob'], ['wf' => $p.'Newsletters']);
        $this->dontSeeIn($page, $p.'-BILLING-CAROL');
        // Carol sees only her assigned tickets of billing
        $page = $this->ticketsPage($this->s['carol']);
        $this->seeIn($page, $p.'Newsletters');
        $this->dontSeeIn($page, '[WP] '.$this->f);
        // overview: same rights
        $page = $this->actingAs($this->s['bob'])->get(route('refreshglobal.folders'));
        $page->assertStatus(200);
        $this->dontSeeIn($page, $p.'Newsletters');
        $this->seeIn($page, $this->s['support']->name);
    }

    public function testExportStateAndSavedViewKeepTheFolder()
    {
        $this->on();
        $p = $this->s['prefix'];
        $response = $this->actingAs($this->s['admin'])->get(route('refreshglobal.export', ['wf' => $this->f.'/Fournisseurs']));
        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString($p.'-SUPPORT-OPEN', $csv);
        $this->assertStringNotContainsString($p.'-SALES-OPEN', $csv);

        // automatic refresh: the folder is part of the list's fingerprint
        $a = $this->actingAs($this->s['admin'])->get(route('refreshglobal.state', ['wf' => $this->f]))->json()['fp'];
        $b = $this->actingAs($this->s['admin'])->get(route('refreshglobal.state', ['wf' => $this->f.'/Fournisseurs']))->json()['fp'];
        $this->assertNotSame($a, $b);

        $this->actingAs($this->s['admin'])->post(route('refreshglobal.views.store'), ['name' => 'Factures', 'wf' => $this->f])->assertStatus(302);
        $view = SavedView::forUser($this->s['admin'])->where('name', 'Factures')->first();
        $this->assertSame($this->f, \Modules\RefreshGlobal\Services\SavedViews::definition($view)['filters']['wfolder']);
        $page = $this->ticketsPage($this->s['admin'], ['view' => $view->id]);
        $this->seeIn($page, $p.'-SALES-OPEN');
        $this->dontSeeIn($page, $p.'-BILLING-CAROL');
    }

    public function testOverviewPage()
    {
        $this->on();
        $retention = \Schema::hasTable('workflowpacks_retention');
        if ($retention) {
            \DB::table('workflowpacks_retention')->insert(['folder' => $this->f, 'number' => 7, 'metric' => 'd', 'mode' => 'trash',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $rows = [];
        foreach (AutoFolders::overview($this->rightsBase($this->s['admin']), (new \Modules\RefreshGlobal\Services\MailboxAccess($this->s['admin']))->mailboxes()) as $row) {
            if (strpos($row['folder'], $this->s['prefix']) === 0) {
                $rows[$row['folder']] = $row;
            }
        }
        $this->assertSame([$this->f, $this->f.'/Fournisseurs', $this->s['prefix'].'Newsletters'], array_keys($rows));
        $this->assertSame(1, $rows[$this->f]['total']);       // sales open (spam left out)
        $this->assertSame(1, $rows[$this->f]['open']);
        $this->assertSame([(int) $this->s['sales']->id => 1], $rows[$this->f]['mailboxes']);
        $this->assertSame(1, $rows[$this->f.'/Fournisseurs']['depth']);
        if ($retention) {
            $this->assertSame(['number' => 7, 'metric' => 'd', 'mode' => 'trash'], $rows[$this->f]['cleanup']);
        }

        $page = $this->actingAs($this->s['admin'])->get(route('refreshglobal.folders'));
        $page->assertStatus(200);
        $this->seeIn($page, '[WP] '.$this->f.' › Fournisseurs');
        $this->seeIn($page, e(route('refreshglobal.tickets', ['wf' => $this->f, 'mb' => [$this->s['sales']->id]])));
        if ($retention) {
            $this->seeIn($page, __('refreshglobal::messages.cleanup_trash', ['delay' => __('refreshglobal::messages.delay_d', ['n' => 7])]));
        }
    }

    public function testDeletedTicketKeepsItsServerFolderForTheMailTrash()
    {
        $this->on();
        \Modules\RefreshGlobal\Services\Settings::setServerTrash(true);
        $c = $this->s['t_support_open'];
        $t = new \App\Thread();
        $t->conversation_id = $c->id;
        $t->type = \App\Thread::TYPE_CUSTOMER;
        $t->state = \App\Thread::STATE_PUBLISHED;
        $t->status = \App\Thread::STATUS_ACTIVE;
        $t->body = 'Body';
        $t->message_id = 'wp-'.$this->s['prefix'].'@example.test';
        $t->source_via = \App\Thread::PERSON_CUSTOMER;
        $t->source_type = \App\Thread::SOURCE_TYPE_EMAIL;
        $t->save();
        $r = $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), ['action' => 'delete_conversation_forever', 'conversation_id' => $c->id]);
        $this->assertSame('success', $r->json()['status']);
        $row = \DB::table(\Modules\RefreshGlobal\Services\MailServerTrash::TABLE)->where('message_id', 'wp-'.$this->s['prefix'].'@example.test')->first();
        $this->assertNotNull($row);
        $this->assertSame('INBOX.[WP] '.$this->f.'.Fournisseurs', $row->imap_path);
    }

    public function testFolderBannerAndBackWithoutTheFolder()
    {
        $this->on();
        $mb = [$this->s['support']->id, $this->s['sales']->id];
        $page = $this->ticketsPage($this->s['admin'], ['mb' => $mb, 'wf' => $this->f]);
        // banner with the way out of the folder (the other filters are kept)
        $this->seeIn($page, 'class="rg-folder-banner"');
        $this->seeIn($page, '<strong>[WP] '.$this->f.'</strong>');
        $this->seeIn($page, 'class="rg-folder-banner-exit" href="'.e(route('refreshglobal.tickets', ['mb' => $mb])).'"');
        // list a ticket opened here goes back to: the same filters, without the folder; its number of tickets
        $this->seeIn($page, 'data-back-url="'.e(route('refreshglobal.tickets', ['mb' => $mb])).'"');
        $this->seeIn($page, 'data-back-label="'.e(__('refreshglobal::messages.title')).'"');
        $all = (new \Modules\RefreshGlobal\Services\GlobalTicketQuery(new \Modules\RefreshGlobal\Services\MailboxAccess($this->s['admin']), ['mailboxes' => $mb]))->base()->count();
        $this->seeIn($page, 'data-back-count="'.$all.'"');
        // no folder: no banner, back to the same list
        $page = $this->ticketsPage($this->s['admin'], ['mb' => $mb]);
        $this->dontSeeIn($page, 'class="rg-folder-banner"');
        $this->seeIn($page, 'data-back-url="'.e(route('refreshglobal.tickets', ['mb' => $mb])).'"');
    }

    public function testDeletionLeavesTheFolder()
    {
        $this->on();
        \Modules\RefreshGlobal\Services\Settings::setDeleteGoesNext(false);
        \Modules\RefreshGlobal\Services\Settings::setDeletePermanently(false);
        \Modules\RefreshGlobal\Services\Settings::setKeepPosition(true);
        $this->ticketsPage($this->s['admin'], ['status' => [Conversation::STATUS_ACTIVE], 'wf' => $this->f])->assertStatus(200);
        $r = $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), ['action' => 'delete_conversation', 'conversation_id' => $this->s['t_sales_open']->id]);
        $this->assertSame('success', $r->json()['status']);
        $this->assertSame(route('refreshglobal.tickets', ['status' => [Conversation::STATUS_ACTIVE]]).'#rg-deleted='.$this->s['t_sales_open']->id, $r->json()['redirect_url']);
    }

    public function testTicketLandmark()
    {
        // ticket of a mailbox the user can see: its id and its mailbox (landmark above Refresh's views panel)
        $P = '\Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider';
        $this->assertSame([
            'conversation' => (int) $this->s['t_support_open']->id,
            'mailbox'      => __('refreshglobal::messages.conv_mailbox', ['name' => $this->s['support']->name]),
        ], $P::ticketLandmark($this->s['t_support_open']->id, $this->s['bob']));
        // Bob can not see the billing mailbox: nothing about it
        $this->assertSame([], $P::ticketLandmark($this->s['t_billing_other']->id, $this->s['bob']));
        $this->assertSame([], $P::ticketLandmark(0, $this->s['bob']));
    }

    protected function rightsBase($user)
    {
        $access = new \Modules\RefreshGlobal\Services\MailboxAccess($user);

        return (new \Modules\RefreshGlobal\Services\GlobalTicketQuery($access, \Modules\RefreshGlobal\Services\GlobalTicketQuery::normalize([], $access)))->base();
    }
}
