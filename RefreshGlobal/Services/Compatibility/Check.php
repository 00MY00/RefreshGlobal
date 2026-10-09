<?php

namespace Modules\RefreshGlobal\Services\Compatibility;

/**
 * One compatibility check. Each check has a unique code (RG-xxx), a label, what it verifies (expected), a severity,
 * the concrete effect when it fails, the recommended action and the fallback. Texts live in
 * Resources/lang/<locale>/compat.php under the check's family.
 */
abstract class Check
{
    const WARNING = 'warning';
    const DEGRADED = 'degraded';
    const BLOCKING = 'blocking';

    /** @var CompatibilityChecker */
    protected $checker;

    public function __construct(CompatibilityChecker $checker)
    {
        $this->checker = $checker;
    }

    /**
     * Runs the check.
     *
     * @return array[] one or more results (see result())
     */
    abstract public function run();

    /**
     * @param string      $code     RG-xxx
     * @param string      $family   key in compat.php (texts)
     * @param string      $severity warning | degraded | blocking
     * @param bool|null   $ok       null = skipped (not applicable)
     * @param string      $expected what was looked for (file, class, route…)
     * @param string      $details  what was found instead
     * @param array       $params   replacements for the texts (:item…)
     */
    protected function result($code, $family, $severity, $ok, $expected, $details = '', array $params = [])
    {
        return [
            'code'     => $code,
            'family'   => $family,
            'severity' => $severity,
            'status'   => $ok === null ? 'skipped' : ($ok ? 'ok' : 'failed'),
            'expected' => (string) $expected,
            'details'  => (string) $details,
            'params'   => $params + ['item' => (string) $expected],
        ];
    }
}
