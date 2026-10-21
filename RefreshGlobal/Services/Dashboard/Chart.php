<?php

namespace Modules\RefreshGlobal\Services\Dashboard;

/**
 * Presentation helpers of the dashboard of all mailboxes, owned by RefreshGlobal (no Refresh class needed): the
 * "Today's trends" SVG chart, the change against yesterday and durations.
 *
 * The markup keeps the class names of Refresh's dashboard (rf-chart, rf-chart-today…), so Refresh's stylesheet styles it
 * when Refresh is installed; Public/css/refreshglobal-dashboard.css styles it otherwise.
 */
class Chart
{
    /** Geometry of the chart (viewBox units; the SVG scales to its container). */
    const WIDTH = 900;
    const HEIGHT = 240;
    const PAD_LEFT = 36;
    const PAD_RIGHT = 12;
    const PAD_TOP = 16;
    const PAD_BOTTOM = 30;

    /**
     * Tickets created per hour: today (up to the current hour) and yesterday (all day).
     *
     * @param int[] $today     24 values
     * @param int[] $yesterday 24 values
     * @param int   $nowHour   current hour, 0-23
     * @return string SVG markup (numbers only, nothing to escape)
     */
    public static function hourly(array $today, array $yesterday, $nowHour)
    {
        $today = self::hours($today);
        $yesterday = self::hours($yesterday);
        $max = max(1, max($today), max($yesterday));
        $nowHour = max(0, min(23, (int) $nowHour));

        return '<svg class="rf-chart" viewBox="0 0 '.self::WIDTH.' '.self::HEIGHT.'" role="img">'
            .self::grid($max)
            .self::hourLabels()
            .self::series($yesterday, 'rf-chart-yest', 23, $max)
            .self::series($today, 'rf-chart-today', $nowHour, $max)
            .'</svg>';
    }

    /**
     * Change of a figure against yesterday: ['12,5%', 'up'|'down'], or ['', null] when it cannot be computed (no value,
     * or nothing yesterday).
     */
    public static function delta($today, $yesterday)
    {
        if ($today === null || $yesterday === null || (float) $yesterday == 0.0) {
            return ['', null];
        }
        $pct = round(abs($today - $yesterday) * 100 / $yesterday, 2);

        return [str_replace('.', ',', (string) $pct).'%', $today >= $yesterday ? 'up' : 'down'];
    }

    /** Minutes as a short translated duration ("12m", "3h 5m", "2d 4h"), "--" when unknown. */
    public static function duration($minutes)
    {
        if ($minutes === null) {
            return '--';
        }
        $minutes = max(0, (int) $minutes);
        if ($minutes < 60) {
            return __('refreshglobal::messages.dash_dur_m', ['m' => $minutes]);
        }
        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return __('refreshglobal::messages.dash_dur_hm', ['h' => $hours, 'm' => $minutes % 60]);
        }

        return __('refreshglobal::messages.dash_dur_dh', ['d' => intdiv($hours, 24), 'h' => $hours % 24]);
    }

    /** 24 integer values (missing hours count 0). */
    protected static function hours(array $values)
    {
        $out = [];
        for ($i = 0; $i < 24; $i++) {
            $out[] = (int) ($values[$i] ?? 0);
        }

        return $out;
    }

    /** X coordinate of an hour. */
    protected static function x($hour)
    {
        $step = (self::WIDTH - self::PAD_LEFT - self::PAD_RIGHT) / 23;

        return round(self::PAD_LEFT + $hour * $step, 1);
    }

    /** Y coordinate of a value. */
    protected static function y($value, $max)
    {
        $height = self::HEIGHT - self::PAD_TOP - self::PAD_BOTTOM;

        return round(self::HEIGHT - self::PAD_BOTTOM - ($value / $max) * $height, 1);
    }

    /** Horizontal grid lines with their values (at most 5 lines). */
    protected static function grid($max)
    {
        $svg = '';
        $ticks = min(4, $max);
        for ($k = 0; $k <= $ticks; $k++) {
            $value = round($max * $k / max(1, $ticks));
            $y = self::y($value, $max);
            $svg .= '<line x1="'.self::PAD_LEFT.'" x2="'.(self::WIDTH - self::PAD_RIGHT).'" y1="'.$y.'" y2="'.$y.'" class="rf-chart-grid"/>'
                .'<text x="'.(self::PAD_LEFT - 10).'" y="'.($y + 4).'" class="rf-chart-lbl" text-anchor="end">'.$value.'</text>';
        }

        return $svg;
    }

    /** Hours 0-23 under the chart. */
    protected static function hourLabels()
    {
        $svg = '';
        for ($i = 0; $i < 24; $i++) {
            $svg .= '<text x="'.self::x($i).'" y="'.(self::HEIGHT - 8).'" class="rf-chart-lbl" text-anchor="middle">'.$i.'</text>';
        }

        return $svg;
    }

    /** One line of the chart with a dot and the value at every hour, up to $upto. */
    protected static function series(array $values, $class, $upto, $max)
    {
        $points = [];
        $dots = '';
        for ($i = 0; $i <= $upto; $i++) {
            $x = self::x($i);
            $y = self::y($values[$i], $max);
            $points[] = $x.','.$y;
            $dots .= '<circle cx="'.$x.'" cy="'.$y.'" r="3.5" class="'.$class.'-dot"/>'
                .'<text x="'.$x.'" y="'.($y - 8).'" class="rf-chart-val" text-anchor="middle">'.$values[$i].'</text>';
        }

        return '<polyline points="'.implode(' ', $points).'" class="'.$class.'"/>'.$dots;
    }
}
