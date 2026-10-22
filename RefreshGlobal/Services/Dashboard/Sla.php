<?php

namespace Modules\RefreshGlobal\Services\Dashboard;

/**
 * Service-level targets of the dashboard of all mailboxes (tiles "Overdue" / "Due: today", KPI "Resolved within SLA").
 *
 * One source, chosen in this order (docs/INTEROPERABILITY.md: RefreshGlobal works without Refresh, and never relies on
 * Refresh's internal classes when a public channel exists):
 *   1. the public server filter fsinterop.sla.targets (schema sla-targets 1.0) — what another module (Refresh from its
 *      contract version) declares; agreed with Refresh's maintainer, to be answered by Refresh 1.9.0;
 *   2. LEGACY, until Refresh answers the filter: Refresh's settings class (RG-CORE-11,
 *      Modules/Refresh/Services/Settings.php:26,32), internal to Refresh and only read while Refresh is active;
 *   3. RefreshGlobal's own settings (Manage › Settings › RefreshGlobal), defaults 24 h / 72 h (Refresh's defaults).
 */
class Sla
{
    const FIRST_RESPONSE = 'refreshglobal.sla_first_response';
    const RESOLUTION = 'refreshglobal.sla_resolution';

    const DEFAULT_FIRST_RESPONSE_HOURS = 24;
    const DEFAULT_RESOLUTION_HOURS = 72;
    const MAX_HOURS = 8760;

    /** Hours to the first agent reply. */
    public static function firstResponseHours()
    {
        return self::targets()['first_response_hours'];
    }

    /** Hours to resolution. */
    public static function resolutionHours()
    {
        return self::targets()['resolution_hours'];
    }

    /** Where the targets come from: "hook:<module>", "refresh-legacy" or "refreshglobal" (settings page, diagnostics). */
    public static function source()
    {
        return self::targets()['source'];
    }

    /**
     * Both targets and their source, from the first source that gives valid values.
     *
     * @return array ['first_response_hours' => int, 'resolution_hours' => int, 'source' => string]
     */
    public static function targets()
    {
        $fromHook = self::fromHook();
        if ($fromHook) {
            return $fromHook;
        }
        $first = self::fromRefreshLegacy('firstResponseHours');
        $resolution = self::fromRefreshLegacy('resolutionHours');
        if ($first !== null && $resolution !== null) {
            return ['first_response_hours' => $first, 'resolution_hours' => $resolution, 'source' => 'refresh-legacy'];
        }

        return [
            'first_response_hours' => self::own(self::FIRST_RESPONSE, self::DEFAULT_FIRST_RESPONSE_HOURS),
            'resolution_hours'     => self::own(self::RESOLUTION, self::DEFAULT_RESOLUTION_HOURS),
            'source'               => 'refreshglobal',
        ];
    }

    /** RefreshGlobal's own value (settings page): 1 … MAX_HOURS, $default when not set. */
    public static function own($key, $default)
    {
        try {
            // not FreeScout's per-request option cache: it keeps "not set" even after the value is saved
            $hours = (int) \App\Option::get($key, $default, true, false);
        } catch (\Throwable $e) {
            $hours = $default;
        }

        return $hours >= 1 ? min($hours, self::MAX_HOURS) : $default;
    }

    /**
     * Targets declared through fsinterop.sla.targets, or null when no module answers with valid values. Context given
     * to the listeners: ['schema' => '1.0', 'scope' => 'global-mailboxes', 'user_id' => int|null].
     */
    protected static function fromHook()
    {
        try {
            $user = auth()->user();
            $answer = \Eventy::filter('fsinterop.sla.targets', null, ['schema' => '1.0', 'scope' => 'global-mailboxes', 'user_id' => $user ? (int) $user->id : null]);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.sla.targets: '.$e->getMessage());

            return null;
        }
        if (!is_array($answer)) {
            return null;
        }
        $first = (int) ($answer['first_response_hours'] ?? 0);
        $resolution = (int) ($answer['resolution_hours'] ?? 0);
        if ($first < 1 || $resolution < 1) {
            return null;
        }

        return [
            'first_response_hours' => min($first, self::MAX_HOURS),
            'resolution_hours'     => min($resolution, self::MAX_HOURS),
            'source'               => 'hook:'.preg_replace('/[^a-z0-9-]/', '', (string) ($answer['source_module'] ?? 'unknown')),
        ];
    }

    /** LEGACY: Refresh's value through its settings class, or null when Refresh is not active / the method is gone. */
    protected static function fromRefreshLegacy($method)
    {
        $class = 'Modules\Refresh\Services\Settings';
        try {
            if (!\App\Module::isActive('refresh') || !method_exists($class, $method)) {
                return null;
            }
            $hours = (int) call_user_func([$class, $method]);

            return $hours >= 1 ? $hours : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
