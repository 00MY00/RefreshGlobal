<?php

namespace Modules\RefreshGlobal\Services\Perf;

use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\Dashboard\Statistics;
use Modules\RefreshGlobal\Services\FlowChanges;
use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\SchemaCache;

/**
 * State of the server as seen from PHP (1.23.0): database, disk, processor, memory, OPcache, cache, sizes, and the time
 * of the heavy parts of RefreshGlobal. Every measure is optional (a forbidden function or file gives "unknown").
 * Nothing is executed outside PHP, nothing personal is read.
 *
 * Level: ok | warn | bad | info. An item "x" that is not ok has an advice perf.advice_x.
 */
class Probe
{
    /** @return array [['key', 'value' (string), 'level'], …] */
    public static function server()
    {
        $items = [];
        $add = function ($key, callable $f) use (&$items) {
            try {
                $r = $f();
            } catch (\Throwable $e) {
                $r = null;
            }
            $items[] = ['key' => $key, 'value' => $r === null ? '—' : (string) $r[0], 'level' => $r === null ? 'info' : $r[1]];
        };
        $add('db_ping', function () {
            \DB::select('select 1');
            $ms = self::median(15, function () {
                \DB::select('select 1');
            });

            return [self::ms($ms), self::level($ms, 2, 5)];
        });
        $add('db_write', function () {
            if (!SchemaCache::has(Recorder::TABLE)) {
                return null;
            }
            $ms = self::median(5, function () {
                $id = \DB::table(Recorder::TABLE)->insertGetId(['uid' => bin2hex(random_bytes(8)), 'route' => 'probe', 'created_at' => date('Y-m-d H:i:s')]);
                \DB::table(Recorder::TABLE)->where('id', $id)->delete();
            });

            return [self::ms($ms), self::level($ms, 25, 80)];
        });
        $add('db_buffer', function () {
            if (\DB::connection()->getDriverName() !== 'mysql') {
                return null;
            }
            $pool = (int) \DB::select("show variables like 'innodb_buffer_pool_size'")[0]->Value;
            $data = (int) \DB::select('select sum(data_length + index_length) as n from information_schema.tables where table_schema = database()')[0]->n;

            return [self::mb($pool).' / '.self::mb($data), $pool >= $data ? 'ok' : ($pool * 4 >= $data ? 'warn' : 'bad')];
        });
        $add('opcache', function () {
            if (!function_exists('opcache_get_status') || !ini_get('opcache.enable')) {
                return ['off', 'bad'];
            }
            $s = @opcache_get_status(false);
            if (!is_array($s)) {
                return ['on', 'ok'];
            }
            if (empty($s['opcache_enabled'])) {
                return ['off', 'bad'];
            }
            $m = $s['memory_usage'];
            $used = $m['used_memory'] + $m['wasted_memory'];
            $st = $s['opcache_statistics'];
            $full = !empty($s['cache_full']) || $st['oom_restarts'] > 0 || $st['num_cached_keys'] >= $st['max_cached_keys'] * 0.95;

            return [self::mb($used).' / '.self::mb($used + $m['free_memory']).', '.(int) $st['num_cached_scripts'].' files, '.round($st['opcache_hit_rate'], 1).' %',
                $full ? 'bad' : 'ok'];
        });
        $add('xdebug', function () {
            $found = array_values(array_filter(['xdebug', 'pcov', 'blackfire'], 'extension_loaded'));
            $debug = (bool) config('app.debug');

            return [($found ? implode(', ', $found) : '-').($debug ? ', APP_DEBUG' : ''), $found ? 'bad' : ($debug ? 'warn' : 'ok')];
        });
        $add('cpu', function () {
            $ms = self::median(3, function () {
                $x = '';
                for ($i = 0; $i < 60000; $i++) {
                    $x = md5($x.$i);
                }
            });

            return [self::ms($ms), self::level($ms, 40, 100)];
        });
        $add('load', function () {
            $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;
            $cores = Hardware::cores();
            if (!is_array($load)) {
                return null;
            }

            return [round($load[0], 2).' / '.($cores ?: '?'), $cores ? self::level($load[0] / $cores, 0.8, 1.5) : 'info'];
        });
        $add('disk', function () {
            $file = storage_path('framework/cache/rg-perf-'.bin2hex(random_bytes(4)).'.tmp');
            $data = str_repeat('x', 65536);
            try {
                $ms = self::median(7, function () use ($file, $data) {
                    $h = fopen($file, 'w');
                    fwrite($h, $data);
                    fflush($h);
                    if (function_exists('fsync')) {
                        fsync($h);
                    }
                    fclose($h);
                    $h = fopen($file, 'r');
                    fread($h, 65536);
                    fclose($h);
                });
            } finally {
                @unlink($file);
            }

            return [self::ms($ms), self::level($ms, 15, 60)];
        });
        $add('cache', function () {
            $key = 'refreshglobal.perf.'.bin2hex(random_bytes(4));
            $ms = self::median(10, function () use ($key) {
                \Cache::put($key, str_repeat('x', 2000), 1);
                \Cache::get($key);
            });
            \Cache::forget($key);

            return [config('cache.default').', '.self::ms($ms), self::level($ms, 5, 20)];
        });
        $add('logs', function () {
            $size = 0;
            foreach ((array) glob(storage_path('logs/*.log')) as $f) {
                $size += (int) @filesize($f);
            }

            return [self::mb($size), $size > 500 * 1048576 ? 'warn' : 'ok'];
        });
        $add('sizes', function () {
            $n = \App\Conversation::count();
            $closed = \App\Conversation::where('status', \App\Conversation::STATUS_CLOSED)->count();

            return [$n.' ('.$closed.' closed), '.\App\Thread::count().' messages', 'info'];
        });
        $add('jobs', function () {
            $n = \DB::table('jobs')->count();

            return [$n, $n > 500 ? 'bad' : ($n > 100 ? 'warn' : 'ok')];
        });
        $add('php', function () {
            $modules = [];
            foreach (\App\Module::where('active', true)->pluck('alias') as $alias) {
                $modules[] = $alias;
            }

            return [PHP_VERSION.' '.PHP_SAPI.', '.ini_get('memory_limit').', session '.config('session.driver').', '.implode(' ', $modules), 'info'];
        });

        return $items;
    }

    /** Time of the heavy parts of the module for this user, without their caches. [['key', 'ms', 'level'], …] */
    public static function parts($user)
    {
        $access = new MailboxAccess($user);
        $ids = $access->allowedIds();
        $base = function () use ($user, $ids) {
            return GlobalDashboard::baseQuery($user, $ids);
        };
        $query = new GlobalTicketQuery($access, GlobalTicketQuery::normalize(['status' => GlobalTicketQuery::listDefaultStatuses()], $access));
        $parts = [
            'list'        => function () use ($query) { $query->paginate(30); },
            'counts'      => function () use ($query) { $query->countsByMailbox(); $query->countsByStatus(); },
            'fingerprint' => function () use ($query) { $query->fingerprint(); },
            'trends'      => function () use ($base) { (new Statistics($base))->summary(); },
            'tiles'       => function () use ($base) { GlobalDashboard::tiles($base); },
            'undelivered' => function () use ($base, $access) { GlobalDashboard::undeliveredPerMailbox($base, $access->mailboxes()); },
            'checks'      => function () { (new CompatibilityChecker())->run(); },
        ];
        if (\Modules\RefreshGlobal\Services\WorkflowPacksLink::available() && FlowChanges::tableExists()) {
            $parts['flows'] = function () use ($access) { FlowChanges::computeStats(FlowChanges::filters([], $access), $access); };
        }
        $out = [];
        foreach ($parts as $key => $f) {
            $t = microtime(true);
            try {
                $f();
                $ms = (microtime(true) - $t) * 1000;
                $out[] = ['key' => $key, 'ms' => (int) round($ms), 'level' => self::level($ms, 300, 1000)];
            } catch (\Throwable $e) {
                $out[] = ['key' => $key, 'ms' => null, 'level' => 'info'];
            }
        }

        return $out;
    }

    /** Plain text report, to copy (no personal data). */
    public static function text(array $server, array $parts, array $summary)
    {
        $v = (new CompatibilityChecker());
        $lines = ['RefreshGlobal '.CompatibilityChecker::moduleVersion().' / FreeScout '.$v->freescoutVersion().' / Refresh '.($v->refreshVersion() ?: '-')];
        foreach ($server as $i) {
            $lines[] = str_pad($i['key'], 12).str_pad($i['level'], 6).$i['value'];
        }
        $lines[] = '-- parts (ms)';
        foreach ($parts as $p) {
            $lines[] = str_pad($p['key'], 12).str_pad($p['level'], 6).($p['ms'] === null ? '-' : $p['ms']);
        }
        $lines[] = '-- recorded: '.$summary['n'].' ('.$summary['from'].' > '.$summary['to'].')';
        if ($summary['share']) {
            $lines[] = 'per page (ms): '.urldecode(http_build_query($summary['share'], '', ' '));
        }
        foreach ($summary['pages'] as $p) {
            $lines[] = $p['route'].' n='.$p['n'].' median='.$p['median'].' p95='.$p['p95'].' boot='.$p['boot'].' sql='.$p['sql_n'].'/'.$p['sql_ms'].'ms repeats='.$p['repeats']
                .' ttfb='.($p['b_ttfb'] === null ? '-' : $p['b_ttfb']).' load='.($p['b_load'] === null ? '-' : $p['b_load']);
        }
        foreach ($summary['modules'] as $m) {
            $lines[] = 'module '.$m['module'].' sql/request='.$m['sql_n'].' ms='.$m['sql_ms'];
        }
        foreach ($summary['slow'] as $q) {
            $lines[] = 'slow '.$q['max'].'ms x'.$q['n'].' '.$q['module'].': '.$q['sql'];
        }

        return implode("\n", $lines);
    }

    protected static function median($times, callable $f)
    {
        $all = [];
        for ($i = 0; $i < $times; $i++) {
            $t = microtime(true);
            $f();
            $all[] = (microtime(true) - $t) * 1000;
        }

        return Recorder::percentile($all, 50);
    }

    protected static function level($value, $warn, $bad)
    {
        return $value >= $bad ? 'bad' : ($value >= $warn ? 'warn' : 'ok');
    }

    protected static function ms($ms)
    {
        return round($ms, $ms < 10 ? 2 : 0).' ms';
    }

    protected static function mb($bytes)
    {
        return round($bytes / 1048576).' MB';
    }
}
