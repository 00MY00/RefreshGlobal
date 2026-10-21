<?php

namespace Modules\RefreshGlobal\Services\Dashboard;

/**
 * Service-level targets of the dashboard of all mailboxes (tiles "Overdue" / "Due: today", KPI "Resolved within SLA").
 *
 * One source, chosen in this order (docs/INTEROPERABILITY.md §2.1: RefreshGlobal works without Refresh):
 *   1. Refresh's own SLA settings, when Refresh is active and its public settings API is there (RG-CORE-11,
 *      Modules/Refresh/Services/Settings.php:26,32) — both modules then count the same tickets as overdue;
 *   2. otherwise RefreshGlobal's settings (Manage › Settings › RefreshGlobal), defaults 24 h / 72 h (Refresh's defaults).
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
        $refresh = self::fromRefresh('firstResponseHours');

        return $refresh !== null ? $refresh : self::own(self::FIRST_RESPONSE, self::DEFAULT_FIRST_RESPONSE_HOURS);
    }

    /** Hours to resolution. */
    public static function resolutionHours()
    {
        $refresh = self::fromRefresh('resolutionHours');

        return $refresh !== null ? $refresh : self::own(self::RESOLUTION, self::DEFAULT_RESOLUTION_HOURS);
    }

    /** "refresh" when Refresh's settings apply, "refreshglobal" otherwise (settings page, diagnostics). */
    public static function source()
    {
        return self::fromRefresh('resolutionHours') !== null ? 'refresh' : 'refreshglobal';
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

    /** Refresh's value through its public static API, or null when Refresh is not active / the method is gone. */
    protected static function fromRefresh($method)
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
