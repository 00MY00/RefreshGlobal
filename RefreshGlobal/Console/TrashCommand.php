<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Services\Trash;

/**
 * php artisan refreshglobal:trash --older-than=30   delete for good the tickets in the trash for more than 30 days
 * php artisan refreshglobal:trash --scheduled       daily run from FreeScout's scheduler, with the days of the settings
 *                                                   (Manage › Settings › RefreshGlobal; 0 = never: nothing is done)
 * Every mailbox. Exit code 0 (also when nothing is deleted), 1 on error.
 */
class TrashCommand extends Command
{
    protected $signature = 'refreshglobal:trash
        {--older-than= : Days a ticket must have spent in the trash}
        {--scheduled : Daily run from the scheduler (days from the settings)}';

    protected $description = 'RefreshGlobal: empty the trash (tickets in the trash for more than N days are deleted for good)';

    public function handle()
    {
        $days = $this->option('scheduled') ? Settings::trashAutoDays() : (int) $this->option('older-than');
        if ($days < 1) {
            if (!$this->option('scheduled')) {
                $this->error('Give --older-than=N (days, at least 1).');

                return 1;
            }

            return 0; // automatic emptying off
        }
        try {
            $n = Trash::purgeOlderThan($days);
        } catch (\Throwable $e) {
            $this->error('Cannot empty the trash: '.$e->getMessage());
            \Log::error('[RefreshGlobal] [trash] '.$e->getMessage());

            return 1;
        }
        $this->info('RefreshGlobal: '.$n.' ticket(s) in the trash for more than '.$days.' day(s) deleted for good.');
        if ($n) {
            \Log::info('[RefreshGlobal] [trash] '.$n.' ticket(s) in the trash for more than '.$days.' day(s) deleted for good.');
        }

        return 0;
    }
}
