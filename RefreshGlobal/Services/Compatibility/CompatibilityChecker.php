<?php

namespace Modules\RefreshGlobal\Services\Compatibility;

use Illuminate\Support\Arr;

/**
 * Runs every compatibility check and computes the state of the module:
 *   ok        every check passed (warnings excepted)
 *   warning   only "warning" checks failed (version outside the tested range)
 *   degraded  the page works with FreeScout's standard look, or without a secondary feature
 *   blocking  the ticket list must not be shown
 *
 * Used by the module's pages (result cached a few minutes, rebuilt as soon as FreeScout's, Refresh's or the module's
 * version changes), by `php artisan refreshglobal:check` and by the /refresh-global/diagnostic page.
 */
class CompatibilityChecker
{
    const CACHE_PREFIX = 'refreshglobal.compat.';

    /** @var \App\User|null */
    protected $user;

    /** @var array */
    protected $integration;

    /** @var \Nwidart\Modules\Module|null|false */
    protected $refresh = false;

    public function __construct($user = null, array $integration = null)
    {
        $this->user = $user;
        $this->integration = $integration !== null ? $integration : (array) config('refreshglobal_integration', []);
    }

    /** Check classes, in report order. */
    public static function checkClasses()
    {
        return [
            Checks\FreeScoutVersionCheck::class,
            Checks\RefreshPresentCheck::class,
            Checks\RefreshVersionCheck::class,
            Checks\RefreshCssCheck::class,
            Checks\RefreshJsCheck::class,
            Checks\ViewCheck::class,
            Checks\HookCheck::class,
            Checks\CoreCheck::class,
            Checks\RouteCheck::class,
            Checks\DatabaseCheck::class,
            Checks\AclCheck::class,
        ];
    }

    public function user()
    {
        return $this->user;
    }

    public function integration($key)
    {
        return Arr::get($this->integration, $key);
    }

    public function freescoutVersion()
    {
        return (string) config('app.version');
    }

    public static function moduleVersion()
    {
        $file = __DIR__.'/../../module.json';
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($json) && !empty($json['version']) ? (string) $json['version'] : '';
    }

    /** Refresh module (nwidart Module object) or null. */
    public function refreshModule()
    {
        if ($this->refresh === false) {
            $this->refresh = null;
            try {
                $this->refresh = \Module::findByAlias((string) $this->integration('refresh.alias')) ?: null;
            } catch (\Exception $e) {
                $this->refresh = null;
            }
        }

        return $this->refresh;
    }

    public function refreshPath()
    {
        $module = $this->refreshModule();
        try {
            return $module ? rtrim($module->getPath(), '/\\') : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function refreshActive()
    {
        try {
            return (bool) \App\Module::isActive((string) $this->integration('refresh.alias'));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function refreshVersion()
    {
        $module = $this->refreshModule();
        try {
            return $module ? (string) $module->get('version') : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /** Refresh present AND active: the checks of its files, views and hooks make sense. */
    public function refreshUsable()
    {
        return $this->refreshPath() !== '' && $this->refreshActive();
    }

    /** Shared by RG-CSS-01 / RG-JS-01: all the files exist in Modules/Refresh. */
    public function filesResult($code, $family, $files)
    {
        $range = $this->integration('versions.refresh');
        $shown = [];
        foreach ((array) $files as $file) {
            $shown[] = 'Modules/Refresh/'.$file;
        }
        $expected = implode(', ', $shown).' (Refresh '.preg_replace('/\.\d+$/', '.x', $range['min']).')';
        $result = [
            'code' => $code, 'family' => $family, 'severity' => Check::DEGRADED, 'expected' => $expected,
            'params' => ['item' => $expected],
        ];
        if (!$this->refreshUsable()) {
            return $result + ['status' => 'skipped', 'details' => ''];
        }
        $missing = [];
        foreach ((array) $files as $file) {
            if (!is_file($this->refreshPath().'/'.$file)) {
                $missing[] = 'Modules/Refresh/'.$file;
            }
        }

        return $result + ['status' => $missing ? 'failed' : 'ok', 'details' => $missing ? 'missing: '.implode(', ', $missing) : ''];
    }

    /** Runs every check now. */
    public function run()
    {
        $results = [];
        foreach (self::checkClasses() as $class) {
            try {
                $check = new $class($this);
                foreach ($check->run() as $result) {
                    $results[] = $result;
                }
            } catch (\Exception $e) {
                // A check that crashes counts as a failed blocking check: the list is not shown blindly.
                $results[] = [
                    'code' => 'RG-ERR-02', 'family' => 'check_error', 'severity' => Check::BLOCKING, 'status' => 'failed',
                    'expected' => $class, 'details' => $e->getMessage(), 'params' => ['item' => class_basename($class)],
                ];
            }
        }

        return $this->buildReport($results);
    }

    public function buildReport(array $results)
    {
        $state = 'ok';
        $rank = ['ok' => 0, 'warning' => 1, 'degraded' => 2, 'blocking' => 3];
        $skin = 'refresh';
        foreach ($results as $r) {
            if ($r['status'] !== 'failed') {
                continue;
            }
            if ($rank[$r['severity']] > $rank[$state]) {
                $state = $r['severity'];
            }
            if (in_array($r['code'], ['RG-REF-01', 'RG-CSS-01', 'RG-JS-01', 'RG-VIEW-05'], true)) {
                $skin = 'native';
            }
        }

        return [
            'state'        => $state,
            'skin'         => $skin,
            'results'      => $results,
            'generated_at' => date('Y-m-d H:i:s'),
            'versions'     => [
                'freescout'     => $this->freescoutVersion(),
                'refresh'       => $this->refreshVersion(),
                'refresh_active' => $this->refreshActive(),
                'module'        => self::moduleVersion(),
                'php'           => PHP_VERSION,
            ],
        ];
    }

    /**
     * Report from the cache (kept compat_cache_minutes, key includes the versions so an update rebuilds it).
     * Every failed check is written to the Laravel log when the report is (re)built.
     */
    public static function cached()
    {
        $checker = new self();
        $key = self::cacheKey($checker);
        $minutes = max(1, (int) config('refreshglobal.compat_cache_minutes', 5));
        try {
            $report = \Cache::get($key);
            if (is_array($report) && isset($report['state'], $report['results'])) {
                return $report;
            }
        } catch (\Exception $e) {
            // cache unavailable: run every time
        }
        $report = $checker->run();
        self::log($report);
        try {
            \Cache::put($key, $report, $minutes);
            \Cache::forever(self::CACHE_PREFIX.'last_key', $key);
        } catch (\Exception $e) {
            // not fatal
        }

        return $report;
    }

    public static function cacheKey(CompatibilityChecker $checker = null)
    {
        $checker = $checker ?: new self();

        return self::CACHE_PREFIX.md5(implode('|', [
            $checker->freescoutVersion(), $checker->refreshVersion(), (int) $checker->refreshActive(), self::moduleVersion(),
        ]));
    }

    public static function forget()
    {
        try {
            \Cache::forget(self::cacheKey());
            $last = \Cache::get(self::CACHE_PREFIX.'last_key');
            if ($last) {
                \Cache::forget($last);
            }
        } catch (\Exception $e) {
            // not fatal
        }
    }

    public static function log(array $report)
    {
        foreach ($report['results'] as $r) {
            if ($r['status'] === 'failed') {
                try {
                    \Log::warning('[RefreshGlobal] ['.$r['code'].'] '.Messages::title($r, 'en')
                        .' Expected: '.$r['expected'].($r['details'] !== '' ? ' — '.$r['details'] : ''));
                } catch (\Exception $e) {
                    // logging must never break the page
                }
            }
        }
    }
}
