<?php
/*
 * Updates RefreshGlobal's copy of the shared FreeScout Interop bridge from a release of the bridge repository.
 *
 *   php tools/sync-bridge.php path/to/fs-interop.js "https://github.com/altmenorg/fs-interop/releases/tag/vX.Y.Z"
 *
 * Source: fs-interop.js of a release of https://github.com/altmenorg/fs-interop (never an edited file). The file is
 * copied UNCHANGED to RefreshGlobal/Public/js/fs-interop.js and RefreshGlobal/Public/js/fs-interop.lock.json records
 * its bridge release, protocol, SHA-256 and source. tools/validate-interop.php (CI) refuses a copy that differs from
 * its lock file: a module's copy is never edited by hand.
 */
$root = dirname(__DIR__);
if (count($argv) < 3) {
    fwrite(STDERR, "usage: php tools/sync-bridge.php path/to/fs-interop.js <release URL>\n");
    exit(1);
}
$source = $argv[1];
$label = $argv[2];

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
