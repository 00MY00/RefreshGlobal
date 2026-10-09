<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\Compatibility\Messages;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Simulates missing components (Refresh, a stylesheet, a view, a method, a route, a column) and checks the state,
 * the message and what the page does.
 */
class CompatibilityTest extends TestCase
{
    protected function findResult(array $report, $code)
    {
        foreach ($report['results'] as $r) {
            if ($r['code'] === $code) {
                return $r;
            }
        }
        $this->fail('No result '.$code);
    }

    public function testCurrentInstallationIsNotBlocking()
    {
        $report = (new CompatibilityChecker())->run();
        $this->assertNotSame('blocking', $report['state']);
    }

    public function testRefreshMissingGivesDegradedNativeSkin()
    {
        $s = $this->s;
        $this->integration('refresh.alias', 'refresh-not-installed');
        $report = (new CompatibilityChecker())->run();
        $this->assertSame('failed', $this->findResult($report, 'RG-REF-01')['status']);
        $this->assertSame('skipped', $this->findResult($report, 'RG-CSS-01')['status']);
        $this->assertSame('degraded', $report['state']);
        $this->assertSame('native', $report['skin']);

        $r = $this->ticketsPage($s['admin']);
        $r->assertStatus(200);
        $this->seeIn($r, 'data-rg-skin="native"');
        $this->seeIn($r, '[RG-REF-01]');
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');

        // a regular user gets the short message, not the technical details
        $r = $this->ticketsPage($s['bob']);
        $r->assertStatus(200);
        $this->dontSeeIn($r, '[RG-REF-01]');
        $this->seeIn($r, $s['prefix'].'-SUPPORT-OPEN');
    }

    public function testMissingRefreshStylesheet()
    {
        if (!$this->refreshInstalled()) {
            $this->markTestSkipped('Refresh is not installed in this FreeScout.');
        }
        $this->integration('refresh.css', ['Public/css/refresh.css', 'Public/css/does-not-exist.css']);
        $report = (new CompatibilityChecker())->run();
        $css = $this->findResult($report, 'RG-CSS-01');
        $this->assertSame('failed', $css['status']);
        $this->assertSame('degraded', $report['state']);
        $this->assertSame('native', $report['skin']);
        $this->assertStringContainsString('Modules/Refresh/Public/css/does-not-exist.css', $css['details']);

        $block = Messages::block($css, 'en');
        $lines = explode("\n", $block);
        $this->assertSame("[RG-CSS-01] Refresh's stylesheet cannot be found.", $lines[0]);
        $this->assertStringStartsWith('Expected: Modules/Refresh/Public/css/refresh.css', $lines[1]);
        $this->assertStringContainsString('Effect: The "All mailboxes" page is shown with FreeScout\'s standard look.', $block);
        $this->assertStringContainsString('Action: Check the installed Refresh version, then run php artisan refreshglobal:check.', $block);
        $this->assertStringContainsString('In the meantime: [Open my mailboxes]', $block);

        $fr = Messages::block($css, 'fr');
        $this->assertStringContainsString('[RG-CSS-01] Le style de Refresh est introuvable.', $fr);
        $this->assertStringContainsString('Attendu : Modules/Refresh/Public/css/refresh.css', $fr);
        $this->assertStringContainsString('En attendant : [Ouvrir mes boîtes]', $fr);
    }

    public function testMissingRefreshViewIsDegraded()
    {
        if (!$this->refreshInstalled()) {
            $this->markTestSkipped('Refresh is not installed in this FreeScout.');
        }
        $this->integration('views.RG-VIEW-05.view', 'refresh::does_not_exist');
        $report = (new CompatibilityChecker())->run();
        $this->assertSame('failed', $this->findResult($report, 'RG-VIEW-05')['status']);
        $this->assertSame('degraded', $report['state']);
    }

    public function testMissingCoreViewBlocksTheList()
    {
        $s = $this->s;
        $this->integration('views.RG-VIEW-02.view', 'conversations/does_not_exist');
        $r = $this->ticketsPage($s['admin']);
        $r->assertStatus(200);
        $this->seeIn($r, '[RG-VIEW-02]');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($r, 'data-rg-page="blocked"');
    }

    public function testMissingMethodBlocksTheList()
    {
        $s = $this->s;
        $this->integration('core.RG-CORE-01.methods', ['mailboxesCanView', 'methodRemovedInAFutureVersion']);
        $report = (new CompatibilityChecker())->run();
        $core = $this->findResult($report, 'RG-CORE-01');
        $this->assertSame('failed', $core['status']);
        $this->assertStringContainsString('App\User::methodRemovedInAFutureVersion()', $core['details']);
        $this->assertSame('blocking', $report['state']);

        $r = $this->ticketsPage($s['bob']);
        $r->assertStatus(200);
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->dontSeeIn($r, 'A FreeScout class, method or constant'); // technical details are for admins only
        $this->seeIn($r, $s['support']->name); // fallback links to the native mailboxes

        // the export is blocked too
        $this->dontSeeIn($this->actingAs($s['bob'])->get(route('refreshglobal.export')), $s['prefix'].'-SUPPORT-OPEN');
    }

    public function testMissingRouteBlocksTheList()
    {
        $s = $this->s;
        $this->integration('routes.RG-ROUTE-99', ['route' => 'route.removed.in.future', 'severity' => 'blocking']);
        $r = $this->ticketsPage($s['admin']);
        $this->seeIn($r, '[RG-ROUTE-99]');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
    }

    public function testMissingColumnBlocksTheList()
    {
        $this->integration('database.RG-DB-01.columns', ['id', 'column_removed_in_future']);
        $report = (new CompatibilityChecker())->run();
        $db = $this->findResult($report, 'RG-DB-01');
        $this->assertSame('failed', $db['status']);
        $this->assertStringContainsString('conversations.column_removed_in_future', $db['details']);
        $this->assertSame('blocking', $report['state']);
    }

    public function testMissingHookIsDegraded()
    {
        $this->integration('hooks.RG-HOOK-01.needle', "@action('menu.renamed')");
        $report = (new CompatibilityChecker())->run();
        $this->assertSame('failed', $this->findResult($report, 'RG-HOOK-01')['status']);
        $this->assertSame('degraded', $report['state']);
    }

    public function testVersionOutsideRangeIsOnlyAWarning()
    {
        $this->integration('versions.freescout', ['min' => '0.1.0', 'max_exclusive' => '0.2.0', 'tested' => '0.1.0']);
        $report = (new CompatibilityChecker())->run();
        $this->assertSame('failed', $this->findResult($report, 'RG-ENV-01')['status']);
        $this->assertContains($report['state'], ['warning', 'degraded']);
        $this->assertNotSame('blocking', $report['state']);
    }

    public function testCacheIsRebuiltWhenAVersionChanges()
    {
        $key1 = CompatibilityChecker::cacheKey();
        config(['app.version' => '9.9.9']);
        $this->assertNotSame($key1, CompatibilityChecker::cacheKey());
    }

    public function testArtisanCommandExitCode()
    {
        $this->assertContains(\Artisan::call('refreshglobal:check'), [0, 1]);
        $this->integration('routes.RG-ROUTE-99', ['route' => 'route.removed.in.future', 'severity' => 'blocking']);
        $this->assertSame(2, \Artisan::call('refreshglobal:check'));
    }

    public function testFailuresAreLogged()
    {
        $this->integration('routes.RG-ROUTE-99', ['route' => 'route.removed.in.future', 'severity' => 'blocking']);
        $logged = [];
        \Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($event) use (&$logged) {
            $logged[] = $event->level.' '.$event->message;
        });
        CompatibilityChecker::cached();
        $this->assertStringContainsString('warning [RefreshGlobal] [RG-ROUTE-99]', implode("\n", $logged));
    }
}
