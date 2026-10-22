<?php
/*
 * Interop Contract — validation of RefreshGlobal/interop.json (CI job "interop-contract", docs/INTEROPERABILITY.md §25).
 *
 *   php tools/validate-interop.php            exit 0 = valid, 1 = problems (listed)
 *
 * Checks, without Laravel:
 *   1. the manifest itself (Services/Interop/Manifest::validate(): JSON, manifest version, versions and ranges, unique
 *      ids, schema files present and valid JSON, UpdateGuard health section);
 *   2. consistency with the other declarations of the module: module.json healthCommand, Config/integration.php version
 *      ranges (FreeScout, Refresh legacy fallback), the health command declared in the module;
 *   3. no undocumented integration: every event emitted and every intent registered by the module's scripts (navigation,
 *      toolbar, swipe, search) is declared in interop.json, and everything declared is really emitted / registered; the
 *      declared server hooks are used by the PHP code and the declared public routes exist in Http/routes.php.
 */
$root = dirname(__DIR__);
$module = $root.'/RefreshGlobal';
require $module.'/Services/Interop/SemVer.php';
require $module.'/Services/Interop/Manifest.php';

use Modules\RefreshGlobal\Services\Interop\Manifest;

$problems = [];
$manifest = Manifest::load($module);
if (!$manifest) {
    fwrite(STDERR, "RefreshGlobal/interop.json: missing or not valid JSON\n");
    exit(1);
}

// 1. the manifest
foreach ($manifest->validate() as $error) {
    $problems[] = 'interop.json: '.$error;
}

// 2. consistency with module.json and Config/integration.php
$moduleJson = json_decode((string) file_get_contents($module.'/module.json'), true) ?: [];
$healthCommand = (string) ($moduleJson['healthCommand'] ?? '');
if ((string) $manifest->get('module.alias') !== (string) ($moduleJson['alias'] ?? '')) {
    $problems[] = 'module.alias must be the alias of module.json';
}
$health = (string) @file_get_contents($module.'/Console/HealthCommand.php');
if (strpos($health, "'".$healthCommand." {--json") === false) {
    $problems[] = "the health command $healthCommand --json is not declared in Console/HealthCommand.php";
}
$integration = require $module.'/Config/integration.php';
$versions = $integration['versions'] ?? [];
$range = function (array $v) {
    return '>='.$v['min'].' <'.$v['max_exclusive'];
};
if (isset($versions['freescout']) && $range($versions['freescout']) !== (string) $manifest->get('compatibility.freescout')) {
    $problems[] = 'compatibility.freescout must be "'.$range($versions['freescout']).'" (Config/integration.php versions.freescout)';
}
if (isset($versions['refresh']) && $range($versions['refresh']) !== (string) $manifest->get('compatibility.peers.refresh.fallback.supported_versions')) {
    $problems[] = 'compatibility.peers.refresh.fallback.supported_versions must be "'.$range($versions['refresh']).'" (Config/integration.php versions.refresh, the tested legacy range)';
}

// 3. code <-> manifest: events and intents
$js = (string) @file_get_contents($module.'/Public/js/interop-adapter.js');
preg_match_all("/FI\\.events\\.emit\\('([^']+)'/", $js, $m);
$emitted = array_values(array_unique($m[1]));
$declaredEvents = array_keys($manifest->events());
foreach (array_diff($emitted, $declaredEvents) as $event) {
    $problems[] = "event $event is emitted by Public/js/interop-adapter.js but not declared in interop.json";
}
foreach (array_diff($declaredEvents, $emitted) as $event) {
    $problems[] = "event $event is declared in interop.json but never emitted";
}
foreach (Manifest::REGISTRIES as $registry) {
    preg_match_all('/FI\\.'.$registry."\\.register\\(\\{\\s*id: '([^']+)'/", $js, $r);
    $registered = array_values(array_unique($r[1]));
    $declared = $manifest->registrations($registry);
    foreach (array_diff($registered, $declared) as $id) {
        $problems[] = "$registry intent $id is registered in Public/js/interop-adapter.js but not declared in interop.json";
    }
    foreach (array_diff($declared, $registered) as $id) {
        $problems[] = "$registry intent $id is declared in interop.json but never registered";
    }
}
// the neutral server hooks declared are really used
$php = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (substr($file->getFilename(), -4) === '.php' && strpos(str_replace('\\', '/', $file->getPathname()), '/Tests/') === false) {
        $php .= file_get_contents($file->getPathname());
    }
}
foreach (array_keys((array) $manifest->get('interop.provides.server_hooks', [])) as $hook) {
    if (strpos($php, "'".$hook."'") === false) {
        $problems[] = "server hook $hook is declared in interop.json but not used in the module's PHP code";
    }
}

// the public routes declared exist (by name, with the same method and path)
$routes = (string) @file_get_contents($module.'/Http/routes.php');
foreach ((array) $manifest->get('interop.provides.routes', []) as $name => $def) {
    $path = preg_replace('#^/refresh-global#', '', (string) ($def['path'] ?? ''));
    $pattern = '#Route::'.strtolower((string) ($def['method'] ?? ''))."\('".preg_quote($path, '#')."',[^
]*->name\('".preg_quote($name, '#')."'\)#";
    if (!preg_match($pattern, $routes)) {
        $problems[] = "route $name ({$def['method']} {$def['path']}) is declared in interop.json but not found in Http/routes.php";
    }
}

// the shared bridge: the module ships an unchanged copy of a bridge release (tools/sync-bridge.php)
$bridgeFile = $module.'/Public/js/fs-interop.js';
$bridgeLock = json_decode((string) @file_get_contents($module.'/Public/js/fs-interop.lock.json'), true);
if (!is_array($bridgeLock)) {
    $problems[] = 'Public/js/fs-interop.lock.json is missing: run php tools/sync-bridge.php';
} else {
    $bridgeCode = (string) @file_get_contents($bridgeFile);
    if (hash('sha256', $bridgeCode) !== ($bridgeLock['sha256'] ?? '')) {
        $problems[] = 'Public/js/fs-interop.js differs from its lock file: never edit it, update the shared bridge and run php tools/sync-bridge.php';
    }
    if (($bridgeLock['protocol'] ?? '') !== $manifest->protocol()) {
        $problems[] = 'the bridge copy speaks protocol '.($bridgeLock['protocol'] ?? '?').' but interop.protocol is '.$manifest->protocol();
    }
    // while the shared repository is staged here, the staged file and the module's copy are the same release
    if (is_file($root.'/interop-bridge/fs-interop.js') && hash_file('sha256', $root.'/interop-bridge/fs-interop.js') !== ($bridgeLock['sha256'] ?? '')) {
        $problems[] = 'interop-bridge/fs-interop.js and the module copy differ: run php tools/sync-bridge.php';
    }
}

if ($problems) {
    fwrite(STDERR, "Interop contract: ".count($problems)." problem(s)\n");
    foreach ($problems as $p) {
        fwrite(STDERR, ' - '.$p."\n");
    }
    exit(1);
}
echo 'Interop contract OK: protocol '.$manifest->protocol().', '.count($manifest->capabilities()).' capabilities, '
    .count($declaredEvents).' events, '.count((array) $manifest->get('interop.provides.schemas', [])).' schemas, '
    .count((array) $manifest->get('interop.provides.server_hooks', [])).' server hooks, '.count($manifest->deprecations()).' deprecated'."\n";
exit(0);
