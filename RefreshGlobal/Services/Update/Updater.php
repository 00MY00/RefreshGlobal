<?php

namespace Modules\RefreshGlobal\Services\Update;

/**
 * Safe automatic update of RefreshGlobal, with automatic rollback.
 *
 *  1. reads module.json of the latest release (config refreshglobal.update_url);
 *  2. downloads RefreshGlobal.zip and checks it against SHA256SUMS (mandatory);
 *  3. state BEFORE: php artisan refreshglobal:check (separate process);
 *  4. backup: module folder + rows of its table + list of its applied migrations
 *     (storage/app/refreshglobal/backups/<version>-<date>/);
 *  5. swaps the folders, then php artisan freescout:module-install refreshglobal (what Manage › Modules does,
 *     app/Http/Controllers/ModulesController.php:257) in a separate process, so the NEW code is loaded;
 *  6. state AFTER: refreshglobal:check + refreshglobal:selftest (the page rendered for an admin);
 *  7. if the install failed, the state is blocking or worse than before, or the self-test failed: rollback
 *     (new migrations rolled back, old folder put back, table rows restored, caches rebuilt).
 *
 * A version that was rolled back is not retried automatically (only with --force).
 * Never run from a web request: always from the CLI (scheduler or php artisan refreshglobal:update).
 *
 * Only PHP, FreeScout and vendor classes are used once the folders are swapped: the classes of this module that
 * are already loaded stay in memory, nothing of the module is autoloaded again during the update.
 */
class Updater
{
    const MODULE = 'RefreshGlobal';
    const ALIAS = 'refreshglobal';
    const TABLE = 'refreshglobal_saved_views';
    const OPTION = 'refreshglobal.auto_update';
    const KEEP_BACKUPS = 3;

    /** @var callable */
    protected $out;

    /** @var array */
    protected $log = [];

    public function __construct(callable $out = null)
    {
        $this->out = $out ?: function ($line) {
        };
    }

    // ------------------------------------------------------------------------------------------ settings / status

    public static function enabled()
    {
        try {
            // without Option's in-memory cache: Option::set() does not refresh it (app/Option.php)
            return (bool) \App\Option::get(self::OPTION, (bool) config('refreshglobal.auto_update_default', false), true, false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setEnabled($enabled)
    {
        \App\Option::set(self::OPTION, $enabled ? 1 : 0);
    }

    public static function dir($sub = '')
    {
        return storage_path('app/refreshglobal'.($sub !== '' ? '/'.$sub : ''));
    }

    public static function status()
    {
        $file = self::dir('update-status.json');
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
    }

    protected function saveStatus(array $changes)
    {
        $status = array_merge(self::status(), $changes);
        @mkdir(self::dir(), 0775, true);
        @file_put_contents(self::dir('update-status.json'), json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $status;
    }

    public static function currentVersion()
    {
        $json = json_decode((string) @file_get_contents(base_path('Modules/'.self::MODULE.'/module.json')), true);

        return is_array($json) && !empty($json['version']) ? (string) $json['version'] : '0.0.0';
    }

    protected function say($line, $level = 'info')
    {
        $this->log[] = $line;
        call_user_func($this->out, $line);
        try {
            \Log::{$level === 'error' ? 'error' : ($level === 'warning' ? 'warning' : 'info')}('[RefreshGlobal] [update] '.$line);
        } catch (\Exception $e) {
        }
    }

    // --------------------------------------------------------------------------------------------- release source

    protected function url($file)
    {
        return rtrim((string) config('refreshglobal.update_url'), '/').'/'.$file;
    }

    /** Body of a release file (https://, or file:// / absolute path for tests and offline mirrors). */
    protected function fetch($file, $to = null)
    {
        $url = $this->url($file);
        if (strpos($url, 'file://') === 0 || strpos($url, '/') === 0) {
            $path = strpos($url, 'file://') === 0 ? substr($url, 7) : $url;
            if (!is_file($path)) {
                throw new \RuntimeException('not found: '.$url);
            }
            if ($to) {
                copy($path, $to);

                return '';
            }

            return (string) file_get_contents($path);
        }
        $options = method_exists(\App\Misc\Helper::class, 'setGuzzleDefaultOptions')
            ? \App\Misc\Helper::setGuzzleDefaultOptions(['timeout' => 120]) : ['timeout' => 120];
        if ($to) {
            $options['sink'] = $to;
        }
        $response = (new \GuzzleHttp\Client())->request('GET', $url, $options);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('HTTP '.$response->getStatusCode().': '.$url);
        }

        return $to ? '' : (string) $response->getBody();
    }

    /** ['current', 'latest', 'available', 'required_app', 'compatible'] — also saved in the status file. */
    public function check()
    {
        $current = self::currentVersion();
        $manifest = json_decode($this->fetch('module.json'), true);
        if (!is_array($manifest) || empty($manifest['version']) || ($manifest['alias'] ?? '') !== self::ALIAS) {
            throw new \RuntimeException('invalid module.json in the release');
        }
        $latest = (string) $manifest['version'];
        $required = (string) ($manifest['requiredAppVersion'] ?? '');
        $info = [
            'current'      => $current,
            'latest'       => $latest,
            'available'    => version_compare($latest, $current, '>'),
            'required_app' => $required,
            'compatible'   => $required === '' || version_compare((string) config('app.version'), $required, '>='),
        ];
        $this->saveStatus(['last_check_at' => date('c'), 'current' => $current, 'latest' => $latest, 'enabled' => self::enabled()]);

        return $info;
    }

    // ---------------------------------------------------------------------------------------------------- update

    /**
     * @return array ['result' => up_to_date|updated|rolled_back|skipped|incompatible|failed, 'from', 'to', 'reason', 'log']
     */
    public function update($force = false)
    {
        @mkdir(self::dir(), 0775, true);
        $lock = @fopen(self::dir('update.lock'), 'c') ?: null;
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->finish('skipped', '', '', 'another update is running');
        }
        try {
            return $this->doUpdate($force);
        } catch (\Throwable $e) {
            $this->say('error: '.$e->getMessage(), 'error');

            return $this->finish('failed', self::currentVersion(), '', $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function doUpdate($force)
    {
        $info = $this->check();
        $from = $info['current'];
        $to = $info['latest'];
        if (!$info['available'] && !$force) {
            $this->say('RefreshGlobal '.$from.' is up to date.');

            return $this->finish('up_to_date', $from, $to, '');
        }
        if (!$info['compatible']) {
            $this->say('RefreshGlobal '.$to.' requires FreeScout '.$info['required_app'].': not installed.', 'warning');

            return $this->finish('incompatible', $from, $to, 'FreeScout '.$info['required_app'].' required');
        }
        $status = self::status();
        if (!$force && ($status['failed_version'] ?? '') === $to) {
            $this->say('RefreshGlobal '.$to.' was rolled back before: not retried automatically (use --force).', 'warning');

            return $this->finish('skipped', $from, $to, 'version '.$to.' was rolled back before');
        }

        // 1. download + checksum
        $work = self::dir('tmp/'.date('YmdHis'));
        @mkdir($work, 0775, true);
        $zip = $work.'/RefreshGlobal.zip';
        $this->say('Downloading RefreshGlobal '.$to.'…');
        $this->fetch('RefreshGlobal.zip', $zip);
        $sums = $this->fetch('SHA256SUMS');
        if (!preg_match('/^([a-f0-9]{64})\s+\*?RefreshGlobal\.zip\s*$/mi', $sums, $m)) {
            throw new \RuntimeException('SHA256SUMS of the release has no line for RefreshGlobal.zip');
        }
        if (!hash_equals(strtolower($m[1]), hash_file('sha256', $zip))) {
            throw new \RuntimeException('checksum mismatch: the downloaded archive is not installed');
        }
        $archive = new \ZipArchive();
        if ($archive->open($zip) !== true || !$archive->extractTo($work.'/x')) {
            throw new \RuntimeException('cannot extract the archive');
        }
        $archive->close();
        $new = $work.'/x/'.self::MODULE;
        $manifest = json_decode((string) @file_get_contents($new.'/module.json'), true);
        if (!is_array($manifest) || ($manifest['alias'] ?? '') !== self::ALIAS || ($manifest['version'] ?? '') !== $to) {
            throw new \RuntimeException('the archive does not contain RefreshGlobal '.$to);
        }

        // 2. state before
        $before = $this->checkState();
        $this->say('State before: '.$before);

        // 3. backup
        $backup = self::dir('backups/'.$from.'-'.date('YmdHis'));
        @mkdir($backup, 0775, true);
        $rows = \Schema::hasTable(self::TABLE) ? \DB::table(self::TABLE)->get()->map(function ($r) {
            return (array) $r;
        })->all() : null;
        file_put_contents($backup.'/table.json', json_encode($rows));
        $migrationsBefore = \DB::table('migrations')->pluck('migration')->all();

        // 4. swap the folders
        $current = base_path('Modules/'.self::MODULE);
        $this->move($current, $backup.'/module');
        $this->move($new, $current);
        $this->say('Files of '.$to.' in place (backup: '.$backup.').');

        // 5. install + checks, in new processes (new code)
        $install = $this->artisan(['freescout:module-install', self::ALIAS]);
        $problem = '';
        if ($install['code'] !== 0 || strpos($install['output'], 'Configuration cached successfully') === false) {
            $problem = 'freescout:module-install failed (code '.$install['code'].')';
        }
        $after = $problem === '' ? $this->checkState() : 'unknown';
        if ($problem === '') {
            $rank = ['ok' => 0, 'warning' => 1, 'degraded' => 2, 'blocking' => 3, 'unknown' => 4];
            if ($rank[$after] >= 3 || $rank[$after] > max($rank[$before] ?? 0, 1)) {
                $problem = 'compatibility state '.$after.' (before: '.$before.')';
            }
        }
        if ($problem === '') {
            $self = $this->artisan(['refreshglobal:selftest']);
            if ($self['code'] !== 0) {
                $problem = 'self-test failed: '.trim(substr($self['output'], -300));
            }
        }

        if ($problem === '') {
            $this->say('RefreshGlobal updated '.$from.' → '.$to.' (state: '.$after.').');
            $this->prune();
            $this->deleteDir($work);

            return $this->finish('updated', $from, $to, '', ['failed_version' => '', 'current' => $to]);
        }

        // 6. rollback
        $this->say('Problem after the update: '.$problem.'. Rolling back to '.$from.'…', 'error');
        $added = array_values(array_diff(\DB::table('migrations')->pluck('migration')->all(), $migrationsBefore));
        if ($added) {
            $r = $this->artisan(['migrate:rollback', '--path=Modules/'.self::MODULE.'/Database/Migrations', '--force']);
            $this->say('Rollback of the new migrations ('.implode(', ', $added).'): code '.$r['code'], $r['code'] === 0 ? 'info' : 'warning');
        }
        $this->deleteDir($current);
        $this->move($backup.'/module', $current);
        if ($rows !== null && \Schema::hasTable(self::TABLE)) {
            \DB::table(self::TABLE)->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                \DB::table(self::TABLE)->insert($chunk);
            }
        }
        $reinstall = $this->artisan(['freescout:module-install', self::ALIAS]);
        $state = $this->checkState();
        $this->say('Rolled back to '.$from.' (install code '.$reinstall['code'].', state '.$state.').', 'error');
        $this->deleteDir($work);

        return $this->finish('rolled_back', $from, $to, $problem, ['failed_version' => $to, 'current' => $from]);
    }

    protected function finish($result, $from, $to, $reason, array $extra = [])
    {
        $this->saveStatus(array_merge([
            'last_run_at' => date('c'),
            'last_result' => $result,
            'last_from'   => $from,
            'last_to'     => $to,
            'last_reason' => $reason,
        ], $extra));

        return ['result' => $result, 'from' => $from, 'to' => $to, 'reason' => $reason, 'log' => $this->log];
    }

    /** State of refreshglobal:check run in a separate process (ok|warning|degraded|blocking|unknown). */
    protected function checkState()
    {
        $r = $this->artisan(['refreshglobal:check', '--json']);
        $start = strpos($r['output'], '{');
        $json = $start !== false ? json_decode(substr($r['output'], $start), true) : null;

        return is_array($json) && isset($json['state']) ? (string) $json['state'] : 'unknown';
    }

    protected function artisan(array $args, $timeout = 900)
    {
        $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false) ?: 'php';
        $process = new \Symfony\Component\Process\Process(array_merge([$php, base_path('artisan')], $args), base_path(), null, null, $timeout);
        $process->run();

        return ['code' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }

    protected function move($from, $to)
    {
        if (!@rename($from, $to)) {
            if (!\File::copyDirectory($from, $to)) {
                throw new \RuntimeException('cannot move '.$from.' to '.$to);
            }
            \File::deleteDirectory($from);
        }
    }

    protected function deleteDir($dir)
    {
        if (is_dir($dir)) {
            \File::deleteDirectory($dir);
        }
    }

    /** Keeps the last KEEP_BACKUPS backups. */
    protected function prune()
    {
        $dirs = glob(self::dir('backups/*'), GLOB_ONLYDIR) ?: [];
        usort($dirs, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        foreach (array_slice($dirs, self::KEEP_BACKUPS) as $dir) {
            $this->deleteDir($dir);
        }
    }
}
