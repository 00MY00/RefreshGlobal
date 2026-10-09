<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-REF-01: the Refresh module is in Modules/ and active (modules table, app/Module.php:44). */
class RefreshPresentCheck extends Check
{
    public function run()
    {
        $path = $this->checker->refreshPath();
        $active = $this->checker->refreshActive();
        if (!$path) {
            $details = 'Modules/Refresh: not found';
        } elseif (!$active) {
            $details = 'Modules/Refresh: present, not active';
        } else {
            $details = 'Modules/Refresh: active';
        }

        return [$this->result('RG-REF-01', 'ref_present', self::DEGRADED, $path && $active, 'Modules/Refresh (alias "refresh"), active', $details)];
    }
}
