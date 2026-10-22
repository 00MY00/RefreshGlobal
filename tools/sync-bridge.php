<?php
/*
 * Updates RefreshGlobal's copy of the shared FreeScout Interop bridge from a release of the bridge repository.
 *
 *   php tools/sync-bridge.php [path/to/fs-interop.js] [source label]
 *
 * Default source: interop-bridge/fs-interop.js (the staging folder, until the shared repository exists). The file is
 * copied UNCHANGED to RefreshGlobal/Public/js/fs-interop.js and RefreshGlobal/Public/js/fs-interop.lock.json records
 * its bridge release, protocol, SHA-256 and source. tools/validate-interop.php (CI) refuses a copy that differs from
 * its lock file: a module's copy is never edited by hand.
 */
$root = dirname(__DIR__);
$source = $argv[1] ?? $root.'/interop-bridge/fs-interop.js';
$label = $argv[2] ?? 'interop-bridge/ (staging, until the shared repository is created)';

$code = @file_get_contents($source);
if ($code === false) {
    fwrite(STDERR, "cannot read $source\n");
    exit(1);
}
$lock = bridgeLock($code, $label);
if (!$lock) {
    fwrite(STDERR, "$source: no @bridge-version or PROTOCOL found\n");
    exit(1);
}
file_put_contents($root.'/RefreshGlobal/Public/js/fs-interop.js', $code);
file_put_contents($root.'/RefreshGlobal/Public/js/fs-interop.lock.json', json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo 'fs-interop '.$lock['version'].' (protocol '.$lock['protocol'].') copied, sha256 '.$lock['sha256']."\n";

/** Lock data of a bridge file, or null when its header is not a bridge release. */
function bridgeLock($code, $label)
{
    if (!preg_match('/@bridge-version (\d+\.\d+\.\d+)/', $code, $v) || !preg_match("/var PROTOCOL = '(\\d+\\.\\d+\\.\\d+)';/", $code, $p)) {
        return null;
    }

    return ['bridge' => 'fs-interop', 'version' => $v[1], 'protocol' => $p[1], 'sha256' => hash('sha256', $code), 'source' => $label];
}
