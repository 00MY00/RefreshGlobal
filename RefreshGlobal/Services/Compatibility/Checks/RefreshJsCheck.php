<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/**
 * RG-JS-01: Refresh's scripts exist. Refresh loads them itself ("javascripts" filter,
 * Modules/Refresh/Providers/RefreshServiceProvider.php:131-138) and builds its shell from its provider script.
 */
class RefreshJsCheck extends Check
{
    public function run()
    {
        return [$this->checker->filesResult('RG-JS-01', 'js', $this->checker->integration('refresh.js'))];
    }
}
