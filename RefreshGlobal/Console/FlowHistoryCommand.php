<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\FlowChanges;
use Modules\RefreshGlobal\Services\WorkflowPacksLink;

/**
 * php artisan refreshglobal:flow-history [--days=30] [--mailbox=ID …] [--requested]
 * Flow statistics: records the changes the flows of Workflow Packs 3.20+ made before RefreshGlobal listened to them
 * (Services/FlowChanges.php backfill(), by batches of 1000 tickets). Safe to run again: nothing is counted twice.
 * --requested: only when an administrator asked for it on the statistics page (scheduled every minute).
 */
class FlowHistoryCommand extends Command
{
    protected $signature = 'refreshglobal:flow-history
        {--days=30 : tickets updated during the last N days (1 to 3650)}
        {--mailbox=* : mailbox id (several allowed); all mailboxes by default}
        {--requested : only when it was asked from the statistics page}';

    protected $description = 'RefreshGlobal: record the changes made by the flows of Workflow Packs before the statistics listened to them';

    public function handle()
    {
        if (!WorkflowPacksLink::available()) {
            if (!$this->option('requested')) {
                $this->warn('Workflow Packs 3.20+ with its engine on is needed: nothing to do.');
            }

            return 0;
        }
        if (!FlowChanges::tableExists()) {
            $this->error('Table '.FlowChanges::TABLE.' missing: run php artisan migrate.');

            return 1;
        }
        $progress = function ($done, $total, $recorded) {
            $this->line(sprintf('%d / %d tickets, %d change(s) recorded', $done, $total, $recorded));
        };
        if ($this->option('requested')) {
            $status = FlowChanges::runRequested($progress);
            if ($status) {
                $this->info('RefreshGlobal: '.$status['state'].', '.(int) $status['recorded'].' change(s) recorded.');
            }

            return $status && $status['state'] === 'failed' ? 1 : 0;
        }
        $mailboxes = array_values(array_filter(array_map('intval', (array) $this->option('mailbox'))));
        if (!$mailboxes) {
            $mailboxes = \App\Mailbox::pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();
        }
        $r = FlowChanges::backfill($mailboxes, $this->option('days'), $progress);
        $this->info('RefreshGlobal: '.$r['tickets'].' ticket(s) read, '.$r['changes'].' change(s) seen, '.$r['recorded'].' recorded.');

        return 0;
    }
}
