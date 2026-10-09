<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-DB-xx: every table and column read by the module exists (Schema::hasTable / hasColumn). */
class DatabaseCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('database') as $code => $def) {
            $expected = $def['table'].'('.implode(', ', $def['columns']).')';
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
            $family = $def['table'] === 'refreshglobal_saved_views' ? 'db_own' : 'db';
            $results[] = $this->result($code, $family, $def['severity'], !$missing, $expected, $missing ? 'missing: '.implode(', ', $missing) : '');
        }

        return $results;
    }
}
