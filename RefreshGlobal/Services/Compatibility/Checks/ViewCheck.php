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
            // texts of compat.php: "view", or the family of a secondary feature (view_lang)
            $family = $def['family'] ?? 'view';
            if (!empty($def['refresh']) && !$this->checker->refreshUsable()) {
                $results[] = $this->result($code, $family, $def['severity'], null, $def['view']);
                continue;
            }
            try {
                $ok = view()->exists($def['view']);
            } catch (\Exception $e) {
                $ok = false;
            }
            $results[] = $this->result($code, $family, $def['severity'], $ok, $def['view'], $ok ? '' : 'view()->exists() = false');
        }

        return $results;
    }
}
