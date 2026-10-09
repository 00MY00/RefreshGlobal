<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/** Refresh's "My dashboard" for all mailboxes, the Refresh view filter (rv) and the correction of Refresh's strings. */
class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->refreshInstalled()) {
            $this->markTestSkipped('Refresh is not installed in this FreeScout.');
        }
        Settings::setGlobalDashboard(true);
    }

    public function testDashboardCoversEveryMailboxOfTheUser()
    {
        $r = $this->actingAs($this->s['bob'])->get(route('dashboard'));
        $r->assertStatus(200);
        // Refresh's block (first mailbox only) is replaced by the module's: one dashboard only
        $this->seeIn($r, 'class="rf-dash rg-dash"');
        $this->dontSeeIn($r, 'class="rf-dash"');
        $this->seeIn($r, $this->s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($r, $this->s['prefix'].'-SALES-OPEN');
        // never a mailbox bob can not see
        $this->dontSeeIn($r, $this->s['prefix'].'-BILLING-');
        // tiles lead to the "All mailboxes" page
        $this->seeIn($r, e(route('refreshglobal.tickets', ['rv' => 'unresolved'])));
    }

    public function testOptionOffKeepsRefreshDashboard()
    {
        Settings::setGlobalDashboard(false);
        $r = $this->actingAs($this->s['bob'])->get(route('dashboard'));
        $r->assertStatus(200);
        $this->dontSeeIn($r, 'rg-dash');
        $this->seeIn($r, 'class="rf-dash"');
    }

    public function testRefreshViewFilter()
    {
        $p = $this->s['prefix'];
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'pending']);
        $r->assertStatus(200);
        $this->seeIn($r, $p.'-SUPPORT-PENDING');
        $this->dontSeeIn($r, $p.'-SUPPORT-OPEN');
        $this->dontSeeIn($r, $p.'-SALES-OPEN');
        $this->seeIn($r, 'rg-rview-chip');

        // rights still apply: Refresh's view never opens another mailbox
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'unresolved', 'mb' => [$this->s['billing']->id]]);
        $this->dontSeeIn($r, $p.'-BILLING-');

        // unknown view: ignored
        $r = $this->ticketsPage($this->s['bob'], ['rv' => 'no-such-view']);
        $this->seeIn($r, $p.'-SUPPORT-OPEN');
        $this->dontSeeIn($r, 'rg-rview-chip');
    }

    public function testRefreshFrenchStringsAreCorrected()
    {
        // dashboard cached in English first: the French page must not reuse those labels
        $this->seeIn($this->actingAs($this->s['admin'])->withSession(['user_locale' => 'en'])->get(route('dashboard')), '>Unresolved<');
        // FreeScout puts the user's language in the session at login (Localize middleware reads "user_locale")
        $r = $this->actingAs($this->s['admin'])->withSession(['user_locale' => 'fr'])->get(route('dashboard'));
        $r->assertStatus(200);
        $this->seeIn($r, 'meta name="refresh-l10n"');
        $this->seeIn($r, '"Créé il y a :time"');
        $this->dontSeeIn($r, '>Unresolved<');
    }
}
