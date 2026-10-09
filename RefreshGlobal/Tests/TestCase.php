<?php

namespace Modules\RefreshGlobal\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Tests\Support\Fixtures;

/**
 * Base of the module's tests. They run inside a FreeScout installation (Modules/RefreshGlobal), on its database,
 * each test inside a transaction that is rolled back. See Tests/README.md.
 */
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use DatabaseTransactions;

    /** @var array */
    protected $s;

    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // In a real request the HTTP kernel exists before the providers boot. In tests it is built later and resets the
        // "web" group (Illuminate\Foundation\Http\Kernel::__construct): the module's middleware is added again.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $router = $this->app['router'];
        if (!in_array(\Modules\RefreshGlobal\Http\Middleware\AfterDelete::class, $router->getMiddlewareGroups()['web'] ?? [], true)) {
            $router->pushMiddlewareToGroup('web', \Modules\RefreshGlobal\Http\Middleware\AfterDelete::class);
        }
        $prefix = 'RGT'.Fixtures::uid();
        $this->s = Fixtures::scenario($prefix);
        $this->s['prefix'] = $prefix;
        CompatibilityChecker::forget();
        \Cache::flush();
    }

    protected function tearDown(): void
    {
        CompatibilityChecker::forget();
        parent::tearDown();
        // Laravel's HandleExceptions bootstrapper installs handlers PHPUnit 10+ reports as "risky"
        restore_error_handler();
        restore_exception_handler();
    }

    /** Changes one entry of the integration list (simulates a missing component). */
    protected function integration($key, $value)
    {
        config(['refreshglobal_integration.'.$key => $value]);
        CompatibilityChecker::forget();
        \Cache::flush();
    }

    protected function refreshInstalled()
    {
        return (new CompatibilityChecker())->refreshUsable();
    }

    /** Laravel 5.5's assertSee() calls an assertion removed from recent PHPUnit versions: plain string checks instead. */
    protected function seeIn($response, $text)
    {
        $this->assertStringContainsString((string) $text, $response->getContent());

        return $response;
    }

    protected function dontSeeIn($response, $text)
    {
        $this->assertStringNotContainsString((string) $text, $response->getContent());

        return $response;
    }

    protected function ticketsPage($user, array $query = [])
    {
        return $this->actingAs($user)->get(route('refreshglobal.tickets', $query));
    }
}
