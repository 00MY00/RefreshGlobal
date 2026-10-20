<?php

namespace Modules\RefreshGlobal\Services\Interop;

/**
 * Semantic versions and version ranges of the FreeScout Interop contract (docs/INTEROPERABILITY.md), with exactly the
 * rules of the browser side (Public/js/fs-interop.js, FreeScoutInterop.semver):
 *   "1.2" = "1.2.0"; comparators separated by spaces: ">=1.0 <2.0", "=1.2.0", "1.2" (equal), "^1.2" (same major,
 *   >= 1.2), "*" or "" (any). Unknown syntax never matches: an unknown range never means "supported".
 * Plain PHP (no Laravel), also used by tools/validate-interop.php.
 */
class SemVer
{
    /** "v1.2.3-beta" -> [1, 2, 3]; null when it is not a version. */
    public static function parse($version)
    {
        if (!is_scalar($version) || !preg_match('/^\s*v?(\d+)(?:\.(\d+))?(?:\.(\d+))?/', (string) $version, $m)) {
            return null;
        }

        return [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)];
    }

    public static function isVersion($version)
    {
        return self::parse($version) !== null;
    }

    /** -1, 0 or 1; an unparsable side compares as lower (-1). */
    public static function compare($a, $b)
    {
        $x = self::parse($a);
        $y = self::parse($b);
        if ($x === null || $y === null) {
            return -1;
        }
        for ($i = 0; $i < 3; $i++) {
            if ($x[$i] !== $y[$i]) {
                return $x[$i] < $y[$i] ? -1 : 1;
            }
        }

        return 0;
    }

    /** Is the range written in the supported syntax? (the validator refuses the others) */
    public static function isRange($range)
    {
        if (!is_string($range)) {
            return false;
        }
        $range = trim($range);
        if ($range === '' || $range === '*') {
            return true;
        }
        if ($range[0] === '^') {
            return self::isVersion(substr($range, 1));
        }
        foreach (preg_split('/\s+/', $range) as $part) {
            if (!preg_match('/^(>=|<=|>|<|=)?(.+)$/', $part, $m) || !self::isVersion($m[2])) {
                return false;
            }
        }

        return true;
    }

    /** Does $version satisfy $range? */
    public static function satisfies($version, $range)
    {
        if (!self::isVersion($version) || !self::isRange((string) $range)) {
            return false;
        }
        $range = trim((string) $range);
        if ($range === '' || $range === '*') {
            return true;
        }
        if ($range[0] === '^') {
            $base = substr($range, 1);

            return self::compare($version, $base) >= 0 && self::parse($version)[0] === self::parse($base)[0];
        }
        foreach (preg_split('/\s+/', $range) as $part) {
            preg_match('/^(>=|<=|>|<|=)?(.+)$/', $part, $m);
            $c = self::compare($version, $m[2]);
            $op = $m[1] !== '' ? $m[1] : '=';
            $ok = ($op === '>=' && $c >= 0) || ($op === '<=' && $c <= 0) || ($op === '>' && $c > 0) || ($op === '<' && $c < 0) || ($op === '=' && $c === 0);
            if (!$ok) {
                return false;
            }
        }

        return true;
    }
}
