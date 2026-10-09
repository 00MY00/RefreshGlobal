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
        foreach ([Settings::GLOBAL_DASHBOARD, Settings::SHOW_MAILBOX, Settings::DELETE_GOES_NEXT, Settings::DELETE_PERMANENTLY] as $key) {
            $this->seeIn($r, 'name="settings['.$key.']"');
        }
        $this->seeIn($r, 'onoffswitch-checkbox');
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

    /** "Check for updates": reads the release's module.json now, installs nothing. */
    public function testCheckForUpdates()
    {
        $dir = sys_get_temp_dir().'/rg-check-'.uniqid();
        mkdir($dir);
        config(['refreshglobal.update_url' => $dir]);
        $release = function ($version, $required = '1.8.0') use ($dir) {
            file_put_contents($dir.'/module.json', json_encode(['alias' => 'refreshglobal', 'version' => $version, 'requiredAppVersion' => $required]));
        };
        $check = function () {
            return $this->actingAs($this->s['admin'])->post(route('refreshglobal.check_update'), ['back' => 'settings']);
        };
        $current = Updater::currentVersion();

        // newer version available
        $release('99.0.0');
        $r = $check()->assertRedirect(route('settings', ['section' => 'refreshglobal']));
        $this->assertStringContainsString('99.0.0', (string) $r->getSession()->get('flash_success'));
        $this->assertSame('99.0.0', Updater::status()['latest']);
        $this->assertSame($current, Updater::currentVersion()); // nothing installed

        // up to date
        $release($current);
        $this->assertStringContainsString($current, (string) $check()->getSession()->get('flash_success'));

        // needs a newer FreeScout
        $release('99.0.0', '99.0.0');
        $this->assertStringContainsString('99.0.0', (string) $check()->getSession()->get('flash_error'));

        // release unreachable
        unlink($dir.'/module.json');
        $this->assertNotEmpty($check()->getSession()->get('flash_error'));
        rmdir($dir);

        // admins only
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.check_update'))->assertStatus(403);
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
