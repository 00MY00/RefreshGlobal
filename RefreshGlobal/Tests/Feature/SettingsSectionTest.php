<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Services\Update\Updater;
use Modules\RefreshGlobal\Tests\TestCase;

/** Manage › Settings › RefreshGlobal and the "Update now" button. */
class SettingsSectionTest extends TestCase
{
    public function testSectionIsListedAndRendered()
    {
        $r = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $r->assertStatus(200);
        $this->seeIn($r, 'name="settings['.Settings::REPLACE_REFRESH_TICKETS.']"');
        $this->seeIn($r, 'name="settings['.Updater::OPTION.']"');
        $this->seeIn($r, route('refreshglobal.update_now'));
        // listed in the settings menu of FreeScout
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('settings')), route('settings', ['section' => 'refreshglobal']));
    }

    public function testFreeScoutSavesTheSettings()
    {
        Settings::setReplaceRefreshTickets(false);
        Updater::setEnabled(false);
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
            Settings::REPLACE_REFRESH_TICKETS => '1',
            Updater::OPTION                   => '1',
        ]])->assertStatus(302);
        $this->assertTrue(Settings::replaceRefreshTickets());
        $this->assertTrue(Updater::enabled());

        // unchecked boxes post the hidden "0"
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
            Settings::REPLACE_REFRESH_TICKETS => '0',
            Updater::OPTION                   => '0',
        ]])->assertStatus(302);
        $this->assertFalse(Settings::replaceRefreshTickets());
        $this->assertFalse(Updater::enabled());
    }

    public function testRegularUserHasNoAccess()
    {
        $this->actingAs($this->s['bob'])->get(route('settings', ['section' => 'refreshglobal']))->assertStatus(403);
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.update_now'))->assertStatus(403);
    }

    public function testUpdateNowIsQueuedForTheScheduler()
    {
        Updater::clearRequest();
        $this->assertSame(0, \Artisan::call('refreshglobal:update', ['--requested' => true])); // nothing requested: no-op
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.update_now'), ['back' => 'settings'])
            ->assertRedirect(route('settings', ['section' => 'refreshglobal']));
        $this->assertNotSame('', Updater::pendingRequest());

        $commands = array_map(function ($e) {
            return (string) $e->command;
        }, app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertNotEmpty(array_filter($commands, function ($c) {
            return strpos($c, 'refreshglobal:update --requested') !== false;
        }));
        Updater::clearRequest();
    }
}
