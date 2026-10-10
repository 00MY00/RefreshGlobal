<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/** Automatic refresh: fingerprint of the list (GET /refresh-global/state), with the page's filters and rights. */
class AutoRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Settings::setAutoRefresh(30);
    }

    protected function fp($user, array $query = ['reset' => 1])
    {
        $r = $this->actingAs($user)->get(route('refreshglobal.state', $query));
        $r->assertStatus(200);

        return $r->json()['fp'];
    }

    public function testPageCarriesTheFingerprintOfWhatItShows()
    {
        $r = $this->ticketsPage($this->s['bob'], ['reset' => 1]);
        $this->seeIn($r, 'data-refresh="30"');
        $this->seeIn($r, 'data-fp="'.$this->fp($this->s['bob']).'"');
        $this->seeIn($r, 'data-state-url="'.e(route('refreshglobal.state', ['reset' => 1])).'"');
    }

    public function testChangesInTheUsersMailboxesOnly()
    {
        $bob = $this->s['bob'];
        $before = $this->fp($bob);
        $this->assertSame($before, $this->fp($bob)); // stable

        // a ticket in a mailbox bob can not see: nothing for him
        Fixtures::conversation($this->s['billing'], $this->s['prefix'].'-NOT-FOR-BOB');
        $this->assertSame($before, $this->fp($bob));

        // a new ticket in one of his mailboxes
        Fixtures::conversation($this->s['support'], $this->s['prefix'].'-NEW');
        $this->assertNotSame($before, $this->fp($bob));
    }

    public function testFiltersOfThePageAreApplied()
    {
        $bob = $this->s['bob'];
        $query = ['mb' => [$this->s['support']->id]];
        $before = $this->fp($bob, $query);
        Fixtures::conversation($this->s['sales'], $this->s['prefix'].'-OTHER-MAILBOX');
        $this->assertSame($before, $this->fp($bob, $query));

        // a ticket of the list modified (status changed): new fingerprint
        $t = $this->s['t_support_open'];
        $t->status = \App\Conversation::STATUS_CLOSED;
        $t->updated_at = date('Y-m-d H:i:s', time() + 5);
        $t->save();
        $this->assertNotSame($before, $this->fp($bob, $query));
    }

    public function testSettingOffAndBounds()
    {
        Settings::setAutoRefresh(0);
        $this->seeIn($this->ticketsPage($this->s['bob']), 'data-refresh="0"');
        foreach (['5' => Settings::AUTO_REFRESH_MIN, '0' => 0, '45' => 45, '999999' => Settings::AUTO_REFRESH_MAX] as $posted => $expected) {
            $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
                Settings::AUTO_REFRESH => $posted,
            ]])->assertStatus(302);
            $this->assertSame($expected, Settings::autoRefresh(), "posted $posted");
        }
    }

    public function testGuestsGetNothing()
    {
        $this->get(route('refreshglobal.state'))->assertStatus(302); // to the login page
    }
}
