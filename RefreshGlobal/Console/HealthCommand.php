<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;

/**
 * php artisan refreshglobal:health [--json] — health check of the installed module, run by the UpdateGuard module
 * (https://github.com/00MY00/UpdateGuard) in a new process right after an update or a restore, and at the end of its
 * observation period ("healthCommand" of module.json). Read-only, fast, no network call:
 *  - files of the module (module.json, ServiceProvider, routes, the page's stylesheet and scripts);
 *  - the compatibility checks of refreshglobal:check (classes, views, routes, hooks, assets, database), grouped;
 *  - the "All mailboxes" page rendered for an administrator (refreshglobal:selftest).
 * Exit code 0 = healthy (state ok, warning or degraded: the list works); 1 = blocking state, missing files or page not
 * rendered. With --json: {"status", "module", "version", "state", "checks": {...}, "failed": [...]}.
 */
class HealthCommand extends Command
{
    protected $signature = 'refreshglobal:health {--json : Print one JSON object}';

    protected $description = 'RefreshGlobal: health check (files, compatibility, "All mailboxes" page), used by UpdateGuard';

    /** Group of each check code (prefix) in the JSON answer. */
    const GROUPS = ['RG-CORE' => 'classes', 'RG-VIEW' => 'views', 'RG-ROUTE' => 'routes', 'RG-HOOK' => 'hooks',
        'RG-CSS' => 'assets', 'RG-JS' => 'assets', 'RG-DB' => 'database', 'RG-ENV' => 'environment', 'RG-REF' => 'environment',
        'RG-ACL' => 'environment'];

    /** Files the module can not work without. */
    const FILES = ['module.json', 'Providers/RefreshGlobalServiceProvider.php', 'Http/routes.php', 'Public/css/refreshglobal.css', 'Public/css/refreshglobal-layout.css',
        'Public/js/refreshglobal.js', 'Public/js/shell.js', 'Resources/views/tickets.blade.php'];

    public function handle()
    {
        $root = realpath(__DIR__.'/..');
        $manifest = json_decode((string) @file_get_contents($root.'/module.json'), true);
        $version = is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';
        $checks = [];
        $failed = [];

        // 1. files
        $missing = array_values(array_filter(self::FILES, function ($file) use ($root) {
            return !is_file($root.'/'.$file);
        }));
        $checks['files'] = $missing ? 'fail' : 'ok';
        if ($missing) {
            $failed[] = 'files: '.implode(', ', $missing);
        }

        // 2. compatibility (same checks as refreshglobal:check)
        $state = 'unknown';
        try {
            CompatibilityChecker::forget();
            $report = (new CompatibilityChecker())->run();
            $state = (string) $report['state'];
            foreach ($report['results'] as $r) {
                $group = self::GROUPS[implode('-', array_slice(explode('-', $r['code']), 0, 2))] ?? 'other';
                $current = $checks[$group] ?? 'ok';
                if ($r['status'] === 'failed') {
                    $level = $r['severity'] === 'blocking' ? 'fail' : 'warn';
                    $checks[$group] = ($current === 'fail' || $level === 'fail') ? 'fail' : 'warn';
                    $failed[] = $r['code'].' ('.$r['severity'].')'.($r['details'] !== '' ? ': '.$r['details'] : '');
                } else {
                    $checks[$group] = $current;
                }
            }
        } catch (\Throwable $e) {
            $checks['compatibility'] = 'fail';
            $failed[] = 'compatibility: '.$e->getMessage();
        }

        // 3. the page itself
        list($pageOk, $pageMessage) = SelfTestCommand::render($this->laravel);
        $checks['page'] = $pageOk ? 'ok' : 'fail';
        if (!$pageOk) {
            $failed[] = 'page: '.$pageMessage;
        }

        $healthy = !$missing && !in_array($state, ['blocking', 'unknown'], true) && $pageOk;
        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $healthy ? 'ok' : 'fail', 'module' => 'RefreshGlobal', 'version' => $version, 'state' => $state,
                'checks' => $checks, 'failed' => $failed,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('RefreshGlobal '.$version.', state '.strtoupper($state));
            foreach ($checks as $name => $result) {
                $this->line(sprintf('  %-12s %s', $name, strtoupper($result)));
            }
            foreach ($failed as $line) {
                $this->line('  - '.$line);
            }
            $healthy ? $this->info('Healthy.') : $this->error('Not healthy.');
        }

        return $healthy ? 0 : 1;
    }
}
