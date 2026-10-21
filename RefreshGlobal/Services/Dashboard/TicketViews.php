<?php

namespace Modules\RefreshGlobal\Services\Dashboard;

use App\Conversation;
use App\Thread;
use Carbon\Carbon;

/**
 * Quick views of the dashboard of all mailboxes (tiles, parameter "rv" of the "All mailboxes" page), owned by
 * RefreshGlobal: they work with or without Refresh (docs/INTEROPERABILITY.md §2.1).
 *
 * Same meaning as Refresh's views of the same name (Modules/Refresh/Services/Views.php:116-219, Refresh 1.6.0), so the
 * figures match when both modules are installed; the SLA hours come from Sla (Refresh's settings when present).
 *
 * The views only add conditions: rights, mailboxes, published state and spam are the caller's query (GlobalTicketQuery,
 * GlobalDashboard::baseQuery), so a view never widens what the user may see.
 */
class TicketViews
{
    /** Views offered, in the order of the tiles. */
    const KEYS = ['unresolved', 'overdue', 'due-today', 'open', 'pending', 'unassigned', 'new', 'undelivered'];

    /** Is this a known view key? */
    public static function exists($view)
    {
        return in_array((string) $view, self::KEYS, true);
    }

    /** Translated name of a view (tiles, filter chip, page title). */
    public static function label($view)
    {
        $keys = [
            'unresolved'  => 'dash_unresolved',
            'overdue'     => 'dash_overdue',
            'due-today'   => 'dash_due_today',
            'open'        => 'dash_open',
            'pending'     => 'dash_pending',
            'unassigned'  => 'dash_unassigned',
            'new'         => 'dash_new',
            'undelivered' => 'dash_undelivered',
        ];

        return isset($keys[$view]) ? __('refreshglobal::messages.'.$keys[$view]) : (string) $view;
    }

    /**
     * Restricts a conversations query to a view. Unknown keys leave the query unchanged.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query query on the conversations table
     * @return \Illuminate\Database\Eloquent\Builder the same query
     */
    public static function apply($query, $view)
    {
        switch ($view) {
            case 'unresolved':
                return $query->whereIn('conversations.status', self::unresolvedStatuses());
            case 'open':
                return $query->where('conversations.status', Conversation::STATUS_ACTIVE);
            case 'pending':
                return $query->where('conversations.status', Conversation::STATUS_PENDING);
            case 'unassigned':
                return $query->whereIn('conversations.status', self::unresolvedStatuses())->whereNull('conversations.user_id');
            case 'new':
                $query->where('conversations.status', Conversation::STATUS_ACTIVE);

                return self::whereNeverAnswered($query);
            case 'overdue':
                return self::whereOverdue($query);
            case 'due-today':
                return self::whereDueToday($query);
            case 'undelivered':
                return self::whereUndelivered($query);
        }

        return $query;
    }

    /** Open and pending: the statuses of a ticket that still needs work. */
    public static function unresolvedStatuses()
    {
        return [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING];
    }

    /** Open tickets past the resolution target, or past the first-response target without any agent reply. */
    protected static function whereOverdue($query)
    {
        $now = Carbon::now();
        $resolution = Sla::resolutionHours();
        $firstResponse = Sla::firstResponseHours();

        return $query->where('conversations.status', Conversation::STATUS_ACTIVE)
            ->where(function ($w) use ($now, $resolution, $firstResponse) {
                $w->where('conversations.created_at', '<', $now->copy()->subHours($resolution))
                    ->orWhere(function ($late) use ($now, $firstResponse) {
                        $late->where('conversations.created_at', '<', $now->copy()->subHours($firstResponse));
                        self::whereNeverAnswered($late);
                    });
            });
    }

    /** Open tickets whose resolution target falls between now and the end of today. */
    protected static function whereDueToday($query)
    {
        $now = Carbon::now();
        $hours = Sla::resolutionHours();

        return $query->where('conversations.status', Conversation::STATUS_ACTIVE)
            ->whereBetween('conversations.created_at', [
                $now->copy()->subHours($hours),
                $now->copy()->endOfDay()->subHours($hours),
            ]);
    }

    /** No published agent message on the ticket yet. */
    public static function whereNeverAnswered($query)
    {
        $prefix = \DB::getTablePrefix();

        return $query->whereNotExists(function ($q) use ($prefix) {
            $q->select(\DB::raw(1))->from('threads')
                ->whereRaw($prefix.'threads.conversation_id = '.$prefix.'conversations.id')
                ->where('threads.type', Thread::TYPE_MESSAGE)
                ->where('threads.state', Thread::STATE_PUBLISHED)
                ->whereNotNull('threads.created_by_user_id');
        });
    }

    /**
     * Tickets whose LAST outgoing e-mail failed (FreeScout's send log statuses on the thread): a later reply sent
     * successfully, or the failed one retried, takes the ticket out of the view.
     */
    protected static function whereUndelivered($query)
    {
        $errors = [\App\SendLog::STATUS_SEND_ERROR, \App\SendLog::STATUS_DELIVERY_ERROR];

        return $query->whereIn('conversations.id', function ($failed) use ($errors) {
            $failed->select('t.conversation_id')->from('threads as t')
                ->whereIn('t.send_status', $errors)
                ->whereNotExists(function ($later) use ($errors) {
                    $later->select(\DB::raw(1))->from('threads as t2')
                        ->whereColumn('t2.conversation_id', 't.conversation_id')
                        ->whereColumn('t2.id', '>', 't.id')
                        ->where('t2.type', Thread::TYPE_MESSAGE)
                        ->where('t2.state', Thread::STATE_PUBLISHED)
                        ->where(function ($ok) use ($errors) {
                            $ok->whereNull('t2.send_status')->orWhereNotIn('t2.send_status', $errors);
                        });
                });
        });
    }
}
