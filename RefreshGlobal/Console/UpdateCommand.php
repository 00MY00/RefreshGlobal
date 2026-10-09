<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\Update\Updater;

/**
 * php artisan refreshglobal:update            update now if a new version exists (automatic rollback if not OK)
 * php artisan refreshglobal:update --check    only tell whether a new version exists
 * php artisan refreshglobal:update --enable   turn the daily automatic update on (--disable: off)
 * php artisan refreshglobal:update --scheduled  used by FreeScout's scheduler: checks every day, installs only if enabled
 *
 * Run it as the web server user (sudo -u www-data php artisan …), like every FreeScout command.
 * Exit code: 0 up to date / updated / checked, 3 rolled back, 1 error.
 */
class UpdateCommand extends Command
{
    protected $signature = 'refreshglobal:update
        {--check : Only check whether a new version is available}
        {--enable : Turn the daily automatic update on}
        {--disable : Turn the daily automatic update off}
        {--scheduled : Daily run from the scheduler (installs only when enabled)}
        {--force : Install even the same version, or a version rolled back before}';

    protected $description = 'RefreshGlobal: update the module (automatic rollback if the new version is not OK)';

    public function handle()
    {
        if ($this->option('enable') || $this->option('disable')) {
            Updater::setEnabled((bool) $this->option('enable'));
            $this->info('RefreshGlobal: automatic update '.($this->option('enable') ? 'ON' : 'OFF').'.');

            return 0;
        }

        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && !$this->option('check')) {
            $this->error('Run this command as the web server user, e.g.: sudo -u www-data php artisan refreshglobal:update');

            return 1;
        }

        $updater = new Updater(function ($line) {
            $this->line($line);
        });

        if ($this->option('check') || ($this->option('scheduled') && !Updater::enabled())) {
            try {
                $info = $updater->check();
            } catch (\Throwable $e) {
                $this->error('Cannot check for updates: '.$e->getMessage());

                return 1;
            }
            $this->line('Installed: '.$info['current'].' — latest: '.$info['latest']
                .($info['available'] ? ' (update available'.($info['compatible'] ? '' : ', requires FreeScout '.$info['required_app']).')' : ' (up to date)'));
            $this->line('Automatic update: '.(Updater::enabled() ? 'ON' : 'OFF'));

            return 0;
        }

        $result = $updater->update((bool) $this->option('force'));
        switch ($result['result']) {
            case 'rolled_back':
                $this->error('Update to '.$result['to'].' rolled back: '.$result['reason']);

                return 3;
            case 'failed':
                $this->error('Update failed (nothing changed): '.$result['reason']);

                return 1;
            default:
                $this->info('Result: '.$result['result'].($result['reason'] ? ' — '.$result['reason'] : ''));

                return 0;
        }
    }
}
