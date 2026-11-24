<?php

namespace Modules\RefreshGlobal\Services\Perf;

/**
 * The machine as Linux shows it to PHP (1.23.0): processor, limits of the container, memory, disks, and how long the
 * tasks wait for them (pressure). Plain files of /proc and /sys only, read when allowed (open_basedir, container):
 * anything unreadable is left out. Never read: host name, addresses, serial numbers, environment, processes.
 */
class Hardware
{
    /** @var string root of the files (tests) */
    public static $root = '';

    /** @return array [['key', 'value', 'level'], …] like Probe::server() */
    public static function items()
    {
        $items = [];
        foreach (['machine', 'processor', 'cpu_limit', 'steal', 'memory', 'memory_limit', 'pressure_cpu', 'pressure_memory', 'pressure_io',
            'disk_type', 'disk_free', 'database'] as $key) {
            try {
                $r = self::$key();
            } catch (\Throwable $e) {
                $r = null;
            }
            if ($r !== null) {
                $items[] = ['key' => $key, 'value' => (string) $r[0], 'level' => $r[1]];
            }
        }

        return $items;
    }

    protected static function read($path, $max = 65536)
    {
        $text = @file_get_contents(self::$root.$path, false, null, 0, $max);

        return $text === false ? '' : trim($text);
    }

    /** Kind of machine: container, virtual machine, model, system. */
    protected static function machine()
    {
        $kernel = self::read('/proc/sys/kernel/osrelease');
        $parts = [];
        $container = self::read('/run/systemd/container');
        if ($container === '' && @is_file(self::$root.'/.dockerenv')) {
            $container = 'docker';
        }
        if ($container === '' && preg_match('/lxc|docker|kubepods/', self::read('/proc/1/cgroup'), $m)) {
            $container = $m[0];
        }
        if (preg_match('/^[a-z0-9-]{1,30}$/', $container)) {
            $parts[] = 'container '.$container;
        }
        if (preg_match('/pve|microsoft|aws|azure|gcp|synology/i', $kernel, $m)) {
            $parts[] = strtolower($m[0]) === 'pve' ? 'Proxmox' : $m[0];
        }
        foreach (['sys_vendor', 'product_name'] as $file) {
            $v = self::read('/sys/class/dmi/id/'.$file, 100);
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        if (preg_match('/^PRETTY_NAME="?([^"\n]+)/m', self::read('/etc/os-release'), $m)) {
            $parts[] = $m[1];
        }
        if ($kernel !== '') {
            $parts[] = 'Linux '.$kernel;
        }

        return $parts ? [mb_substr(implode(', ', $parts), 0, 250), 'info'] : [PHP_OS, 'info'];
    }

    public static function cores()
    {
        return preg_match_all('/^processor\s*:/m', self::read('/proc/cpuinfo', 1048576));
    }

    protected static function processor()
    {
        $info = self::read('/proc/cpuinfo', 1048576);
        if (!preg_match('/^(?:model name|Hardware|Model)\s*:\s*(.+)$/m', $info, $m)) {
            return null;
        }
        $mhz = preg_match('/^cpu MHz\s*:\s*([\d.]+)/m', $info, $f) ? ', '.round($f[1]).' MHz' : '';
        $cores = self::cores();

        return [mb_substr(trim($m[1]), 0, 120).' × '.$cores.$mhz, $cores >= 2 ? 'ok' : 'warn'];
    }

    /** Share of the processor the container may use. */
    protected static function cpu_limit()
    {
        if (preg_match('/^(\d+) (\d+)/', self::read('/sys/fs/cgroup/cpu.max'), $m) && (int) $m[2] > 0) {
            $cpus = $m[1] / $m[2];
        } else {
            $quota = (int) self::read('/sys/fs/cgroup/cpu/cpu.cfs_quota_us');
            $period = (int) self::read('/sys/fs/cgroup/cpu/cpu.cfs_period_us');
            if ($quota <= 0 || $period <= 0) {
                return null;
            }
            $cpus = $quota / $period;
        }
        $throttled = preg_match('/^nr_throttled (\d+)/m', self::read('/sys/fs/cgroup/cpu.stat'), $t) ? (int) $t[1] : 0;
        $periods = preg_match('/^nr_periods (\d+)/m', self::read('/sys/fs/cgroup/cpu.stat'), $p) ? (int) $p[1] : 0;
        $share = $periods > 0 ? 100 * $throttled / $periods : 0;

        return [round($cpus, 2).' CPU'.($periods ? ', '.round($share, 1).' %' : ''), $cpus < 1 || $share > 10 ? 'bad' : ($cpus < 2 || $share > 2 ? 'warn' : 'ok')];
    }

    /** Processor time taken by the other machines of the host, since the start. */
    protected static function steal()
    {
        if (!preg_match('/^cpu\s+(.+)$/m', self::read('/proc/stat'), $m)) {
            return null;
        }
        $v = array_map('floatval', preg_split('/\s+/', trim($m[1])));
        $total = array_sum(array_slice($v, 0, 8));
        if (count($v) < 8 || $total <= 0) {
            return null;
        }
        $steal = 100 * $v[7] / $total;
        $wait = 100 * $v[4] / $total;

        return [round($steal, 1).' %, iowait '.round($wait, 1).' %', $steal > 10 || $wait > 20 ? 'bad' : ($steal > 3 || $wait > 8 ? 'warn' : 'ok')];
    }

    protected static function memory()
    {
        $info = self::read('/proc/meminfo');
        $get = function ($name) use ($info) {
            return preg_match('/^'.$name.':\s+(\d+)/m', $info, $m) ? $m[1] * 1024 : 0;
        };
        $total = $get('MemTotal');
        if (!$total) {
            return null;
        }
        $free = $get('MemAvailable');
        $swap = $get('SwapTotal') - $get('SwapFree');

        return [self::mb($free).' / '.self::mb($total).', swap '.self::mb($swap),
            $free < $total * 0.05 ? 'bad' : ($free < $total * 0.15 || $swap > $total * 0.25 ? 'warn' : 'ok')];
    }

    protected static function memory_limit()
    {
        $max = self::read('/sys/fs/cgroup/memory.max');
        $used = self::read('/sys/fs/cgroup/memory.current');
        if (!ctype_digit($max) || !ctype_digit($used) || (float) $max <= 0) {
            return null;
        }
        $events = preg_match('/^oom_kill (\d+)/m', self::read('/sys/fs/cgroup/memory.events'), $m) ? (int) $m[1] : 0;

        return [self::mb($used).' / '.self::mb($max).($events ? ', OOM × '.$events : ''), $events ? 'bad' : 'info'];
    }

    protected static function pressure_cpu()
    {
        return self::pressure('cpu');
    }

    protected static function pressure_memory()
    {
        return self::pressure('memory');
    }

    protected static function pressure_io()
    {
        return self::pressure('io');
    }

    /** Share of the time some tasks waited for this resource, over the last 10 s and 5 minutes. */
    protected static function pressure($what)
    {
        $text = self::read('/sys/fs/cgroup/'.$what.'.pressure');
        if ($text === '') {
            $text = self::read('/proc/pressure/'.$what);
        }
        if (!preg_match('/^some avg10=([\d.]+) avg60=[\d.]+ avg300=([\d.]+)/m', $text, $m)) {
            return null;
        }
        $v = max((float) $m[1], (float) $m[2]);

        return [$m[1].' % (10 s), '.$m[2].' % (5 min)', $v >= 25 ? 'bad' : ($v >= 8 ? 'warn' : 'ok')];
    }

    /** File system of FreeScout, and the kind of the disks seen. */
    protected static function disk_type()
    {
        $base = base_path();
        $best = '';
        $type = '';
        $sync = '';
        foreach (explode("\n", self::read('/proc/mounts')) as $line) {
            $f = explode(' ', $line);
            if (count($f) >= 4 && ($base === $f[1] || strpos($base, rtrim($f[1], '/').'/') === 0) && strlen($f[1]) >= strlen($best)) {
                $best = $f[1];
                $type = $f[2];
                $sync = in_array('sync', explode(',', $f[3]), true) ? ', sync' : '';
            }
        }
        $disks = [];
        foreach ((array) @glob(self::$root.'/sys/block/*') as $dir) {
            $name = basename((string) $dir);
            if (!preg_match('/^(sd|nvme|vd|xvd|mmcblk|hd)/', $name)) {
                continue;
            }
            $rotational = self::read('/sys/block/'.$name.'/queue/rotational');
            $kind = $rotational === '1' ? 'HDD' : (strpos($name, 'nvme') === 0 ? 'NVMe' : 'SSD');
            $disks[] = trim(self::read('/sys/block/'.$name.'/device/model', 60).' '.$kind);
        }
        if ($type === '' && !$disks) {
            return null;
        }
        $remote = preg_match('/^(nfs|cifs|smb|9p|fuse)/', $type);

        // a disk seen from a container is the host's, and virtual disks call themselves HDD: shown, never an alert (the
        // write test and the wait for the disk tell)
        return [$type.$sync.($disks ? ' — '.mb_substr(implode(', ', array_slice(array_unique($disks), 0, 6)), 0, 200) : ''),
            $remote || $sync ? 'bad' : 'info'];
    }

    protected static function disk_free()
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if (!$total) {
            return null;
        }

        return [round($free / 1073741824, 1).' / '.round($total / 1073741824, 1).' GB', $free < $total * 0.05 ? 'bad' : ($free < $total * 0.1 ? 'warn' : 'ok')];
    }

    /** Database: version, and whether it is on this machine (its address is not shown). */
    protected static function database()
    {
        $connection = \DB::connection();
        $host = (string) $connection->getConfig('host');
        $local = in_array($host, ['', 'localhost', '127.0.0.1', '::1'], true) || (string) $connection->getConfig('unix_socket') !== '';
        $version = $connection->getDriverName() === 'mysql' ? \DB::select('select version() as v')[0]->v : $connection->getDriverName();

        return [mb_substr((string) $version, 0, 80).', '.($local ? 'local' : 'remote'), 'info'];
    }

    public static function mb($bytes)
    {
        return round($bytes / 1048576).' MB';
    }
}
