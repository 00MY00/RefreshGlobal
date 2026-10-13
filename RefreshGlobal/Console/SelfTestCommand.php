<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;

/**
 * php artisan refreshglobal:selftest — renders the "All mailboxes" page for an active administrator, through
 * FreeScout's HTTP kernel (routes, middlewares, views), without a browser. Also part of refreshglobal:health, and
 * still called by the updater of RefreshGlobal 1.8.x and older right after it installs a newer version.
 * Exit code 0: HTTP 200 with the list (or its empty state), or no administrator; 1 otherwise.
 */
class SelfTestCommand extends Command
{
    protected $signature = 'refreshglobal:selftest';

    protected $description = 'RefreshGlobal: render the "All mailboxes" page for an administrator and check it';

    public function handle()
    {
        list($ok, $message) = self::render($this->laravel);
        $ok ? $this->info($message) : $this->error($message);

        return $ok ? 0 : 1;
    }

    /** @return array [bool ok, string message] */
    public static function render($app)
    {
        $admin = \App\User::where('role', \App\User::ROLE_ADMIN)->orderBy('id')->first();
        if (!$admin) {
            return [true, 'No administrator: self-test skipped.'];
        }
        try {
            $app['auth']->guard('web')->setUser($admin);
            $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
            // Absolute address (APP_URL host and scheme): FreeScout answers 403 to any other host
            // (app/Http/Middleware/TrustHosts.php, "Check if current host matches APP_URL").
            $request = \Illuminate\Http\Request::create(route('refreshglobal.tickets'), 'GET');
            $response = $kernel->handle($request);
            $content = (string) $response->getContent();
            $kernel->terminate($request, $response);
        } catch (\Throwable $e) {
            return [false, 'Exception: '.$e->getMessage()];
        }
        if ($response->getStatusCode() !== 200) {
            return [false, 'HTTP '.$response->getStatusCode()];
        }
        if (strpos($content, 'data-rg-page="tickets"') === false) {
            preg_match('/\[(RG-[A-Z]+-\d+)\]/', $content, $m);

            return [false, 'The list is not displayed'.($m ? ' ('.$m[1].')' : '')];
        }

        return [true, 'OK: the "All mailboxes" page renders (HTTP 200).'];
    }
}
