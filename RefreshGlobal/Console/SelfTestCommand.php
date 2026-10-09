<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;

/**
 * php artisan refreshglobal:selftest — renders the "All mailboxes" page for an active administrator, through
 * FreeScout's HTTP kernel (routes, middlewares, views), without a browser. Used after an automatic update.
 * Exit code 0: HTTP 200 with the list (or its empty state); 1 otherwise.
 */
class SelfTestCommand extends Command
{
    protected $signature = 'refreshglobal:selftest';

    protected $description = 'RefreshGlobal: render the "All mailboxes" page for an administrator and check it';

    public function handle()
    {
        $admin = \App\User::where('role', \App\User::ROLE_ADMIN)->orderBy('id')->first();
        if (!$admin) {
            $this->warn('No administrator: self-test skipped.');

            return 0;
        }
        try {
            $this->laravel['auth']->guard('web')->setUser($admin);
            $kernel = $this->laravel->make(\Illuminate\Contracts\Http\Kernel::class);
            $request = \Illuminate\Http\Request::create(route('refreshglobal.tickets', [], false), 'GET');
            $response = $kernel->handle($request);
            $content = (string) $response->getContent();
            $kernel->terminate($request, $response);
        } catch (\Throwable $e) {
            $this->error('Exception: '.$e->getMessage());

            return 1;
        }
        if ($response->getStatusCode() !== 200) {
            $this->error('HTTP '.$response->getStatusCode());

            return 1;
        }
        if (strpos($content, 'data-rg-page="tickets"') === false) {
            preg_match('/\[(RG-[A-Z]+-\d+)\]/', $content, $m);
            $this->error('The list is not displayed'.($m ? ' ('.$m[1].')' : ''));

            return 1;
        }
        $this->info('OK: the "All mailboxes" page renders (HTTP 200).');

        return 0;
    }
}
