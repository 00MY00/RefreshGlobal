<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/**
 * RG-CSS-01: Refresh's stylesheets exist. Refresh loads them itself on every page ("stylesheets" filter,
 * Modules/Refresh/Providers/RefreshServiceProvider.php:133-139); RefreshGlobal never loads them again.
 */
class RefreshCssCheck extends Check
{
    public function run()
    {
        return [$this->checker->filesResult('RG-CSS-01', 'css', $this->checker->integration('refresh.css'))];
    }
}
