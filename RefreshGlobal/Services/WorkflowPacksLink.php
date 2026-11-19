<?php

namespace Modules\RefreshGlobal\Services;

/**
 * The ONLY place where RefreshGlobal talks to the flows API of Workflow Packs (3.20.0 and later, its docs/api-flux.md).
 * Everything goes through Eventy inside FreeScout: no code of Workflow Packs is called, no table of it is read.
 *
 * The flow statistics and the temporary flows exist only when available() is true:
 *   Workflow Packs active (App\Module::isActive), its filter "workflowpacks.info" answers with api >= 1, version
 *   3.20.0 or later, and its internal engine is on.
 * Otherwise nothing is shown, no error, no request to Workflow Packs: RefreshGlobal works exactly as without it.
 *
 * Each read is wrapped: an error or an unexpected answer gives the empty default, never an exception.
 */
class WorkflowPacksLink
{
    const ALIAS = 'workflowpacks';
    const MIN_VERSION = '3.20.0';
    const API = 1;
    /** tickets per call of workflowpacks.ticket_changes (its limit) */
    const MAX_IDS = 1000;
    /** tickets of a temporary flow (its limit) */
    const MAX_TEMP = 5000;
    const ORIGIN = 'refreshglobal';

    /** Tests: the module is considered active (its answers are simulated with \Eventy::addFilter). */
    public static $assumeActive = false;

    /** @var array|null answer of workflowpacks.info, read once per request */
    protected static $info = null;
    protected static $available = null;

    /**
     * Flows API usable? Read once per request. Only call it once every module has booted (the provider asks from
     * app()->booted()): Workflow Packs registers its filters in its own boot(), after RefreshGlobal's.
     */
    public static function available()
    {
        if (self::$available === null) {
            self::$available = false;
            try {
                if (self::$assumeActive || \App\Module::isActive(self::ALIAS)) {
                    $info = self::info();
                    self::$available = (int) ($info['api'] ?? 0) >= self::API
                        && !empty($info['engine'])
                        && is_string($info['version'] ?? null)
                        && version_compare($info['version'], self::MIN_VERSION, '>=');
                }
            } catch (\Throwable $e) {
                self::$available = false;
            }
        }

        return self::$available;
    }

    /** Answer of workflowpacks.info ([] without Workflow Packs or before 3.20). */
    public static function info()
    {
        if (self::$info === null) {
            self::$info = [];
            try {
                $info = \Eventy::filter('workflowpacks.info', []);
                self::$info = is_array($info) ? $info : [];
            } catch (\Throwable $e) {
                self::$info = [];
            }
        }

        return self::$info;
    }

    /** Forget what was read (tests, long-running workers). */
    public static function reset()
    {
        self::$info = null;
        self::$available = null;
    }

    /**
     * Changes made by the flows on these tickets (at most MAX_IDS per call), [conversation id => [change, …]].
     */
    public static function ticketChanges(array $ids, array $options = [])
    {
        if (!self::available() || !$ids) {
            return [];
        }
        $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, self::MAX_IDS);

        return self::filter('workflowpacks.ticket_changes', [$ids, $options]);
    }

    /** Statistics per flow over a period (used for "flow deleted since"), ['flows' => [...]] or []. */
    public static function flowStats(array $options)
    {
        return self::available() ? self::filter('workflowpacks.flow_stats', [$options]) : [];
    }

    /** Tickets no flow has acted on: ['ok', 'total', 'ids', 'truncated'] or []. */
    public static function untreated(array $options)
    {
        return self::available() ? self::filter('workflowpacks.untreated', [$options]) : [];
    }

    /** A temporary flow for a list of tickets (administrators only, checked again by Workflow Packs). */
    public static function tempFlow(array $input)
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'engine_off'];
        }
        $out = self::filter('workflowpacks.temp_flow', [$input]);

        return $out ?: ['ok' => false, 'error' => 'internal'];
    }

    /** Where a temporary flow is: draft | running | paused | stopped | waiting | done | deleted (or [] when unknown). */
    public static function tempFlowStatus($flowId)
    {
        return self::available() ? self::filter('workflowpacks.temp_flow_status', [(int) $flowId]) : [];
    }

    /** An address given by Workflow Packs, kept only when it points to this FreeScout (never elsewhere). */
    public static function safeUrl($url)
    {
        if (!is_string($url) || $url === '' || preg_match('/[\s\\\\]/', $url)) {
            return '';
        }
        $base = rtrim(url('/'), '/');

        return $url === $base || strpos($url, $base.'/') === 0 || strpos($url, $base.'?') === 0 ? $url : '';
    }

    protected static function filter($name, array $args)
    {
        try {
            $out = \Eventy::filter($name, [], ...$args);

            return is_array($out) ? $out : [];
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [flows] '.$name.': '.get_class($e));

            return [];
        }
    }
}
