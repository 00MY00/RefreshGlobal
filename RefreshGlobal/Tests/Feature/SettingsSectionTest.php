<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/** Manage › Settings › RefreshGlobal. */
class SettingsSectionTest extends TestCase
{
    public function testSectionIsListedAndRendered()
    {
        $r = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $r->assertStatus(200);
        $this->seeIn($r, 'name="settings['.Settings::REPLACE_REFRESH_TICKETS.']"');
        foreach ([Settings::GLOBAL_DASHBOARD, Settings::SHOW_MAILBOX, Settings::DELETE_GOES_NEXT, Settings::DELETE_PERMANENTLY] as $key) {
            $this->seeIn($r, 'name="settings['.$key.']"');
        }
        $this->seeIn($r, 'onoffswitch-checkbox');
        // who updates the module (FreeScout's Modules page or UpdateGuard)
        $this->seeIn($r, 'class="rg-updates"');
        // listed in the settings menu of FreeScout
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('settings')), route('settings', ['section' => 'refreshglobal']));
    }

    public function testFreeScoutSavesTheSettings()
    {
        Settings::setReplaceRefreshTickets(false);
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
            Settings::REPLACE_REFRESH_TICKETS => '1',
        ]])->assertStatus(302);
        $this->assertTrue(Settings::replaceRefreshTickets());

        // unchecked boxes post the hidden "0"
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
            Settings::REPLACE_REFRESH_TICKETS => '0',
        ]])->assertStatus(302);
        $this->assertFalse(Settings::replaceRefreshTickets());
    }

    public function testDefaultsAndNewSwitches()
    {
        foreach ([Settings::GLOBAL_DASHBOARD, Settings::SHOW_MAILBOX, Settings::DELETE_GOES_NEXT, Settings::DELETE_PERMANENTLY] as $key) {
            \App\Option::remove($key);
        }
        // defaults: dashboard of all mailboxes, mailbox shown, back to the list, trash
        $this->assertTrue(Settings::globalDashboard());
        $this->assertTrue(Settings::showMailbox());
        $this->assertFalse(Settings::deleteGoesNext());
        $this->assertFalse(Settings::deletePermanently());

        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
            Settings::SHOW_MAILBOX       => '0',
            Settings::DELETE_GOES_NEXT   => '1',
            Settings::DELETE_PERMANENTLY => '1',
        ]])->assertStatus(302);
        $this->assertFalse(Settings::showMailbox());
        $this->assertTrue(Settings::deleteGoesNext());
        $this->assertTrue(Settings::deletePermanently());
    }

    public function testRegularUserHasNoAccess()
    {
        $this->actingAs($this->s['bob'])->get(route('settings', ['section' => 'refreshglobal']))->assertStatus(403);
    }
}
