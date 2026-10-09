<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/** RG-CORE-xx: every FreeScout (or Refresh) class, method and constant the module calls exists. Blocking by default. */
class CoreCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('core') as $code => $def) {
            // texts of compat.php: "core", or the family of a secondary feature (core_refresh, core_delete)
            $family = $def['family'] ?? 'core';
            // classes of Refresh: not applicable without Refresh
            if (!empty($def['refresh']) && !$this->checker->refreshUsable()) {
                $results[] = $this->result($code, $family, $def['severity'] ?? self::BLOCKING, null, $def['class'] ?? implode(', ', (array) ($def['classes'] ?? [])));
                continue;
            }
            $missing = [];
            $items = [];
            if (!empty($def['classes'])) {
                // several classes (vendor / PHP extensions), no method list
                foreach ((array) $def['classes'] as $class) {
                    if (!class_exists($class)) {
                        $missing[] = $class;
                    }
                }
                $expected = implode(', ', (array) $def['classes']);
            } elseif (!empty($def['class'])) {
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
            $severity = $def['severity'] ?? self::BLOCKING;
            $results[] = $this->result($code, $family, $severity, !$missing, $expected, $missing ? 'missing: '.implode(', ', $missing) : '');
        }

        return $results;
    }
}
