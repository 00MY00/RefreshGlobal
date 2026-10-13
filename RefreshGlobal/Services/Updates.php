<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Who updates RefreshGlobal (1.9.0). The module has no updater of its own any more: its module.json declares
 * FreeScout's standard keys (latestVersionUrl = version.txt of the latest release, latestVersionZipUrl =
 * RefreshGlobal.zip, SHA256SUMS next to them, health command refreshglobal:health), so
 *  - with the UpdateGuard module (https://github.com/00MY00/UpdateGuard) installed and active: UpdateGuard updates it
 *    (SHA-256, staging, backup, health check, automatic rollback, optional automatic updates);
 *  - without it: FreeScout's own "Update" button of Manage › Modules (app/Http/Controllers/ModulesController.php:150-195,
 *    app/Module.php:415-519), with no backup nor rollback — UpdateGuard is recommended.
 */
class Updates
{
    const UPDATEGUARD_ALIAS = 'updateguard';
    const UPDATEGUARD_URL = 'https://github.com/00MY00/UpdateGuard';

    /** UpdateGuard installed, active, with its page of a module. */
    public static function updateGuard()
    {
        try {
            return \App\Module::isActive(self::UPDATEGUARD_ALIAS) && \Route::has('updateguard.module');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * What the settings and diagnostic pages show.
     *
     * @return array ['version', 'manager' (updateguard | freescout), 'url' (page where to update, or ''), 'recommend' (url of UpdateGuard or '')]
     */
    public static function info()
    {
        $version = '';
        try {
            $json = json_decode((string) @file_get_contents(__DIR__.'/../module.json'), true);
            $version = is_array($json) ? (string) ($json['version'] ?? '') : '';
        } catch (\Throwable $e) {
            $version = '';
        }
        if (self::updateGuard()) {
            return ['version' => $version, 'manager' => 'updateguard', 'url' => route('updateguard.module', ['name' => 'RefreshGlobal']), 'recommend' => ''];
        }
        $url = '';
        try {
            $url = \Route::has('modules') ? route('modules') : '';
        } catch (\Throwable $e) {
            $url = '';
        }

        return ['version' => $version, 'manager' => 'freescout', 'url' => $url, 'recommend' => self::UPDATEGUARD_URL];
    }
}
