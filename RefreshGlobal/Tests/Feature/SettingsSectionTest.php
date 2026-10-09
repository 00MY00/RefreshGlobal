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
        // the check writes the update status file: restored at the end
        $statusFile = Updater::dir('update-status.json');
        $savedStatus = is_file($statusFile) ? file_get_contents($statusFile) : null;
        try {
            $this->checkForUpdates();
        } finally {
            if ($savedStatus === null) {
                @unlink($statusFile);
            } else {
                file_put_contents($statusFile, $savedStatus);
            }
        }
    }

    protected function checkForUpdates()
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

        // no release published (like a GitHub repository with tags only): the main branch is read instead
        unlink($dir.'/module.json');
        $branch = $dir.'-main.json';
        file_put_contents($branch, json_encode(['alias' => 'refreshglobal', 'version' => '98.0.0', 'requiredAppVersion' => '1.8.0']));
        config(['refreshglobal.update_branch_manifest' => $branch, 'refreshglobal.update_branch_zip' => $dir.'-main.zip']);
        $msg = (string) $check()->getSession()->get('flash_success');
        $this->assertStringContainsString('98.0.0', $msg);
        $this->assertStringContainsString(__('refreshglobal::messages.update_source_branch'), $msg);
        $this->assertSame('branch', Updater::status()['source']);

        // neither a release nor the branch: clear message with the address
        unlink($branch);
        $this->assertStringContainsString($dir, (string) $check()->getSession()->get('flash_error'));
        // fallback turned off: same message
        config(['refreshglobal.update_branch_manifest' => '']);
        $this->assertStringContainsString($dir, (string) $check()->getSession()->get('flash_error'));
        rmdir($dir);

        // admins only
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.check_update'))->assertStatus(403);
    }

    /** Up to date after an update: no "latest version" shown; requested / running: said clearly, buttons disabled. */
    public function testUpdateStates()
    {
        $statusFile = Updater::dir('update-status.json');
        $savedStatus = is_file($statusFile) ? file_get_contents($statusFile) : null;
        @mkdir(Updater::dir(), 0775, true);
        $page = function () {
            return $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        };
        $current = Updater::currentVersion();
        try {
            Updater::clearRequest();

            // right after a successful update: installed = latest (from the main branch)
            file_put_contents($statusFile, json_encode(['latest' => $current, 'source' => 'branch', 'last_result' => 'updated', 'last_from' => '0.9.0', 'last_to' => $current]));
            $r = $page();
            $this->seeIn($r, 'data-rg-update-state="up_to_date"');
            $this->seeIn($r, __('refreshglobal::messages.update_state_up_to_date'));
            $this->dontSeeIn($r, e(__('refreshglobal::messages.update_source_branch')));
            $this->dontSeeIn($r, __('refreshglobal::messages.update_available'));

            // newer version
            file_put_contents($statusFile, json_encode(['latest' => '99.0.0', 'source' => 'branch']));
            $r = $page();
            $this->seeIn($r, 'data-rg-update-state="available"');
            $this->seeIn($r, '99.0.0');

            // requested: shown, buttons disabled, the page reloads itself
            Updater::requestUpdate();
            $r = $page();
            $this->seeIn($r, 'data-rg-update-state="requested"');
            $this->seeIn($r, 'window.location.reload()');
            $this->seeIn($r, 'disabled');
            Updater::clearRequest();

            // running: another process holds the update lock
            $lock = fopen(Updater::dir('update.lock'), 'c');
            flock($lock, LOCK_EX);
            file_put_contents($statusFile, json_encode(['latest' => '99.0.0', 'running_to' => '99.0.0']));
            try {
                $this->assertTrue(Updater::isRunning());
                $r = $page();
                $this->seeIn($r, 'data-rg-update-state="running"');
                $this->seeIn($r, e(__('refreshglobal::messages.update_state_running', ['version' => '99.0.0'])));
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            $this->assertFalse(Updater::isRunning());
        } finally {
            Updater::clearRequest();
            if ($savedStatus === null) {
                @unlink($statusFile);
            } else {
                file_put_contents($statusFile, $savedStatus);
            }
        }
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
