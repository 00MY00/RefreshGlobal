<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/** Entry points in Refresh's interface: phone tab (shell.js) and the "replace Refresh's Tickets" option. */
class NavigationTest extends TestCase
{
    public function testShellScriptIsInTheBundle()
    {
        $scripts = \Eventy::filter('javascripts', []);
        $this->assertContains(\Module::getPublicPath('refreshglobal').'/js/shell.js', $scripts);
        $this->assertFileExists(__DIR__.'/../../Public/js/shell.js');
    }

    public function testSettingsAreWrittenForRefreshPages()
    {
        if (!$this->refreshInstalled()) {
            $this->markTestSkipped('Refresh is not installed in this FreeScout.');
        }
        Settings::setReplaceRefreshTickets(false);
        $r = $this->ticketsPage($this->s['bob']);
        $this->seeIn($r, '<meta name="refreshglobal"');
        $this->seeIn($r, '&quot;replace&quot;:false');
        $this->seeIn($r, '&quot;active&quot;:true');

        Settings::setReplaceRefreshTickets(true);
        $this->seeIn($this->ticketsPage($this->s['bob']), '&quot;replace&quot;:true');
    }

    public function testAdminTogglesTheOption()
    {
        Settings::setReplaceRefreshTickets(false);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.navigation'), ['replace_refresh_tickets' => 1])->assertStatus(302);
        $this->assertTrue(Settings::replaceRefreshTickets());
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.navigation'), ['replace_refresh_tickets' => 0])->assertStatus(302);
        $this->assertFalse(Settings::replaceRefreshTickets());

        $this->actingAs($this->s['bob'])->post(route('refreshglobal.navigation'), ['replace_refresh_tickets' => 1])->assertStatus(403);
        $this->assertFalse(Settings::replaceRefreshTickets());
    }
}
