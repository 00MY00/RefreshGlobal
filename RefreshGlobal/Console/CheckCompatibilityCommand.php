<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\Compatibility\Messages;

/**
 * php artisan refreshglobal:check [--json]
 *
 * Exit code: 0 = ok or warning, 1 = degraded, 2 = blocking.
 */
class CheckCompatibilityCommand extends Command
{
    protected $signature = 'refreshglobal:check {--json : Print the report as JSON} {--locale= : Language of the messages (en, fr, de…)}';

    protected $description = 'RefreshGlobal: check compatibility with the installed FreeScout and Refresh versions';

    public function handle()
    {
        if ($this->option('locale')) {
            app()->setLocale((string) $this->option('locale'));
        }
        CompatibilityChecker::forget();
        $report = (new CompatibilityChecker())->run();
        CompatibilityChecker::log($report);
        $code = ['ok' => 0, 'warning' => 0, 'degraded' => 1, 'blocking' => 2][$report['state']];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $code;
        }

        $v = $report['versions'];
        $this->line('RefreshGlobal '.$v['module'].' — FreeScout '.$v['freescout'].' — Refresh '.($v['refresh'] ?: '-')
            .($v['refresh'] && !$v['refresh_active'] ? ' (inactive)' : '').' — PHP '.$v['php']);
        $this->line('');

        $rows = [];
        foreach ($report['results'] as $r) {
            $rows[] = [$r['code'], strtoupper($r['status']), $r['severity'], Messages::check($r)];
        }
        $this->table(['Code', __('refreshglobal::messages.diag_status'), __('refreshglobal::messages.diag_severity'), __('refreshglobal::messages.diag_check')], $rows);

        foreach (Messages::failed($report) as $r) {
            $this->line('');
            $method = $r['severity'] === 'blocking' ? 'error' : ($r['severity'] === 'degraded' ? 'warn' : 'comment');
            foreach (explode("\n", Messages::block($r)) as $i => $line) {
                $i === 0 ? $this->{$method}($line) : $this->line('  '.$line);
            }
        }

        $this->line('');
        $this->line(__('refreshglobal::messages.diag_state').' '.strtoupper($report['state']));

        return $code;
    }
}
