<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\MailTags;

/**
 * php artisan refreshglobal:mail-tags [--requested]
 * Tags of the e-mails already in FreeScout (Services/MailTags.php recalc()): every folder of every IMAP mailbox is
 * read and each e-mail still on the mail server gets its origin tags. --requested: only when an administrator asked
 * for it on Manage › Settings › RefreshGlobal (scheduled every minute; nothing to do otherwise).
 */
class MailTagsCommand extends Command
{
    protected $signature = 'refreshglobal:mail-tags {--requested : only when it was asked from the settings page}';

    protected $description = 'RefreshGlobal: add the origin tags (folder of the mail server, spam) to the e-mails already in FreeScout';

    public function handle()
    {
        if (!MailTags::tableExists()) {
            $this->error('Table '.MailTags::TABLE.' missing: run php artisan migrate.');

            return 1;
        }
        if ($this->option('requested') && MailTags::recalcStatus()['state'] !== 'requested') {
            return 0;
        }
        $r = MailTags::recalc(function ($line) {
            $this->line($line);
        });
        $this->info('RefreshGlobal: '.$r['seen'].' e-mail(s) read on the mail server, '.$r['tagged'].' tag(s) added'
            .($r['errors'] ? ', '.count($r['errors']).' error(s)' : '').'.');

        return $r['errors'] ? 1 : 0;
    }
}
