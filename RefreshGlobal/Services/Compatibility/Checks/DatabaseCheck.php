<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/**
 * RG-DB-xx: every table and column read by the module exists (Schema::hasTable / hasColumn).
 * An entry with 'module' => alias belongs to an optional module (e.g. Workflow Packs): not applicable when it is not active.
 */
class DatabaseCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('database') as $code => $def) {
            $expected = $def['table'].'('.implode(', ', $def['columns']).')';
            $family = $def['family'] ?? ($def['table'] === 'refreshglobal_saved_views' ? 'db_own' : 'db');
            if (!empty($def['module']) && !RouteCheck::moduleActive($def['module'])) {
                $results[] = $this->result($code, $family, $def['severity'], null, $expected);
                continue;
            }
            $missing = [];
            try {
                if (!\Schema::hasTable($def['table'])) {
                    $missing[] = 'table '.$def['table'];
                } else {
                    foreach ($def['columns'] as $column) {
                        if (!\Schema::hasColumn($def['table'], $column)) {
                            $missing[] = $def['table'].'.'.$column;
                        }
                    }
                }
            } catch (\Exception $e) {
                $missing[] = 'database error: '.$e->getMessage();
            }
            $results[] = $this->result($code, $family, $def['severity'], !$missing, $expected, $missing ? 'missing: '.implode(', ', $missing) : '');
        }

        return $results;
    }
}
