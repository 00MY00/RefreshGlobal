<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-VIEW-xx: every Blade view the module's pages extend or include exists (view()->exists()). */
class ViewCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('views') as $code => $def) {
            if (!empty($def['refresh']) && !$this->checker->refreshUsable()) {
                $results[] = $this->result($code, 'view', $def['severity'], null, $def['view']);
                continue;
            }
            try {
                $ok = view()->exists($def['view']);
            } catch (\Exception $e) {
                $ok = false;
            }
            $results[] = $this->result($code, 'view', $def['severity'], $ok, $def['view'], $ok ? '' : 'view()->exists() = false');
        }

        return $results;
    }
}
