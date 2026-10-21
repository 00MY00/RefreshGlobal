<?php

namespace Modules\RefreshGlobal\Services\Dashboard;

use App\Conversation;
use App\Thread;
use Carbon\Carbon;

/**
 * Figures of "Today's trends" on the dashboard of all mailboxes, computed by RefreshGlobal (no Refresh class needed):
 * tickets created per hour, resolved, received, average first response time and "Resolved within SLA", for today and
 * yesterday in FreeScout's time zone.
 *
 * Every figure starts from the same base query (GlobalDashboard::baseQuery: the user's allowed mailboxes, published,
 * not spam, "only assigned" right), passed as a factory so that each figure gets a fresh query.
 */
class Statistics
{
    /** @var callable returns a new base query (\Illuminate\Database\Eloquent\Builder) */
    protected $base;

    /** @var string time zone of the days and hours */
    protected $tz;

    public function __construct(callable $base, $tz = null)
    {
        $this->base = $base;
        $this->tz = $tz ?: config('app.timezone');
    }

    /**
     * Everything the trends card shows.
     *
     * @return array ['h_today' => int[24], 'h_yest' => int[24], 'now_h' => int,
     *                'stats' => ['resolved' | 'received' | 'frt' | 'sla' => [today, yesterday]]]
     */
    public function summary()
    {
        $today = Carbon::now($this->tz)->startOfDay();
        $yesterday = $today->copy()->subDay();

        return [
            'h_today' => $this->createdPerHour($today),
            'h_yest'  => $this->createdPerHour($yesterday),
            'now_h'   => (int) Carbon::now($this->tz)->format('G'),
            'stats'   => [
                'resolved' => [$this->resolved($today), $this->resolved($yesterday)],
                'received' => [$this->received($today), $this->received($yesterday)],
                'frt'      => [$this->firstResponseMinutes($today), $this->firstResponseMinutes($yesterday)],
                'sla'      => [$this->resolvedWithinSlaPercent($today), $this->resolvedWithinSlaPercent($yesterday)],
            ],
        ];
    }

    /** Tickets created during each hour of a day (local hours). */
    public function createdPerHour(Carbon $day)
    {
        $hours = array_fill(0, 24, 0);
        foreach ($this->query()->whereBetween('created_at', $this->range($day))->pluck('created_at') as $created) {
            $hours[(int) Carbon::parse($created)->setTimezone($this->tz)->format('G')]++;
        }

        return $hours;
    }

    /** Tickets created that day. */
    public function received(Carbon $day)
    {
        return $this->query()->whereBetween('created_at', $this->range($day))->count();
    }

    /** Tickets closed that day (and still closed). */
    public function resolved(Carbon $day)
    {
        return $this->query()->where('status', Conversation::STATUS_CLOSED)->whereBetween('closed_at', $this->range($day))->count();
    }

    /**
     * Share (0-100) of the tickets closed that day that were closed before their due date, or null when none was
     * closed. Due date: the one set on the ticket by Refresh (conversations.meta.rf_due, read as plain data, if any),
     * otherwise creation + the resolution target (Sla).
     */
    public function resolvedWithinSlaPercent(Carbon $day)
    {
        $rows = $this->query()->where('status', Conversation::STATUS_CLOSED)->whereBetween('closed_at', $this->range($day))
            ->get(['created_at', 'closed_at', 'meta']);
        if (!count($rows)) {
            return null;
        }
        $hours = Sla::resolutionHours();
        $inTime = 0;
        foreach ($rows as $row) {
            if (Carbon::parse($row->closed_at)->lte($this->dueDate($row, $hours))) {
                $inTime++;
            }
        }

        return (int) round($inTime * 100 / count($rows));
    }

    /**
     * Average time (minutes) between the creation of a ticket and its first agent reply, over the tickets whose first
     * agent reply was sent that day; null when there is none.
     */
    public function firstResponseMinutes(Carbon $day)
    {
        $range = $this->range($day);
        $firstReplies = Thread::select('conversation_id', \DB::raw('MIN(created_at) AS r_at'))
            ->where('type', Thread::TYPE_MESSAGE)->where('state', Thread::STATE_PUBLISHED)->whereNotNull('created_by_user_id')
            ->whereIn('conversation_id', $this->query()->select('id')->getQuery())
            ->groupBy('conversation_id')
            ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$range[0]->toDateTimeString(), $range[1]->toDateTimeString()])
            ->get();
        $repliedAt = [];
        foreach ($firstReplies as $row) {
            $repliedAt[$row->conversation_id] = Carbon::parse($row->r_at);
        }
        if (!$repliedAt) {
            return null;
        }
        $sum = 0;
        $n = 0;
        foreach (Conversation::whereIn('id', array_keys($repliedAt))->get(['id', 'created_at']) as $conversation) {
            $sum += max(0, $repliedAt[$conversation->id]->diffInMinutes(Carbon::parse($conversation->created_at)));
            $n++;
        }

        return $n ? (int) round($sum / $n) : null;
    }

    /** A fresh base query. */
    protected function query()
    {
        return call_user_func($this->base);
    }

    /** [start, end] of a local day, in UTC (dates are stored in UTC). */
    protected function range(Carbon $day)
    {
        return [$day->copy()->setTimezone('UTC'), $day->copy()->endOfDay()->setTimezone('UTC')];
    }

    /** Due date of a closed ticket (see resolvedWithinSlaPercent()). */
    protected function dueDate($row, $hours)
    {
        $meta = is_array($row->meta) ? $row->meta : (json_decode((string) $row->meta, true) ?: []);

        return !empty($meta['rf_due']) ? Carbon::parse($meta['rf_due']) : Carbon::parse($row->created_at)->addHours($hours);
    }
}
