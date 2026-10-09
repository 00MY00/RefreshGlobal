<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-ENV-01: FreeScout version (config/app.php 'version') inside the tested range of COMPATIBILITY.md. */
class FreeScoutVersionCheck extends Check
{
    public function run()
    {
        $range = $this->checker->integration('versions.freescout');
        $version = (string) $this->checker->freescoutVersion();
        $ok = $version !== ''
            && version_compare($version, $range['min'], '>=')
            && version_compare($version, $range['max_exclusive'], '<');
        $expected = 'FreeScout >= '.$range['min'].' < '.$range['max_exclusive'].' (tested: '.$range['tested'].')';

        return [$this->result('RG-ENV-01', 'env', self::WARNING, $ok, $expected, 'FreeScout '.($version ?: '?'))];
    }
}
