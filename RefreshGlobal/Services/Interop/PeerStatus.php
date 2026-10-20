<?php

namespace Modules\RefreshGlobal\Services\Interop;

/**
 * How RefreshGlobal cooperates with an optional peer module (Refresh) on this installation, decided from the declared
 * contracts only (docs/INTEROPERABILITY.md §21, §28, §34–37):
 *
 *   mode           native      the peer declares a compatible FreeScout Interop protocol (its interop.json):
 *                              cooperation through FreeScoutInterop (registries, context, events)
 *                  legacy      no compatible contract, but a peer version this RefreshGlobal was tested with:
 *                              the documented DOM fallbacks of Config/integration.php are used
 *                  standalone  the peer is not installed or not active: RefreshGlobal works alone
 *                  unavailable the peer is installed but nothing can be used (unknown version, protocol mismatch
 *                              with policy "incompatible"): integration off, RefreshGlobal works alone
 *   compatibility  COMPATIBLE | DEGRADED | UNKNOWN | INCOMPATIBLE (§28)
 *   capabilities   [name => native | legacy | unavailable] for each capability RefreshGlobal consumes
 *
 * The peer stays optional whatever the result: only an explicit conflict of the manifest gives INCOMPATIBLE.
 * Pure logic: evaluate() receives the facts (installed, active, version, peer manifest); fromInstallation() collects
 * them from FreeScout.
 */
class PeerStatus
{
    const COMPATIBLE = 'COMPATIBLE';
    const DEGRADED = 'DEGRADED';
    const UNKNOWN = 'UNKNOWN';
    const INCOMPATIBLE = 'INCOMPATIBLE';

    /**
     * @param Manifest      $own       RefreshGlobal's manifest
     * @param string        $peer      peer id in the manifest ("refresh")
     * @param array         $facts     ['installed' => bool, 'active' => bool, 'version' => string]
     * @param Manifest|null $peerManifest the peer's interop.json, null when it has none
     * @return array ['peer', 'installed', 'active', 'version', 'manifest', 'protocol', 'mode', 'compatibility',
     *                'capabilities', 'legacy_fallbacks', 'reasons']
     */
    public static function evaluate(Manifest $own, $peer, array $facts, Manifest $peerManifest = null)
    {
        $consumes = $own->consumes($peer) ?: ['protocol' => '', 'capabilities' => []];
        $compat = $own->peerCompatibility($peer) ?: [];
        $wanted = (array) ($consumes['capabilities'] ?? []);
        $version = (string) ($facts['version'] ?? '');
        $out = [
            'peer'             => $peer,
            'installed'        => !empty($facts['installed']),
            'active'           => !empty($facts['active']),
            'version'          => $version,
            'manifest'         => $peerManifest !== null,
            'protocol'         => $peerManifest ? $peerManifest->protocol() : '',
            'mode'             => 'standalone',
            'compatibility'    => self::COMPATIBLE,
            'capabilities'     => array_fill_keys(array_keys($wanted), 'unavailable'),
            'legacy_fallbacks' => false,
            'reasons'          => [],
        ];

        // 1. peer absent or inactive: RefreshGlobal alone, nothing to negotiate (the peer is optional)
        if (!$out['installed'] || !$out['active']) {
            $out['reasons'][] = $out['installed'] ? 'peer installed but not active' : 'peer not installed';

            return $out;
        }

        // 2. explicit conflicts of the manifest come first (§34)
        foreach ((array) ($compat['conflicts'] ?? []) as $range) {
            if (SemVer::satisfies($version, $range)) {
                $out['mode'] = 'unavailable';
                $out['compatibility'] = $own->policy('explicit_conflict') === 'blocked' ? self::INCOMPATIBLE : self::DEGRADED;
                $out['reasons'][] = "peer version $version is declared in conflict ($range)";

                return $out;
            }
        }

        // 3. the peer declares a compatible protocol: native cooperation, capability by capability
        $protocolRange = (string) ($compat['interop_protocol'] ?? ($consumes['protocol'] ?? ''));
        if ($peerManifest && SemVer::satisfies($peerManifest->protocol(), $protocolRange)) {
            $out['mode'] = 'native';
            $provided = $peerManifest->capabilities();
            $missing = [];
            foreach ($wanted as $cap => $range) {
                $ok = isset($provided[$cap]) && SemVer::satisfies($provided[$cap], $range);
                $out['capabilities'][$cap] = $ok ? 'native' : (self::inFallback($compat, $version) ? 'legacy' : 'unavailable');
                if (!$ok) {
                    $missing[] = $cap;
                }
            }
            $out['legacy_fallbacks'] = in_array('legacy', $out['capabilities'], true);
            if ($missing) {
                $out['compatibility'] = self::DEGRADED;
                $out['reasons'][] = 'capabilities not provided in a supported version: '.implode(', ', $missing);
            }

            return $out;
        }
        if ($peerManifest) {
            $out['reasons'][] = 'peer protocol '.$peerManifest->protocol()." outside $protocolRange";
        }

        // 4. no compatible contract: the documented legacy fallbacks, if this peer version was tested with them
        if (self::inFallback($compat, $version)) {
            $out['mode'] = 'legacy';
            $out['legacy_fallbacks'] = true;
            $out['capabilities'] = array_fill_keys(array_keys($wanted), 'legacy');
            $out['compatibility'] = self::DEGRADED;
            $out['reasons'][] = 'legacy compatibility mode (DOM fallbacks of Config/integration.php)';

            return $out;
        }

        // 5. a protocol mismatch or an untested peer version: the policy of the manifest decides (§36, §37)
        if ($peerManifest) {
            $out['mode'] = 'unavailable';
            $out['compatibility'] = $own->policy('peer_protocol_mismatch') === 'incompatible' ? self::INCOMPATIBLE : self::DEGRADED;

            return $out;
        }
        $supported = (string) ($compat['supported_versions'] ?? '*');
        $out['mode'] = SemVer::satisfies($version, $supported) ? 'legacy' : 'unavailable';
        $out['legacy_fallbacks'] = $out['mode'] === 'legacy';
        if ($out['legacy_fallbacks']) {
            $out['capabilities'] = array_fill_keys(array_keys($wanted), 'legacy');
        }
        $policy = $own->policy('unknown_peer_version');
        $out['compatibility'] = $policy === 'incompatible' ? self::INCOMPATIBLE : ($policy === 'degraded' ? self::DEGRADED : self::UNKNOWN);
        $out['reasons'][] = "peer version $version was not tested with this RefreshGlobal (no interop.json)";

        return $out;
    }

    protected static function inFallback(array $compat, $version)
    {
        $range = (string) ($compat['fallback']['supported_versions'] ?? '');

        return $range !== '' && SemVer::satisfies($version, $range);
    }

    /**
     * Facts of this installation for a peer, from FreeScout's module list (app/Module.php through \Module), then
     * evaluate(). Never throws: a peer that can not be read counts as absent.
     */
    public static function fromInstallation($peer = 'refresh')
    {
        $own = Manifest::own();
        $facts = ['installed' => false, 'active' => false, 'version' => ''];
        $peerManifest = null;
        try {
            $module = \Module::findByAlias($peer);
            if ($module) {
                $facts['installed'] = true;
                $facts['active'] = (bool) \App\Module::isActive($peer);
                $facts['version'] = (string) $module->get('version');
                $peerManifest = Manifest::load($module->getPath());
            }
        } catch (\Throwable $e) {
            // unreadable peer: absent
        }
        if (!$own) {
            return ['peer' => $peer, 'mode' => 'unavailable', 'compatibility' => self::UNKNOWN, 'capabilities' => [],
                'reasons' => ['interop.json of RefreshGlobal missing'], 'installed' => $facts['installed'], 'active' => $facts['active'],
                'version' => $facts['version'], 'manifest' => $peerManifest !== null, 'protocol' => '', 'legacy_fallbacks' => true];
        }

        return self::evaluate($own, $peer, $facts, $peerManifest);
    }
}
