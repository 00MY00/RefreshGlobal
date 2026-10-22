<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Updates;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Updates (1.9.0): no updater of its own; FreeScout's Modules page or the UpdateGuard module, through the standard keys
 * of module.json, and the health command refreshglobal:health that UpdateGuard runs after an update.
 */
class UpdatesTest extends TestCase
{
    protected function manifest()
    {
        return json_decode(file_get_contents(__DIR__.'/../../module.json'), true);
    }

    public function testModuleJsonDeclaresTheStandardUpdateKeys()
    {
        $json = $this->manifest();
        $this->assertSame('../modules/refreshglobal/img/module.svg', $json['img']);
        $this->assertFileExists(__DIR__.'/../../Public/img/module.svg');
        // FreeScout's Modules page (ModulesController) and UpdateGuard read these two addresses
        $this->assertSame('https://github.com/00MY00/RefreshGlobal/releases/latest/download/version.txt', $json['latestVersionUrl']);
        $this->assertSame('https://github.com/00MY00/RefreshGlobal/releases/latest/download/RefreshGlobal.zip', $json['latestVersionZipUrl']);
        // UpdateGuard: native health check, migrations that the previous version can live with
        $this->assertSame('refreshglobal:health', $json['healthCommand']);
        $this->assertSame('backward-compatible', $json['updateGuard']['migrations']);
    }

    public function testHealthCommand()
    {
        $this->assertSame(0, \Artisan::call('refreshglobal:health', ['--json' => true]));
        $out = json_decode(trim(\Artisan::output()), true);
        $this->assertIsArray($out);
        // schema health 1.0 (docs/interop/schemas/health-1.0.schema.json)
        $this->assertSame('1.0', $out['schema']);
        $this->assertSame('healthy', $out['status']);
        $this->assertSame('RefreshGlobal', $out['module']);
        $this->assertSame($this->manifest()['version'], $out['version']);
        $this->assertSame('pass', $out['checks']['files']);
        $this->assertSame('pass', $out['checks']['page']);
        $this->assertSame('pass', $out['checks']['interop_manifest']);
        $this->assertNotSame('fail', $out['checks']['database'] ?? 'pass');
        $this->assertSame('1.0.0', $out['interop']['protocol']);
        // UpdateGuard detects --json from the command's definition
        $this->assertTrue(\Artisan::all()['refreshglobal:health']->getDefinition()->hasOption('json'));
    }

    public function testHealthFailsWhenTheModuleIsBlocked()
    {
        // a blocking component missing (simulated): not healthy
        $this->integration('routes.RG-ROUTE-03', ['route' => 'refreshglobal.does_not_exist', 'severity' => 'blocking', 'source' => 'test']);
        $this->assertSame(1, \Artisan::call('refreshglobal:health', ['--json' => true]));
        $out = json_decode(trim(\Artisan::output()), true);
        $this->assertSame('unhealthy', $out['status']);
        $this->assertSame('blocking', $out['state']);
    }

    public function testNoUpdaterOfItsOwnAnyMore()
    {
        $commands = \Artisan::all();
        $this->assertArrayNotHasKey('refreshglobal:update', $commands);
        // still there: called by the updater of 1.8.x and older right after it installs this version
        $this->assertArrayHasKey('refreshglobal:check', $commands);
        $this->assertArrayHasKey('refreshglobal:selftest', $commands);
        $this->assertFalse(\Route::has('refreshglobal.update_now'));
        $this->assertFalse(\Route::has('refreshglobal.check_update'));
        $scheduled = array_map(function ($e) {
            return (string) $e->command;
        }, app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertEmpty(array_filter($scheduled, function ($c) {
            return strpos($c, 'refreshglobal:update') !== false;
        }));
    }

    public function testSettingsAndDiagnosticSayWhoUpdates()
    {
        $manager = Updates::updateGuard() ? 'updateguard' : 'freescout';
        foreach ([route('settings', ['section' => 'refreshglobal']), route('refreshglobal.diagnostic')] as $url) {
            $page = $this->actingAs($this->s['admin'])->get($url);
            $page->assertStatus(200);
            $this->seeIn($page, 'data-rg-updates="'.$manager.'"');
            $this->seeIn($page, '<strong>'.$this->manifest()['version'].'</strong>');
            if ($manager === 'freescout') {
                // FreeScout's Modules page, and UpdateGuard recommended
                $this->seeIn($page, e(route('modules')));
                $this->seeIn($page, Updates::UPDATEGUARD_URL);
            } else {
                $this->seeIn($page, e(route('updateguard.module', ['name' => 'RefreshGlobal'])));
                $this->dontSeeIn($page, 'rg-updates-advice');
            }
        }
    }
}
