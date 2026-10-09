<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Update\Updater;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Settings of the automatic update. The update itself (download, swap, rollback) replaces the module's files, so it
 * is tested in disposable containers: tests/installer/run_tests.sh auto-update.
 */
class UpdateSettingsTest extends TestCase
{
    public function testEnableDisableFromTheCommand()
    {
        $this->assertSame(0, \Artisan::call('refreshglobal:update', ['--enable' => true]));
        $this->assertTrue(Updater::enabled());
        $this->assertSame(0, \Artisan::call('refreshglobal:update', ['--disable' => true]));
        $this->assertFalse(Updater::enabled());
    }

    public function testDailyTaskIsScheduled()
    {
        $commands = [];
        foreach (app(\Illuminate\Console\Scheduling\Schedule::class)->events() as $event) {
            $commands[] = (string) $event->command;
        }
        $this->assertNotEmpty(array_filter($commands, function ($c) {
            return strpos($c, 'refreshglobal:update --scheduled') !== false;
        }), 'refreshglobal:update --scheduled is not in the schedule');
    }

    public function testDiagnosticShowsAndTogglesTheSetting()
    {
        $admin = $this->s['admin'];
        Updater::setEnabled(false);
        $r = $this->actingAs($admin)->get(route('refreshglobal.diagnostic'));
        $r->assertStatus(200);
        $this->seeIn($r, route('refreshglobal.auto_update'));

        $this->actingAs($admin)->post(route('refreshglobal.auto_update'), ['enabled' => 1])->assertStatus(302);
        $this->assertTrue(Updater::enabled());
        $this->actingAs($admin)->post(route('refreshglobal.auto_update'), ['enabled' => 0])->assertStatus(302);
        $this->assertFalse(Updater::enabled());
    }

    public function testOnlyAdminsCanToggle()
    {
        Updater::setEnabled(false);
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.auto_update'), ['enabled' => 1])->assertStatus(403);
        $this->assertFalse(Updater::enabled());
    }

    public function testModuleCardImage()
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../module.json'), true);
        $this->assertSame('../modules/refreshglobal/img/module.svg', $json['img']);
        $this->assertFileExists(__DIR__.'/../../Public/img/module.svg');
        // FreeScout's own updater (no rollback) must not take over: no latestVersionUrl
        $this->assertArrayNotHasKey('latestVersionUrl', $json);
    }
}
