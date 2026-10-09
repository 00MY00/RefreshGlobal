<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-CORE-xx: every FreeScout class, method and constant the module calls exists. Blocking. */
class CoreCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('core') as $code => $def) {
            $missing = [];
            $items = [];
            if (!empty($def['class'])) {
                $items[] = $def['class'];
                if (!class_exists($def['class'])) {
                    $missing[] = $def['class'];
                } else {
                    foreach ((array) ($def['methods'] ?? []) as $method) {
                        if (!method_exists($def['class'], $method)) {
                            $missing[] = $def['class'].'::'.$method.'()';
                        }
                    }
                }
                $expected = $def['class'].(empty($def['methods']) ? '' : '::'.implode('(), ', $def['methods']).'()');
            } else {
                $expected = implode(', ', (array) ($def['constants'] ?? []));
            }
            foreach ((array) ($def['constants'] ?? []) as $constant) {
                if (!defined($constant)) {
                    $missing[] = $constant;
                }
            }
            $results[] = $this->result($code, 'core', self::BLOCKING, !$missing, $expected, $missing ? 'missing: '.implode(', ', $missing) : '');
        }

        return $results;
    }
}
