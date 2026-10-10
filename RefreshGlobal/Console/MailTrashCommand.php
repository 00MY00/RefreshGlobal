<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\MailServerTrash;

/**
 * php artisan refreshglobal:mail-trash
 * Moves to the mail server's trash folder the e-mails of the tickets deleted for good (queue filled at the deletion,
 * Services/MailServerTrash.php). Run every minute by FreeScout's scheduler; does nothing when the queue is empty.
 * Exit code 0 (also when some e-mails could not be moved: they are tried again), 1 on error.
 */
class MailTrashCommand extends Command
{
    protected $signature = 'refreshglobal:mail-trash';

    protected $description = 'RefreshGlobal: move the e-mails of the tickets deleted for good to the mail server\'s trash';

    public function handle()
    {
        if (!MailServerTrash::tableExists()) {
            return 0;
        }
        if (!\DB::table(MailServerTrash::TABLE)->where('status', 'pending')->exists()) {
            return 0; // nothing to do: no IMAP connection
        }
        try {
            $done = MailServerTrash::process(function ($line) {
                $this->line($line);
            });
        } catch (\Throwable $e) {
            $this->error('Cannot move the e-mails: '.$e->getMessage());
            \Log::error('[RefreshGlobal] [mail-trash] '.$e->getMessage());

            return 1;
        }
        $line = 'RefreshGlobal: '.$done['moved'].' e-mail(s) moved to the mail server\'s trash, '.$done['not_found'].' not found, '
            .$done['failed'].' error(s), '.$done['skipped'].' skipped.';
        $this->info($line);
        \Log::info('[RefreshGlobal] [mail-trash] '.$line);

        return 0;
    }
}
