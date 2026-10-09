<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/**
 * RG-HOOK-xx: every Eventy hook the module listens to is still fired. Eventy cannot list the hooks a file fires, so
 * the check reads the file that fires it (recorded during the audit) and looks for the call.
 */
class HookCheck extends Check
{
    public function run()
    {
        $results = [];
        foreach ((array) $this->checker->integration('hooks') as $code => $def) {
            if (!empty($def['refresh'])) {
                if (!$this->checker->refreshUsable()) {
                    $results[] = $this->result($code, 'hook', $def['severity'], null, $def['hook'], '', ['file' => $def['file']]);
                    continue;
                }
                $path = $this->checker->refreshPath().'/'.$def['file'];
                $shown = 'Modules/Refresh/'.$def['file'];
            } else {
                $path = base_path($def['file']);
                $shown = $def['file'];
            }
            $content = is_file($path) ? @file_get_contents($path) : false;
            $ok = is_string($content) && strpos($content, $def['needle']) !== false;
            $details = $content === false ? $shown.': file not found' : ($ok ? '' : $shown.': call not found');
            $results[] = $this->result($code, 'hook', $def['severity'], $ok, $def['hook'], $details, ['file' => $shown]);
        }

        return $results;
    }
}
