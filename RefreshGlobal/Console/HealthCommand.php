<?php

namespace Modules\RefreshGlobal\Console;

use Illuminate\Console\Command;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\Interop\Manifest;
use Modules\RefreshGlobal\Services\Interop\PeerStatus;

/**
 * php artisan refreshglobal:health [--json] — health check of the installed module, run by UpdateGuard
 * (https://github.com/00MY00/UpdateGuard) in a new process right after an update or a restore, and at the end of its
 * observation period ("healthCommand" of module.json, "updateguard.health" of interop.json). Read-only, fast, no
 * network call, no e-mail, no change to tickets:
 *  - files of the module (module.json, interop.json, ServiceProvider, routes, stylesheets, scripts);
 *  - the compatibility checks of refreshglobal:check (classes, views, routes, hooks, assets, database), grouped;
 *  - the module's interop.json valid (FreeScout Interop contract, docs/INTEROPERABILITY.md);
 *  - the "All mailboxes" page rendered for an administrator (refreshglobal:selftest).
 *
 * Exit codes (interop.json updateguard.health.exit_codes): 0 healthy (state ok, warning or degraded: the list works),
 * 1 unhealthy (blocking state, missing files, invalid manifest, page not rendered), 2 the check itself could not run.
 * JSON (schema health 1.0, docs/interop/schemas/health-1.0.schema.json):
 *   {"schema", "module", "version", "status": healthy|unhealthy|error, "state", "checks": {name: pass|warn|fail},
 *    "interop": {"protocol", "refresh": {mode, compatibility}}, "failed": [...]}
 */
class HealthCommand extends Command
{
    protected $signature = 'refreshglobal:health {--json : Print one JSON object}';

    protected $description = 'RefreshGlobal: health check (files, compatibility, interop manifest, "All mailboxes" page), used by UpdateGuard';

    const EXIT_HEALTHY = 0;
    const EXIT_UNHEALTHY = 1;
    const EXIT_ERROR = 2;

    /** Group of each check code (prefix) in the JSON answer. */
    const GROUPS = ['RG-CORE' => 'classes', 'RG-VIEW' => 'views', 'RG-ROUTE' => 'routes', 'RG-HOOK' => 'hooks',
        'RG-CSS' => 'assets', 'RG-JS' => 'assets', 'RG-DB' => 'database', 'RG-ENV' => 'environment', 'RG-REF' => 'environment',
        'RG-ACL' => 'environment'];

    /** Files the module can not work without. */
    const FILES = ['module.json', 'interop.json', 'Providers/RefreshGlobalServiceProvider.php', 'Http/routes.php',
        'Public/css/refreshglobal.css', 'Public/css/refreshglobal-layout.css', 'Public/js/refreshglobal.js', 'Public/js/shell.js',
        'Public/js/fs-interop.js', 'Public/js/interop-adapter.js', 'Resources/views/tickets.blade.php'];

    /** @var array name => pass|warn|fail */
    protected $checks = [];

    /** @var string[] */
    protected $failed = [];

    public function handle()
    {
        $root = realpath(__DIR__.'/..');
        try {
            $this->checkFiles($root);
            $state = $this->checkCompatibility();
            $this->checkInteropManifest();
            $this->checkPage();
            $healthy = !in_array('fail', $this->checks, true) && !in_array($state, ['blocking', 'unknown'], true);

            return $this->finish($healthy ? 'healthy' : 'unhealthy', $state, $healthy ? self::EXIT_HEALTHY : self::EXIT_UNHEALTHY);
        } catch (\Throwable $e) {
            // the check itself failed (not the module): exit code 2, the caller's policy decides
            $this->failed[] = 'error: '.get_class($e).': '.$e->getMessage();

            return $this->finish('error', 'unknown', self::EXIT_ERROR);
        }
    }

    /** 1. files of the module */
    protected function checkFiles($root)
    {
        $missing = array_values(array_filter(self::FILES, function ($file) use ($root) {
            return !is_file($root.'/'.$file);
        }));
        $this->checks['files'] = $missing ? 'fail' : 'pass';
        if ($missing) {
            $this->failed[] = 'files: '.implode(', ', $missing);
        }
    }

    /** 2. the checks of refreshglobal:check, grouped by family; returns the overall state */
    protected function checkCompatibility()
    {
        CompatibilityChecker::forget();
        $report = (new CompatibilityChecker())->run();
        foreach ($report['results'] as $r) {
            $group = self::GROUPS[implode('-', array_slice(explode('-', $r['code']), 0, 2))] ?? 'other';
            $current = $this->checks[$group] ?? 'pass';
            if ($r['status'] === 'failed') {
                $level = $r['severity'] === 'blocking' ? 'fail' : 'warn';
                $this->checks[$group] = ($current === 'fail' || $level === 'fail') ? 'fail' : 'warn';
                $this->failed[] = $r['code'].' ('.$r['severity'].')'.($r['details'] !== '' ? ': '.$r['details'] : '');
            } else {
                $this->checks[$group] = $current;
            }
        }

        return (string) $report['state'];
    }

    /** 3. interop.json present and valid (a broken contract would mislead UpdateGuard and the peer modules) */
    protected function checkInteropManifest()
    {
        $manifest = Manifest::own();
        $errors = $manifest ? $manifest->validate() : ['interop.json missing or not valid JSON'];
        $this->checks['interop_manifest'] = $errors ? 'fail' : 'pass';
        foreach ($errors as $error) {
            $this->failed[] = 'interop_manifest: '.$error;
        }
    }

    /** 4. the page itself */
    protected function checkPage()
    {
        list($ok, $message) = SelfTestCommand::render($this->laravel);
        $this->checks['page'] = $ok ? 'pass' : 'fail';
        if (!$ok) {
            $this->failed[] = 'page: '.$message;
        }
    }

    /** Prints the result (text or JSON) and returns the exit code. */
    protected function finish($status, $state, $code)
    {
        $version = (string) (json_decode((string) @file_get_contents(realpath(__DIR__.'/..').'/module.json'), true)['version'] ?? '');
        $interop = $this->interopSummary();
        if ($this->option('json')) {
            $this->line(json_encode([
                'schema'  => '1.0',
                'module'  => 'RefreshGlobal',
                'version' => $version,
                'status'  => $status,
                'state'   => $state,
                'checks'  => $this->checks,
                'interop' => $interop,
                'failed'  => $this->failed,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('RefreshGlobal '.$version.', state '.strtoupper($state));
            foreach ($this->checks as $name => $result) {
                $this->line(sprintf('  %-17s %s', $name, strtoupper($result)));
            }
            if ($interop['refresh']) {
                $this->line(sprintf('  %-17s %s (%s)', 'refresh interop', $interop['refresh']['mode'], $interop['refresh']['compatibility']));
            }
            foreach ($this->failed as $line) {
                $this->line('  - '.$line);
            }
            $status === 'healthy' ? $this->info('Healthy.') : $this->error($status === 'error' ? 'Health check error.' : 'Not healthy.');
        }

        return $code;
    }

    /** Protocol and cooperation mode with Refresh (information only: the peer is optional). */
    protected function interopSummary()
    {
        $manifest = Manifest::own();
        try {
            $peer = PeerStatus::fromInstallation('refresh');
        } catch (\Throwable $e) {
            $peer = null;
        }

        return [
            'protocol' => $manifest ? $manifest->protocol() : '',
            'refresh'  => $peer ? ['mode' => $peer['mode'], 'compatibility' => $peer['compatibility'], 'version' => $peer['version']] : null,
        ];
    }
}
