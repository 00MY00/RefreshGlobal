<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-ROUTE-xx: every named route the module links to exists (Route::has()). */
class RouteCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('routes') as $code => $def) {
            try {
                $ok = \Route::has($def['route']);
            } catch (\Exception $e) {
                $ok = false;
            }
            $results[] = $this->result($code, 'route', $def['severity'], $ok, $def['route'], $ok ? '' : 'Route::has() = false');
        }

        return $results;
    }
}
