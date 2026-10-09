<?php

namespace Modules\RefreshGlobal\Services\Compatibility;

/**
 * Texts of a check result, in the mandatory format:
 *
 *   [RG-CSS-01] Refresh's stylesheet cannot be found.
 *   Expected: Modules/Refresh/Public/css/refresh.css (Refresh 1.4.x)
 *   Effect: the "All mailboxes" page is shown with FreeScout's standard look.
 *   Action: check the installed Refresh version, then run php artisan refreshglobal:check.
 *   In the meantime: [Open my mailboxes]
 */
class Messages
{
    public static function key(array $r, $part)
    {
        return 'refreshglobal::compat.'.$r['family'].'.'.$part;
    }

    protected static function text(array $r, $part, $locale = null)
    {
        $params = (array) ($r['params'] ?? []);
        // severity-specific text first (e.g. effect_blocking), then the generic one
        foreach ([$part.'_'.$r['severity'], $part] as $p) {
            $key = self::key($r, $p);
            $text = trans($key, $params, $locale);
            if ($text !== $key) {
                return $text;
            }
        }

        return '';
    }

    public static function title(array $r, $locale = null)
    {
        return self::text($r, 'label', $locale);
    }

    /** What is verified (diagnostic table). */
    public static function check(array $r, $locale = null)
    {
        return self::text($r, 'check', $locale);
    }

    public static function effect(array $r, $locale = null)
    {
        return self::text($r, 'effect', $locale);
    }

    public static function action(array $r, $locale = null)
    {
        return self::text($r, 'action', $locale);
    }

    /** Full plain-text block (CLI, logs). */
    public static function block(array $r, $locale = null)
    {
        $lines = ['['.$r['code'].'] '.self::title($r, $locale)];
        $lines[] = trans('refreshglobal::messages.expected', [], $locale).' '.$r['expected'];
        if (!empty($r['details'])) {
            $lines[] = trans('refreshglobal::messages.found', [], $locale).' '.$r['details'];
        }
        $lines[] = trans('refreshglobal::messages.effect', [], $locale).' '.self::effect($r, $locale);
        $lines[] = trans('refreshglobal::messages.action', [], $locale).' '.self::action($r, $locale);
        $lines[] = trans('refreshglobal::messages.meanwhile', [], $locale).' ['.trans('refreshglobal::messages.open_my_mailboxes', [], $locale).']';

        return implode("\n", $lines);
    }

    /** Failed results of a report, most severe first. */
    public static function failed(array $report, array $severities = ['blocking', 'degraded', 'warning'])
    {
        $out = [];
        foreach ($severities as $severity) {
            foreach ($report['results'] as $r) {
                if ($r['status'] === 'failed' && $r['severity'] === $severity) {
                    $out[] = $r;
                }
            }
        }

        return $out;
    }
}
