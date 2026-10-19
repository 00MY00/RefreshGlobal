<?php

namespace Modules\RefreshGlobal\Services;

use App\Conversation;
use App\Thread;
use Carbon\Carbon;

/**
 * Refresh's dashboard ("My dashboard") for ALL the user's mailboxes.
 *
 * Refresh computes its dashboard for the user's first mailbox only ($user->mailboxesCanView()->first(),
 * Modules/Refresh/Providers/RefreshServiceProvider.php:505-537). This service computes the same figures over every
 * mailbox the user can see (MailboxAccess) and renders them with the same markup as Refresh's view
 * (Modules/Refresh/Resources/views/dashboard.blade.php), so Refresh's stylesheet gives the same look.
 *
 * Reused from Refresh (public static API, checked by refreshglobal:check):
 *  - Views::query($mailbox_id, $view, $user): its view definitions (SLA rules), for the tiles;
 *  - Dashboard::chart() / delta() / duration(): the chart and number formats;
 *  - Settings::resolutionHours(): the SLA used by "Resolved within SLA".
 * The trend figures follow the formulas of Modules/Refresh/Services/Dashboard.php:16-123, over several mailboxes.
 */
class GlobalDashboard
{
    const TILES = ['unresolved', 'overdue', 'due-today', 'open', 'pending', 'unassigned'];

    /** Refresh views the "All mailboxes" page can filter on (parameter rv). */
    const VIEWS = ['unresolved', 'overdue', 'due-today', 'open', 'pending', 'unassigned', 'new'];

    public static function refreshApi()
    {
        return class_exists('Modules\Refresh\Services\Views') && method_exists('Modules\Refresh\Services\Views', 'query')
            && class_exists('Modules\Refresh\Services\Dashboard') && method_exists('Modules\Refresh\Services\Dashboard', 'chart')
            && class_exists('Modules\Refresh\Services\Settings') && method_exists('Modules\Refresh\Services\Settings', 'resolutionHours');
    }

    /** Name Refresh gives to one of its views (Views::labels(), Modules/Refresh/Services/Views.php:106). */
    public static function viewLabel($view)
    {
        if (method_exists('Modules\Refresh\Services\Views', 'labels')) {
            $labels = \Modules\Refresh\Services\Views::labels();
            if (isset($labels[$view])) {
                return $labels[$view];
            }
        }

        return $view;
    }

    /** HTML of the dashboard block, or '' when nothing to show. */
    public static function render($user)
    {
        $access = new MailboxAccess($user);
        $mailboxes = $access->mailboxes();
        $ids = $access->allowedIds();
        if (!$ids) {
            return '';
        }

        // one minute per user, mailboxes and language (the data holds translated labels), and per state of the tickets:
        // after any change (new ticket, reply, status…) the figures are computed again, never served from the cache
        $fp = (new GlobalTicketQuery($access, GlobalTicketQuery::normalize([], $access)))->fingerprint();
        // "v2": the "Unresolved tickets" rows have open / pending since 1.14.0, the rows of an older module are not read
        $key = 'refreshglobal.dash.v2.'.$user->id.'.'.app()->getLocale().'.'.md5(implode(',', $ids)).'.'.$fp;
        $data = \Cache::remember($key, 1, function () use ($user, $mailboxes, $ids) {
            return self::data($user, $mailboxes, $ids);
        });

        // unresolved tickets of every mailbox, with the "Mailbox" column (not cached: links, stars, viewers)
        $query = new GlobalTicketQuery($access, GlobalTicketQuery::normalize(['status' => [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING]], $access));
        $conversations = $query->paginate(25);

        $previous = \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::$show_mailbox_column;
        \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::$show_mailbox_column = \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::mailboxColumnMode();
        try {
            return view('refreshglobal::dashboard', $data + [
                'rg_fp'         => $fp,
                'conversations' => $conversations,
                'folder'        => \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::virtualFolder(),
            ])->render();
        } finally {
            \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::$show_mailbox_column = $previous;
        }
    }

    protected static function data($user, $mailboxes, array $ids)
    {
        $V = '\Modules\Refresh\Services\Views';
        $labels = ['unresolved' => __('Unresolved'), 'overdue' => __('Overdue'), 'due-today' => __('Due: today'),
            'open' => __('refresh::labels.open'), 'pending' => __('Pending'), 'unassigned' => __('Unassigned')];
        $tiles = [];
        foreach (self::TILES as $key) {
            $count = 0;
            foreach ($ids as $id) {
                $count += $V::query($id, $key, $user)->count();
            }
            $tiles[] = ['key' => $key, 'label' => $labels[$key], 'count' => $count, 'url' => route('refreshglobal.tickets', ['rv' => $key])];
        }

        $undelivered = [];
        foreach ($mailboxes as $mailbox) {
            $n = $V::query($mailbox->id, 'undelivered', $user)->count();
            if ($n) {
                $undelivered[] = ['name' => $mailbox->name, 'n' => $n, 'url' => \Route::has('refresh.tickets')
                    ? route('refresh.tickets', ['mailbox_id' => $mailbox->id, 'view' => 'undelivered']) : MailboxAccess::mailboxUrl($mailbox)];
            }
        }

        return ['tiles' => $tiles, 'stats' => self::stats($user, $ids), 'undelivered' => $undelivered];
    }

    /** Same figures as Refresh's Dashboard::stats(), over several mailboxes and with the user's rights. */
    protected static function stats($user, array $ids)
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $yesterday = $today->copy()->subDay();
        $toUtc = function ($c) {
            return $c->copy()->setTimezone('UTC');
        };
        $base = function () use ($ids, $user) {
            return self::baseQuery($user, $ids);
        };
        $day = function ($from) use ($toUtc) {
            return [$toUtc($from), $toUtc($from->copy()->endOfDay())];
        };

        $hours = function ($from) use ($base, $day, $tz) {
            $h = array_fill(0, 24, 0);
            foreach ($base()->whereBetween('created_at', $day($from))->pluck('created_at') as $c) {
                $h[(int) Carbon::parse($c)->setTimezone($tz)->format('G')]++;
            }

            return $h;
        };
        $received = function ($from) use ($base, $day) {
            return $base()->whereBetween('created_at', $day($from))->count();
        };
        $resolved = function ($from) use ($base, $day) {
            return $base()->where('status', Conversation::STATUS_CLOSED)->whereBetween('closed_at', $day($from))->count();
        };
        $slaPct = function ($from) use ($base, $day) {
            $rows = $base()->where('status', Conversation::STATUS_CLOSED)->whereBetween('closed_at', $day($from))->get(['created_at', 'closed_at', 'meta']);
            if (!count($rows)) {
                return null;
            }
            $ok = 0;
            foreach ($rows as $r) {
                $meta = is_array($r->meta) ? $r->meta : (json_decode((string) $r->meta, true) ?: []);
                $due = !empty($meta['rf_due']) ? Carbon::parse($meta['rf_due'])
                    : Carbon::parse($r->created_at)->addHours(\Modules\Refresh\Services\Settings::resolutionHours());
                if (Carbon::parse($r->closed_at)->lte($due)) {
                    $ok++;
                }
            }

            return (int) round($ok * 100 / count($rows));
        };
        // average first response time (minutes) of the agent first replies sent that day
        $frt = function ($from) use ($base, $day) {
            $range = $day($from);
            $first = Thread::select('conversation_id', \DB::raw('MIN(created_at) AS r_at'))
                ->where('type', Thread::TYPE_MESSAGE)->where('state', Thread::STATE_PUBLISHED)->whereNotNull('created_by_user_id')
                ->whereIn('conversation_id', $base()->select('id')->getQuery())
                ->groupBy('conversation_id')
                ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$range[0]->toDateTimeString(), $range[1]->toDateTimeString()])
                ->get();
            $sum = 0;
            $n = 0;
            $created = [];
            foreach ($first as $row) {
                $created[$row->conversation_id] = Carbon::parse($row->r_at);
            }
            if (!$created) {
                return null;
            }
            foreach (Conversation::whereIn('id', array_keys($created))->get(['id', 'created_at']) as $c) {
                $sum += max(0, $created[$c->id]->diffInMinutes(Carbon::parse($c->created_at)));
                $n++;
            }

            return $n ? (int) round($sum / $n) : null;
        };

        return [
            'h_today'  => $hours($today),
            'h_yest'   => $hours($yesterday),
            'now_h'    => (int) Carbon::now($tz)->format('G'),
            'stats'    => [
                'resolved' => [$resolved($today), $resolved($yesterday)],
                'received' => [$received($today), $received($yesterday)],
                'frt'      => [$frt($today), $frt($yesterday)],
                'sla'      => [$slaPct($today), $slaPct($yesterday)],
            ],
            'by_agent' => self::unresolvedByAgent($base()),
        ];
    }

    /**
     * Tickets the dashboard's figures are computed on: the given mailboxes (the user's allowed ones), published, not
     * spam, only the user's own tickets with the "only assigned" right.
     */
    public static function baseQuery($user, array $ids)
    {
        $q = Conversation::whereIn('mailbox_id', $ids ?: [0])
            ->where('state', Conversation::STATE_PUBLISHED)
            ->where('status', '!=', Conversation::STATUS_SPAM);
        if ((new MailboxAccess($user))->onlyAssigned()) {
            $q->where('user_id', $user->id);
        }

        return $q;
    }

    /** "Unresolved tickets" rows for a user (allowed mailboxes and rights). */
    public static function unresolvedFor($user)
    {
        return self::unresolvedByAgent(self::baseQuery($user, (new MailboxAccess($user))->allowedIds()));
    }

    /**
     * "Unresolved tickets" card: per agent (and "Unassigned"), open and pending tickets counted apart, like Refresh's
     * own card since Refresh 1.5.0 (Modules/Refresh/Services/Dashboard.php:94-111): one grouped query (agent, status)
     * on the dashboard's base query (allowed mailboxes, published, "only assigned" right), the names in one more query.
     * Sorted by open + pending, then by name.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @return array [['user_id', 'name', 'open', 'pending', 'total', 'n' (= total), 'url', 'url_open', 'url_pending'], …]
     */
    public static function unresolvedByAgent($base)
    {
        $rows = $base->whereIn('status', [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING])
            ->select('user_id', 'status', \DB::raw('COUNT(*) AS n'))->groupBy('user_id', 'status')->get();
        $users = \App\User::whereIn('id', $rows->pluck('user_id')->filter()->unique()->values()->all())->get()->keyBy('id');
        $by_agent = [];
        foreach ($rows as $r) {
            $id = (int) $r->user_id;
            if (!isset($by_agent[$id])) {
                $url = function (array $status) use ($id) {
                    return route('refreshglobal.tickets', ['status' => $status, 'assignee' => $id ? (string) $id : 'none']);
                };
                $by_agent[$id] = [
                    'user_id'     => $id,
                    'name'        => ($id && isset($users[$id])) ? $users[$id]->getFullName() : __('Unassigned'),
                    'open'        => 0,
                    'pending'     => 0,
                    'total'       => 0,
                    'n'           => 0,
                    'url'         => $url([Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING]),
                    'url_open'    => $url([Conversation::STATUS_ACTIVE]),
                    'url_pending' => $url([Conversation::STATUS_PENDING]),
                ];
            }
            $by_agent[$id][(int) $r->status === Conversation::STATUS_PENDING ? 'pending' : 'open'] += (int) $r->n;
            $by_agent[$id]['total'] += (int) $r->n;
            $by_agent[$id]['n'] = $by_agent[$id]['total'];
        }
        $by_agent = array_values($by_agent);
        usort($by_agent, function ($a, $b) {
            return $b['total'] - $a['total'] ?: strnatcasecmp($a['name'], $b['name']);
        });

        return $by_agent;
    }

    /** Restricts a ticket query to a Refresh view (rv), over the given mailboxes, with Refresh's own definition. */
    public static function applyView($query, $view, array $ids, $user)
    {
        $query->where(function ($w) use ($view, $ids, $user) {
            foreach ($ids ?: [0] as $id) {
                $sub = \Modules\Refresh\Services\Views::query($id, $view, $user)->select('conversations.id');
                $w->orWhereIn('conversations.id', $sub->getQuery());
            }
        });
    }
}
