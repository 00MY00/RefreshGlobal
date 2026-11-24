<?php

namespace Modules\RefreshGlobal\Services\Perf;

use Modules\RefreshGlobal\Services\SchemaCache;
use Modules\RefreshGlobal\Services\Settings;

/**
 * Performance sensors (1.23.0): while an administrator asked for it (at most MAX_MINUTES, then it stops by itself), one
 * row per request in refreshglobal_perf: total time, time before the modules were ready, SQL requests (number, time,
 * repeated ones, per module) and, sent by the page itself, what the browser saw. Nothing is measured otherwise.
 *
 * No personal data: the name of the route (never its address or parameters), no user, no IP address; SQL texts without
 * their values. At most MAX_ROWS rows.
 *
 * Laravel: event Illuminate\Foundation\Http\Events\RequestHandled (Foundation/Http/Kernel.php:128), DB::listen();
 * LARAVEL_START (public/index.php:19).
 */
class Recorder
{
    const TABLE = 'refreshglobal_perf';
    const OPTION = 'refreshglobal.perf_until';
    const MAX_MINUTES = 240;
    const MAX_ROWS = 3000;
    /** SQL requests kept with the row when slower than this (ms) */
    const SLOW_SQL_MS = 100;

    /** @var array|null measures of the current request */
    protected static $now = null;

    /** Recording asked for, and not over. */
    public static function active()
    {
        try {
            return (int) Settings::option(self::OPTION, 0) > time() && SchemaCache::has(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function until()
    {
        return self::active() ? (int) Settings::option(self::OPTION, 0) : 0;
    }

    public static function start($minutes)
    {
        \App\Option::set(self::OPTION, time() + 60 * max(1, min(self::MAX_MINUTES, (int) $minutes)));
    }

    public static function stop()
    {
        \App\Option::set(self::OPTION, 0);
    }

    public static function clear()
    {
        \DB::table(self::TABLE)->delete();
    }

    /** Called when the module boots. */
    public static function register($app)
    {
        self::$now = null;
        if ($app->runningInConsole() && !$app->runningUnitTests()) {
            return;
        }
        $app->booted(function () use ($app) {
            if (!self::active()) {
                return;
            }
            self::begin();
            \DB::listen(function ($query) {
                self::sql($query->sql, (float) $query->time);
            });
            $app['events']->listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, function ($event) {
                self::finish($event->request, $event->response);
            });
            // the page tells what the browser saw (Public/js/shell.js)
            \Eventy::addAction('layout.head', function () {
                if (self::$now && auth()->check()) {
                    echo '<meta name="refreshglobal-perf" content="'.e(json_encode(['id' => self::$now['uid'],
                        'url' => route('refreshglobal.performance.browser')])).'">'."\n";
                }
            });
        });
    }

    public static function begin()
    {
        $start = defined('LARAVEL_START') && PHP_SAPI !== 'cli' ? LARAVEL_START : microtime(true);
        self::$now = ['uid' => bin2hex(random_bytes(8)), 'start' => $start, 'boot' => (microtime(true) - $start) * 1000,
            'sql_n' => 0, 'sql_ms' => 0.0, 'modules' => [], 'seen' => [], 'slow' => []];
    }

    /**
     * One SQL request: counted for the module whose code asked for it, else for FreeScout. The middlewares of the
     * modules surround every page: they do not count. A page of a module counts with its layout.
     */
    public static function sql($sql, $ms)
    {
        if (!self::$now || strpos($sql, self::TABLE) !== false) {
            return;
        }
        $module = 'FreeScout';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            if (isset($frame['file']) && preg_match('#[/\\\\]Modules[/\\\\]([A-Za-z0-9_]+)[/\\\\]#', $frame['file'], $m)
                && !preg_match('#[/\\\\](Services[/\\\\]Perf|Http[/\\\\]Middleware)[/\\\\]#', $frame['file'])) {
                $module = $m[1];
                break;
            }
        }
        self::$now['sql_n']++;
        self::$now['sql_ms'] += $ms;
        $row = self::$now['modules'][$module] ?? [0, 0.0];
        self::$now['modules'][$module] = [$row[0] + 1, round($row[1] + $ms, 2)];
        $hash = md5($sql);
        self::$now['seen'][$hash] = (self::$now['seen'][$hash] ?? 0) + 1;
        if ($ms >= self::SLOW_SQL_MS && count(self::$now['slow']) < 5) {
            self::$now['slow'][] = [$module, (int) round($ms), self::anonymous($sql)];
        }
    }

    /** SQL text without any value. */
    public static function anonymous($sql)
    {
        $sql = preg_replace(["/'(?:[^'\\\\]|\\\\.|'')*'/s", '/"(?:[^"\\\\]|\\\\.)*"(?=\s|\)|,|$)/s', '/\b\d+\b/', '/\s+/'], ['?', '?', '?', ' '], (string) $sql);
        $sql = preg_replace('/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?)', $sql);

        return mb_substr(trim($sql), 0, 300);
    }

    public static function finish($request, $response)
    {
        $now = self::$now;
        if (!$now) {
            return;
        }
        self::$now = null;
        try {
            $total = (microtime(true) - $now['start']) * 1000;
            if (isset($response->headers)) {
                $response->headers->set('Server-Timing', sprintf('total;dur=%.1f, boot;dur=%.1f, sql;dur=%.1f;desc="%d requests"',
                    $total, $now['boot'], $now['sql_ms'], $now['sql_n']));
            }
            $route = $request->route();
            $name = is_object($route) ? ((string) $route->getName() ?: (string) $route->uri()) : 'other';
            if (strpos($name, 'refreshglobal.performance') === 0) {
                return;
            }
            // FreeScout's ajax addresses do many things: the action is part of the name
            $action = $request->isMethod('POST') ? $request->input('action') : null;
            if (is_string($action) && preg_match('/^[a-z0-9_]{1,40}$/', $action)) {
                $name .= ':'.$action;
            }
            $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;
            $id = \DB::table(self::TABLE)->insertGetId([
                'uid'     => $now['uid'],
                'route'   => mb_substr($name, 0, 120),
                'method'  => mb_substr($request->method(), 0, 10),
                'status'  => method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 0,
                'total'   => (int) round($total),
                'boot'    => (int) round($now['boot']),
                'sql_n'   => $now['sql_n'],
                'sql_ms'  => (int) round($now['sql_ms']),
                'repeats' => $now['sql_n'] - count($now['seen']),
                'memory'  => (int) round(memory_get_peak_usage(true) / 1048576),
                'load_avg' => is_array($load) ? round((float) $load[0], 2) : null,
                'details' => json_encode(['modules' => $now['modules'], 'slow' => $now['slow']]),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($id % 100 === 0) {
                \DB::table(self::TABLE)->where('id', '<=', $id - self::MAX_ROWS)->delete();
            }
        } catch (\Throwable $e) {
            // a sensor never breaks a page
        }
    }

    /** What the browser saw of a page (ms since the click): first byte, page ready, page complete. */
    public static function browser($uid, $ttfb, $ready, $complete)
    {
        if (!is_string($uid) || !preg_match('/^[0-9a-f]{16}$/', $uid)) {
            return false;
        }
        $ms = function ($v) {
            return is_numeric($v) && $v >= 0 && $v <= 600000 ? (int) round($v) : null;
        };

        return (bool) \DB::table(self::TABLE)->where('uid', $uid)->whereNull('b_ttfb')->where('created_at', '>=', date('Y-m-d H:i:s', time() - 900))
            ->update(['b_ttfb' => $ms($ttfb), 'b_ready' => $ms($ready), 'b_load' => $ms($complete)]);
    }

    /**
     * Figures per page and per module over the recorded rows.
     *
     * @return array ['n', 'from', 'to', 'pages' => [...], 'modules' => [...], 'slow' => [...], 'share' => [...]|null]
     */
    public static function summary()
    {
        $rows = SchemaCache::has(self::TABLE) ? \DB::table(self::TABLE)->orderBy('id', 'desc')->limit(self::MAX_ROWS)->get()->all() : [];
        $groups = [];
        $modules = [];
        $slow = [];
        foreach ($rows as $r) {
            $groups[$r->route][] = $r;
            $d = json_decode((string) $r->details, true) ?: [];
            foreach ((array) ($d['modules'] ?? []) as $name => $m) {
                $modules[$name]['sql_n'] = ($modules[$name]['sql_n'] ?? 0) + (int) $m[0];
                $modules[$name]['sql_ms'] = ($modules[$name]['sql_ms'] ?? 0) + (float) $m[1];
            }
            foreach ((array) ($d['slow'] ?? []) as $q) {
                $key = $q[0].$q[2];
                if (!isset($slow[$key])) {
                    $slow[$key] = ['module' => $q[0], 'sql' => $q[2], 'n' => 0, 'max' => 0];
                }
                $slow[$key]['n']++;
                $slow[$key]['max'] = max($slow[$key]['max'], (int) $q[1]);
            }
        }
        $pages = [];
        foreach ($groups as $route => $list) {
            $col = function ($name) use ($list) {
                return array_values(array_filter(array_map(function ($r) use ($name) {
                    return $r->$name;
                }, $list), function ($v) {
                    return $v !== null;
                }));
            };
            $pages[] = ['route' => $route, 'n' => count($list), 'median' => self::percentile($col('total'), 50), 'p95' => self::percentile($col('total'), 95),
                'boot' => self::percentile($col('boot'), 50), 'sql_n' => self::percentile($col('sql_n'), 50), 'sql_ms' => self::percentile($col('sql_ms'), 50),
                'repeats' => self::percentile($col('repeats'), 50), 'b_ttfb' => self::percentile($col('b_ttfb'), 50), 'b_load' => self::percentile($col('b_load'), 50)];
        }
        usort($pages, function ($a, $b) {
            return $b['median'] * $b['n'] - $a['median'] * $a['n'];
        });
        foreach ($modules as $name => $m) {
            $modules[$name] = ['module' => $name, 'sql_n' => round($m['sql_n'] / max(1, count($rows)), 1), 'sql_ms' => round($m['sql_ms'] / max(1, count($rows)), 1)];
        }
        usort($modules, function ($a, $b) {
            return $b['sql_ms'] > $a['sql_ms'] ? 1 : -1;
        });
        usort($slow, function ($a, $b) {
            return $b['max'] - $a['max'];
        });

        return ['n' => count($rows), 'from' => $rows ? end($rows)->created_at : null, 'to' => $rows ? $rows[0]->created_at : null,
            'pages' => array_slice($pages, 0, 25), 'modules' => $modules, 'slow' => array_slice($slow, 0, 10), 'share' => self::share($rows)];
    }

    /**
     * Where the time of the recorded pages goes, in ms per page (pages whose browser answered only): SQL, the rest of
     * PHP, between the browser and PHP (network, web server, wait for a free PHP process), then the browser itself.
     */
    protected static function share(array $rows)
    {
        $rows = array_values(array_filter($rows, function ($r) {
            return $r->b_ttfb !== null && $r->b_load !== null;
        }));
        if (count($rows) < 3) {
            return null;
        }
        $avg = function ($f) use ($rows) {
            return (int) round(array_sum(array_map($f, $rows)) / count($rows));
        };

        return [
            'sql'     => $avg(function ($r) { return min($r->sql_ms, $r->total); }),
            'php'     => $avg(function ($r) { return max(0, $r->total - $r->sql_ms); }),
            'wait'    => $avg(function ($r) { return max(0, $r->b_ttfb - $r->total); }),
            'browser' => $avg(function ($r) { return max(0, $r->b_load - $r->b_ttfb); }),
            'n'       => count($rows),
        ];
    }

    public static function percentile(array $values, $p)
    {
        if (!$values) {
            return null;
        }
        sort($values);

        return $values[(int) min(count($values) - 1, max(0, ceil($p / 100 * count($values)) - 1))];
    }
}
