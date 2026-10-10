<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/**
 * RG-ROUTE-xx: every named route the module links to exists (Route::has()).
 * An entry with 'module' => alias belongs to an optional module (e.g. SyncNow): not applicable when it is not active.
 */
class RouteCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('routes') as $code => $def) {
            $family = $def['family'] ?? 'route';
            if (!empty($def['module']) && !self::moduleActive($def['module'])) {
                $results[] = $this->result($code, $family, $def['severity'], null, $def['route']);
                continue;
            }
            try {
                $ok = \Route::has($def['route']);
            } catch (\Exception $e) {
                $ok = false;
            }
            $results[] = $this->result($code, $family, $def['severity'], $ok, $def['route'], $ok ? '' : 'Route::has() = false');
        }

        return $results;
    }

    public static function moduleActive($alias)
    {
        try {
            return \App\Module::isActive($alias);
        } catch (\Exception $e) {
            return false;
        }
    }
}
