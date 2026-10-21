<?php

namespace Modules\RefreshGlobal\Services;

use App\Conversation;
use Modules\RefreshGlobal\Services\Dashboard\Statistics;
use Modules\RefreshGlobal\Services\Dashboard\TicketViews;

/**
 * Dashboard of ALL the user's mailboxes (scope "global-mailboxes" of the FreeScout Interop contract), computed and
 * rendered by RefreshGlobal alone: it works with or without Refresh (docs/INTEROPERABILITY.md §2.1, §19).
 *
 * Two places, chosen by the service provider (registerDashboard()):
 *  - MODE_REFRESH: Refresh computes its "My dashboard" for the user's first mailbox only
 *    (Modules/Refresh/Providers/RefreshServiceProvider.php:505-537); this dashboard replaces that block, with the same
 *    markup and class names, so Refresh's stylesheet gives it the same look;
 *  - MODE_STANDALONE: without Refresh, the same dashboard is shown above FreeScout's own dashboard (mailbox cards),
 *    styled by Public/css/refreshglobal-dashboard.css.
 *
 * Its pieces are the module's own services (Services/Dashboard): TicketViews (tiles and "rv" views), Statistics
 * (trends), Chart (SVG chart and formats), Sla (targets: Refresh's settings when present, else the module's).
 */
class GlobalDashboard
{
    const MODE_REFRESH = 'refresh';
    const MODE_STANDALONE = 'standalone';

    /** Tiles, in their order. */
    const TILES = ['unresolved', 'overdue', 'due-today', 'open', 'pending', 'unassigned'];

    /** Quick views the "All mailboxes" page can filter on (parameter rv). */
    const VIEWS = TicketViews::KEYS;

    /** Mode of the dashboard rendered during this request (MODE_*), null when none was (see renderedMode()). */
    protected static $rendered = null;

    /**
     * Mode of the dashboard rendered during this request, or null: tells the FreeScout Interop navigation context that
     * RefreshGlobal owns the "dashboard@global-mailboxes" surface of this page (Services/Interop/NavigationContext.php).
     */
    public static function renderedMode()
    {
        return self::$rendered;
    }

    /** Name of a quick view (filter chip, page title). */
    public static function viewLabel($view)
    {
        return TicketViews::label($view);
    }

    /**
     * HTML of the dashboard block, or '' when nothing to show.
     *
     * @param \App\User $user
     * @param string    $mode MODE_REFRESH or MODE_STANDALONE
     */
    public static function render($user, $mode = self::MODE_REFRESH)
    {
        $access = new MailboxAccess($user);
        // FreeScout Interop: other modules may narrow the mailboxes of this dashboard, never widen them
        // (Services/Interop/ServerHooks.php, filter fsinterop.dashboard.context)
        $ids = \Modules\RefreshGlobal\Services\Interop\ServerHooks::dashboardContext($user, $access->allowedIds())['mailbox_ids'];
        if (!$ids) {
            return '';
        }
        $mailboxes = $access->mailboxes()->filter(function ($mailbox) use ($ids) {
            return in_array((int) $mailbox->id, $ids, true);
        })->values();

        // one minute per user, mailboxes and language (the data holds translated labels), and per state of the tickets:
        // after any change (new ticket, reply, status…) the figures are computed again, never served from the cache
        $fp = (new GlobalTicketQuery($access, GlobalTicketQuery::normalize([], $access)))->fingerprint();
        // "v3": figures computed by the module itself since 1.17.0 (links of the undelivered card changed)
        $key = 'refreshglobal.dash.v3.'.$user->id.'.'.app()->getLocale().'.'.md5(implode(',', $ids)).'.'.$fp;
        $data = \Cache::remember($key, 1, function () use ($user, $mailboxes, $ids) {
            return self::data($user, $mailboxes, $ids);
        });

        // unresolved tickets of every mailbox, with the "Mailbox" column (not cached: links, stars, viewers)
        $query = new GlobalTicketQuery($access, GlobalTicketQuery::normalize(['status' => TicketViews::unresolvedStatuses(), 'mb' => $ids], $access));
        $conversations = $query->paginate(25);

        $previous = \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::$show_mailbox_column;
        \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::$show_mailbox_column = \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::mailboxColumnMode();
        try {
            $html = view('refreshglobal::dashboard', $data + [
                'rg_fp'         => $fp,
                'rg_mode'       => $mode === self::MODE_STANDALONE ? self::MODE_STANDALONE : self::MODE_REFRESH,
                'conversations' => $conversations,
                'folder'        => \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::virtualFolder(),
            ])->render();
            self::$rendered = $mode === self::MODE_STANDALONE ? self::MODE_STANDALONE : self::MODE_REFRESH;

            return $html;
        } finally {
            \Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider::$show_mailbox_column = $previous;
        }
    }

    /** Cached figures of the dashboard: tiles, trends, unresolved per agent, undelivered per mailbox. */
    protected static function data($user, $mailboxes, array $ids)
    {
        $base = function () use ($user, $ids) {
            return self::baseQuery($user, $ids);
        };
        $stats = (new Statistics($base))->summary();
        $stats['by_agent'] = self::unresolvedByAgent($base());

        return [
            'tiles'       => self::tiles($base),
            'stats'       => $stats,
            'undelivered' => self::undeliveredPerMailbox($base, $mailboxes),
        ];
    }

    /**
     * Tiles: one count per quick view on the base query; each tile opens the "All mailboxes" page on that view.
     *
     * @return array [['key', 'label', 'count', 'url'], …]
     */
    public static function tiles(callable $base)
    {
        $tiles = [];
        foreach (self::TILES as $view) {
            $tiles[] = [
                'key'   => $view,
                'label' => TicketViews::label($view),
                'count' => TicketViews::apply($base(), $view)->count(),
                'url'   => route('refreshglobal.tickets', ['rv' => $view]),
            ];
        }

        return $tiles;
    }

    /**
     * "Undelivered e-mails" card: mailboxes holding tickets whose last outgoing e-mail failed (one grouped query), each
     * linked to the "All mailboxes" page on that view and mailbox.
     *
     * @return array [['name', 'n', 'url'], …] in the order of $mailboxes
     */
    public static function undeliveredPerMailbox(callable $base, $mailboxes)
    {
        $counts = TicketViews::apply($base(), 'undelivered')
            ->select('conversations.mailbox_id', \DB::raw('COUNT(*) AS n'))
            ->groupBy('conversations.mailbox_id')
            ->pluck('n', 'mailbox_id');
        $rows = [];
        foreach ($mailboxes as $mailbox) {
            $n = (int) ($counts[$mailbox->id] ?? 0);
            if ($n) {
                $rows[] = ['name' => $mailbox->name, 'n' => $n, 'url' => route('refreshglobal.tickets', ['rv' => 'undelivered', 'mb' => [$mailbox->id]])];
            }
        }

        return $rows;
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
                    'name'        => ($id && isset($users[$id])) ? $users[$id]->getFullName() : __('refreshglobal::messages.dash_unassigned'),
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


    /** Restricts a ticket query to a quick view (rv); rights and mailboxes are already part of the query. */
    public static function applyView($query, $view)
    {
        return TicketViews::apply($query, $view);
    }
}
