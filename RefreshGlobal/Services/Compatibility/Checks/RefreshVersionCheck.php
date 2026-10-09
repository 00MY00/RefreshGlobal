<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-REF-02: Refresh version (its module.json) inside the tested range. Skipped when Refresh is missing. */
class RefreshVersionCheck extends Check
{
    public function run()
    {
        $range = $this->checker->integration('versions.refresh');
        $expected = 'Refresh >= '.$range['min'].' < '.$range['max_exclusive'].' (tested: '.$range['tested'].')';
        if (!$this->checker->refreshUsable()) {
            return [$this->result('RG-REF-02', 'ref_version', self::WARNING, null, $expected)];
        }
        $version = (string) $this->checker->refreshVersion();
        $ok = $version !== ''
            && version_compare($version, $range['min'], '>=')
            && version_compare($version, $range['max_exclusive'], '<');

        return [$this->result('RG-REF-02', 'ref_version', self::WARNING, $ok, $expected, 'Refresh '.($version ?: '?'))];
    }
}
