<?php

namespace Modules\RefreshGlobal\Services\Interop;

/**
 * interop.json — the machine-readable declaration of a module's cross-module contract (docs/INTEROPERABILITY.md):
 * what it provides (capabilities, events, surfaces, registrations, schemas, server hooks, commands), what it may
 * consume from optional peers, its compatibility ranges and its UpdateGuard metadata.
 *
 * Plain PHP (no Laravel): used by the module (health check, diagnostics, PeerStatus), by tools/validate-interop.php in
 * the CI, and by the tests.
 */
class Manifest
{
    /** Manifest versions this code understands. */
    const SUPPORTED_MANIFEST_VERSIONS = [1];

    /** Values allowed in compatibility_policy. */
    const POLICIES = [
        'peer_protocol_mismatch' => ['degraded', 'incompatible'],
        'explicit_conflict'      => ['blocked', 'warning'],
        'unknown_peer_version'   => ['warning', 'degraded', 'incompatible'],
    ];

    /** @var array decoded manifest */
    protected $data;

    /** @var string folder of the module the manifest belongs to (relative files are resolved from it) */
    protected $root;

    public function __construct(array $data, $root)
    {
        $this->data = $data;
        $this->root = rtrim((string) $root, '/\\');
    }

    /** interop.json of a module folder, or null when absent or not valid JSON. */
    public static function load($moduleRoot)
    {
        $file = rtrim((string) $moduleRoot, '/\\').'/interop.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? new self($data, $moduleRoot) : null;
    }

    /** The manifest of RefreshGlobal itself (cached per process). */
    public static function own()
    {
        static $own = false;
        if ($own === false) {
            $own = self::load(dirname(__DIR__, 2));
        }

        return $own;
    }

    public function data()
    {
        return $this->data;
    }

    public function root()
    {
        return $this->root;
    }

    /** Value at a dotted path ("interop.protocol"), or $default. */
    public function get($path, $default = null)
    {
        $value = $this->data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    public function id()
    {
        return (string) $this->get('module.id', '');
    }

    public function protocol()
    {
        return (string) $this->get('interop.protocol', '');
    }

    /** [capability => version] provided (an entry may be "1.0" or {"version": "1.0", "status": …}). */
    public function capabilities()
    {
        $out = [];
        foreach ((array) $this->get('interop.provides.capabilities', []) as $name => $def) {
            $out[$name] = is_array($def) ? (string) ($def['version'] ?? '') : (string) $def;
        }

        return $out;
    }

    /** [event name => version] provided (an entry may be "1.0" or {"version": "1.0", …}). */
    public function events()
    {
        $out = [];
        foreach ((array) $this->get('interop.provides.events', []) as $name => $def) {
            $out[$name] = is_array($def) ? (string) ($def['version'] ?? '') : (string) $def;
        }

        return $out;
    }

    /** Registries of intents a manifest may declare (interop.provides.registrations). */
    const REGISTRIES = ['navigation', 'toolbar', 'swipe', 'search'];

    /** Lifecycle of a provided element (docs/INTEROPERABILITY.md, deprecation policy). */
    const STATUSES = ['active', 'deprecated'];

    /** ["surface@context" => version] of the surfaces provided. */
    public function surfaces()
    {
        $out = [];
        foreach ((array) $this->get('interop.provides.surfaces', []) as $s) {
            if (is_array($s) && isset($s['surface'])) {
                $out[$s['surface'].'@'.($s['context'] ?? '*')] = (string) ($s['version'] ?? '1.0');
            }
        }

        return $out;
    }

    /**
     * Deprecated elements: ["capability|event|server_hook <name>" => ['replacement' => …, 'remove_in_protocol' => …]].
     * An element is deprecated when its declaration is an object with "status": "deprecated".
     */
    public function deprecations()
    {
        $out = [];
        foreach ($this->lifecycleEntries() as $label => $def) {
            if (is_array($def) && ($def['status'] ?? 'active') === 'deprecated') {
                $out[$label] = ['replacement' => (string) ($def['replacement'] ?? ''), 'remove_in_protocol' => (string) ($def['remove_in_protocol'] ?? '')];
            }
        }

        return $out;
    }

    /** Declarations that may carry a lifecycle status: ["<kind> <name>" => definition]. */
    protected function lifecycleEntries()
    {
        $entries = [];
        foreach (['capability' => 'capabilities', 'event' => 'events', 'server_hook' => 'server_hooks'] as $kind => $key) {
            foreach ((array) $this->get('interop.provides.'.$key, []) as $name => $def) {
                $entries[$kind.' '.$name] = $def;
            }
        }

        return $entries;
    }

    /** Ids of the items registered in a registry (navigation, toolbar, swipe, search). */
    public function registrations($registry)
    {
        return array_values(array_filter(array_map(function ($item) {
            return is_array($item) ? (string) ($item['id'] ?? '') : '';
        }, (array) $this->get('interop.provides.registrations.'.$registry, []))));
    }

    /** What the module expects of an optional peer: ['protocol' => range, 'capabilities' => [name => range]] or null. */
    public function consumes($peer)
    {
        $c = $this->get('interop.consumes.optional.'.$peer);

        return is_array($c) ? $c : null;
    }

    /** compatibility.peers.<peer> or null. */
    public function peerCompatibility($peer)
    {
        $p = $this->get('compatibility.peers.'.$peer);

        return is_array($p) ? $p : null;
    }

    public function policy($name)
    {
        $value = (string) $this->get('compatibility_policy.'.$name, '');

        return in_array($value, self::POLICIES[$name] ?? [], true) ? $value : (self::POLICIES[$name][0] ?? '');
    }

    /**
     * Every rule of the contract (CI job "Interop Contract", health check, tests).
     *
     * @return string[] problems; empty = valid
     */
    public function validate()
    {
        $errors = [];
        $d = $this->data;

        // 1. manifest and module
        if (!in_array($d['manifest_version'] ?? null, self::SUPPORTED_MANIFEST_VERSIONS, true)) {
            $errors[] = 'manifest_version must be one of '.implode(', ', self::SUPPORTED_MANIFEST_VERSIONS);
        }
        foreach (['module.id', 'module.name', 'interop.protocol'] as $required) {
            if ((string) $this->get($required, '') === '') {
                $errors[] = $required.' is required';
            }
        }
        if ($this->protocol() !== '' && !SemVer::isVersion($this->protocol())) {
            $errors[] = 'interop.protocol is not a version: '.$this->protocol();
        }

        // 2. provided versions
        foreach ($this->capabilities() as $name => $version) {
            if (!SemVer::isVersion($version)) {
                $errors[] = "capability $name: invalid version";
            }
        }
        foreach ($this->events() as $name => $version) {
            if (!SemVer::isVersion($version)) {
                $errors[] = "event $name: invalid version";
            }
        }

        // 3. unique ids in every list
        foreach (self::REGISTRIES as $registry) {
            $ids = $this->registrations($registry);
            foreach (array_unique(array_diff_assoc($ids, array_unique($ids))) as $dup) {
                $errors[] = "registration $registry: duplicate id $dup";
            }
        }
        $surfaces = [];
        foreach ((array) $this->get('interop.provides.surfaces', []) as $s) {
            $key = ($s['surface'] ?? '').'@'.($s['context'] ?? '*');
            if (isset($surfaces[$key])) {
                $errors[] = "surface $key declared twice";
            }
            $surfaces[$key] = true;
        }

        // 4. schemas: version and file present; every schema referenced by an event / registration / hook exists
        $schemas = (array) $this->get('interop.provides.schemas', []);
        foreach ($schemas as $name => $schema) {
            if (!SemVer::isVersion($schema['version'] ?? null)) {
                $errors[] = "schema $name: invalid version";
            }
            $file = (string) ($schema['file'] ?? '');
            if ($file === '' || !$this->fileExists($file)) {
                $errors[] = "schema $name: file not found ($file)";
            } elseif (!is_array(json_decode((string) @file_get_contents($this->resolve($file)), true))) {
                $errors[] = "schema $name: $file is not valid JSON";
            }
        }
        $referenced = [];
        foreach ((array) $this->get('interop.provides.events', []) as $def) {
            if (is_array($def) && isset($def['schema'])) {
                $referenced[] = $def['schema'];
            }
        }
        foreach (self::REGISTRIES as $registry) {
            foreach ((array) $this->get('interop.provides.registrations.'.$registry, []) as $item) {
                if (isset($item['schema'])) {
                    $referenced[] = $item['schema'];
                }
            }
        }
        foreach ((array) $this->get('interop.provides.server_hooks', []) as $hook => $def) {
            if (!in_array($def['type'] ?? '', ['filter', 'action'], true)) {
                $errors[] = "server hook $hook: type must be filter or action";
            }
            if (isset($def['schema'])) {
                $referenced[] = $def['schema'];
            }
        }
        foreach ((array) $this->get('interop.provides.routes', []) as $route => $def) {
            if (!in_array($def['method'] ?? '', ['GET', 'POST'], true) || strpos((string) ($def['path'] ?? ''), '/') !== 0) {
                $errors[] = "route $route: method (GET or POST) and path are required";
            }
            if (isset($def['schema'])) {
                $referenced[] = $def['schema'];
            }
        }
        foreach (array_unique($referenced) as $schema) {
            if (!isset($schemas[$schema])) {
                $errors[] = "schema $schema is referenced but not declared";
            }
        }

        // 4b. lifecycle (deprecation policy): a deprecated element names its removal protocol, a later major version
        foreach ($this->lifecycleEntries() as $label => $def) {
            if (!is_array($def) || !isset($def['status'])) {
                continue;
            }
            if (!in_array($def['status'], self::STATUSES, true)) {
                $errors[] = "$label: status must be one of ".implode(', ', self::STATUSES);
            } elseif ($def['status'] === 'deprecated') {
                $remove = (string) ($def['remove_in_protocol'] ?? '');
                if (!SemVer::isVersion($remove) || SemVer::compare($remove, $this->protocol()) <= 0 || (int) $remove <= (int) $this->protocol()) {
                    $errors[] = "$label: a deprecated element needs remove_in_protocol, a later major protocol version";
                }
            }
        }

        // 5. consumed and compatibility ranges
        foreach ((array) $this->get('interop.consumes.optional', []) as $peer => $c) {
            if (!SemVer::isRange((string) ($c['protocol'] ?? ''))) {
                $errors[] = "consumes $peer: invalid protocol range";
            }
            foreach ((array) ($c['capabilities'] ?? []) as $cap => $range) {
                if (!SemVer::isRange((string) $range)) {
                    $errors[] = "consumes $peer.$cap: invalid range";
                }
            }
        }
        foreach (['freescout', 'php'] as $what) {
            if (!SemVer::isRange((string) $this->get('compatibility.'.$what, ''))) {
                $errors[] = "compatibility.$what: invalid range";
            }
        }
        foreach ((array) $this->get('compatibility.peers', []) as $peer => $p) {
            if (!is_bool($p['required'] ?? null)) {
                $errors[] = "compatibility.peers.$peer.required must be true or false";
            }
            foreach (['supported_versions', 'interop_protocol'] as $key) {
                if (isset($p[$key]) && !SemVer::isRange((string) $p[$key])) {
                    $errors[] = "compatibility.peers.$peer.$key: invalid range";
                }
            }
            if (isset($p['fallback']) && !SemVer::isRange((string) ($p['fallback']['supported_versions'] ?? ''))) {
                $errors[] = "compatibility.peers.$peer.fallback.supported_versions: invalid range";
            }
            foreach ((array) ($p['conflicts'] ?? []) as $conflict) {
                if (!SemVer::isRange((string) $conflict)) {
                    $errors[] = "compatibility.peers.$peer.conflicts: invalid range $conflict";
                }
            }
        }
        foreach (self::POLICIES as $name => $allowed) {
            $value = $this->get('compatibility_policy.'.$name);
            if ($value !== null && !in_array($value, $allowed, true)) {
                $errors[] = "compatibility_policy.$name must be one of ".implode(', ', $allowed);
            }
        }

        // 6. UpdateGuard section
        if ($this->get('updateguard') !== null) {
            if ((string) $this->get('updateguard.health.json_command', '') === '' || (string) $this->get('updateguard.health.command', '') === '') {
                $errors[] = 'updateguard.health: command and json_command are required';
            }
            $codes = (array) $this->get('updateguard.health.exit_codes', []);
            if (($codes['healthy'] ?? null) !== 0) {
                $errors[] = 'updateguard.health.exit_codes.healthy must be 0';
            }
            if ($this->get('updateguard.health.read_only') !== true || $this->get('updateguard.health.network') !== false) {
                $errors[] = 'updateguard.health must be read_only and without network';
            }
        }

        // 7. files of the module the manifest points to
        foreach (['interop.bridge'] as $path) {
            $file = (string) $this->get($path, '');
            if ($file !== '' && !$this->fileExists($file)) {
                $errors[] = "$path: file not found ($file)";
            }
        }

        return $errors;
    }

    /** Files are relative to the module folder; documentation files may also be at the repository root (one level up). */
    protected function resolve($file)
    {
        $inModule = $this->root.'/'.$file;

        return is_file($inModule) ? $inModule : dirname($this->root).'/'.$file;
    }

    protected function fileExists($file)
    {
        return is_file($this->resolve($file));
    }
}
