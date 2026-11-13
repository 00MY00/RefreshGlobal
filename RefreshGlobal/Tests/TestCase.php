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
        // tables of optional modules the tests need, created before the test transaction starts
        if (!$this->app) {
            $this->refreshApplication();
        }
        \Modules\RefreshGlobal\Tests\Support\WorkflowPacks::ensureTable();
        \Modules\RefreshGlobal\Services\AutoFolders::reset();
        \Modules\RefreshGlobal\Services\AutoFolders::$assumeActive = false;
        parent::setUp();
        // In a real request the HTTP kernel exists before the providers boot. In tests it is built later and resets the
        // "web" group (Illuminate\Foundation\Http\Kernel::__construct): the module's middleware is added again.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $router = $this->app['router'];
        foreach ([\Modules\RefreshGlobal\Http\Middleware\AfterDelete::class, \Modules\RefreshGlobal\Http\Middleware\CheckFromAlias::class] as $middleware) {
            if (!in_array($middleware, $router->getMiddlewareGroups()['web'] ?? [], true)) {
                $router->pushMiddlewareToGroup('web', $middleware);
            }
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

    /**
     * Logs a user in for the next requests. FreeScout 1.8.250+ adds Laravel's AuthenticateSession middleware
     * (app/Http/Kernel.php:38), which keeps the logged-in user's password hash in the session and logs out when another
     * user shows up in the same session: when a test switches users, the previous user's hash is dropped first, as a
     * real logout / login would do.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $driver = null)
    {
        if ($this->app->bound('session.store')) {
            $this->app['session.store']->forget('password_hash');
        }

        return parent::actingAs($user, $driver);
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
