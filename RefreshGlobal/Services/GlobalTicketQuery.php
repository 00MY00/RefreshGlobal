<?php

namespace Modules\RefreshGlobal\Services;

use App\Conversation;

/**
 * Builds THE query of the "All mailboxes" page: access rights + filters + sort.
 * The list, the counters and the CSV export all go through it, so they always apply the same rights.
 *
 * Filters (already cleaned by normalize()):
 *   mailboxes int[]   empty = every allowed mailbox
 *   status    int[]   empty = every status except spam (like FreeScout's folders and Refresh's "All tickets")
 *   assignee  string  '' any | 'me' | 'none' | user id
 *   q         string  subject, customer name, customer e-mail, ticket number
 *   sort      string  one of sorts()
 *   order     string  asc | desc
 */
class GlobalTicketQuery
{
    const MAX_QUERY_LENGTH = 200;

    /** @var MailboxAccess */
    protected $access;

    /** @var array */
    protected $filters;

    public function __construct(MailboxAccess $access, array $filters)
    {
        $this->access = $access;
        $this->filters = array_merge(self::defaults(), $filters);
    }

    public static function defaults()
    {
        return [
            'mailboxes' => [],
            'status'    => [],
            'assignee'  => '',
            'q'         => '',
            'sort'      => 'updated',
            'order'     => 'desc',
        ];
    }

    /** Sort key => column (without table). */
    public static function sorts()
    {
        return [
            'updated' => 'last_reply_at',
            'created' => 'created_at',
            'number'  => self::numberColumn(),
            'subject' => 'subject',
            'status'  => 'status',
        ];
    }

    public static function statuses()
    {
        return [
            Conversation::STATUS_ACTIVE  => Conversation::statusCodeToName(Conversation::STATUS_ACTIVE),
            Conversation::STATUS_PENDING => Conversation::statusCodeToName(Conversation::STATUS_PENDING),
            Conversation::STATUS_CLOSED  => Conversation::statusCodeToName(Conversation::STATUS_CLOSED),
            Conversation::STATUS_SPAM    => Conversation::statusCodeToName(Conversation::STATUS_SPAM),
        ];
    }

    /**
     * Cleans raw input (request or saved view). Unknown values are dropped; mailboxes the user can not see are
     * dropped and counted in 'dropped_mailboxes' (saved views show a discreet note).
     */
    public static function normalize(array $input, MailboxAccess $access)
    {
        $f = self::defaults();

        $requested = isset($input['mb']) ? (array) $input['mb'] : (isset($input['mailboxes']) ? (array) $input['mailboxes'] : []);
        $requested = array_values(array_filter($requested, function ($v) {
            return $v !== '' && $v !== null;
        }));
        $f['mailboxes'] = $access->filterAllowed($requested);
        $f['dropped_mailboxes'] = max(0, count(array_unique(array_map('strval', array_filter($requested, 'is_scalar')))) - count($f['mailboxes']));

        $statuses = array_keys(self::statuses());
        foreach ((array) ($input['status'] ?? []) as $s) {
            if (is_scalar($s) && in_array((int) $s, $statuses, true) && !in_array((int) $s, $f['status'], true)) {
                $f['status'][] = (int) $s;
            }
        }
        sort($f['status']);

        $assignee = isset($input['assignee']) && is_scalar($input['assignee']) ? (string) $input['assignee'] : '';
        if (in_array($assignee, ['me', 'none'], true) || preg_match('/^[1-9]\d{0,9}$/', $assignee)) {
            $f['assignee'] = $assignee;
        }

        $q = isset($input['q']) && is_scalar($input['q']) ? trim((string) $input['q']) : '';
        $f['q'] = mb_substr($q, 0, self::MAX_QUERY_LENGTH);

        $sort = isset($input['sort']) && is_scalar($input['sort']) ? (string) $input['sort'] : '';
        $f['sort'] = array_key_exists($sort, self::sorts()) ? $sort : 'updated';
        $order = isset($input['order']) && is_scalar($input['order']) ? (string) $input['order'] : '';
        $f['order'] = $order === 'asc' ? 'asc' : 'desc';

        return $f;
    }

    /** Filters as URL parameters (shareable link, saved view). */
    public static function toQueryParams(array $f)
    {
        $params = [];
        if (!empty($f['mailboxes'])) {
            $params['mb'] = array_values($f['mailboxes']);
        }
        if (!empty($f['status'])) {
            $params['status'] = array_values($f['status']);
        }
        if (isset($f['assignee']) && $f['assignee'] !== '') {
            $params['assignee'] = $f['assignee'];
        }
        if (isset($f['q']) && $f['q'] !== '') {
            $params['q'] = $f['q'];
        }
        if (isset($f['sort']) && $f['sort'] !== 'updated') {
            $params['sort'] = $f['sort'];
        }
        if (isset($f['order']) && $f['order'] !== 'desc') {
            $params['order'] = $f['order'];
        }

        return $params;
    }

    /** Number of active filters (badge of the Filters button). */
    public static function activeCount(array $f)
    {
        $n = 0;
        foreach (['mailboxes', 'status'] as $k) {
            if (!empty($f[$k])) {
                $n++;
            }
        }
        foreach (['assignee', 'q'] as $k) {
            if (isset($f[$k]) && $f[$k] !== '') {
                $n++;
            }
        }

        return $n;
    }

    public function filters()
    {
        return $this->filters;
    }

    /**
     * Rights + filters, no sort.
     *
     * @param string[] $except filters to leave out ('mailboxes' or 'status', for the counters)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function base(array $except = [])
    {
        $f = $this->filters;
        $query = Conversation::query()->select('conversations.*');

        // 1. Rights: allowed mailboxes only, whatever was requested (the request is intersected again here).
        $allowed = $this->access->allowedIds();
        $ids = $allowed;
        if (!in_array('mailboxes', $except, true) && !empty($f['mailboxes'])) {
            $ids = array_values(array_intersect(array_map('intval', $f['mailboxes']), $allowed));
        }
        // whereIn([]) would produce invalid SQL on some drivers: 0 never matches an id
        $query->whereIn('conversations.mailbox_id', $ids ?: [0]);

        // 2. Rights: "User can see only assigned conversations"
        $user = $this->access->user();
        if ($this->access->onlyAssigned()) {
            $query->where('conversations.user_id', $user->id);
        }

        // Published tickets only: drafts belong to their author, deleted ones are in the trash
        $query->where('conversations.state', Conversation::STATE_PUBLISHED);

        if (!in_array('status', $except, true)) {
            if (!empty($f['status'])) {
                $query->whereIn('conversations.status', array_map('intval', $f['status']));
            } else {
                $query->where('conversations.status', '!=', Conversation::STATUS_SPAM);
            }
        } else {
            // status counters: spam counted only when asked for, like the list
            if (empty($f['status']) || !in_array(Conversation::STATUS_SPAM, $f['status'], true)) {
                $query->where('conversations.status', '!=', Conversation::STATUS_SPAM);
            }
        }

        if ($f['assignee'] === 'me') {
            $query->where('conversations.user_id', $user ? $user->id : 0);
        } elseif ($f['assignee'] === 'none') {
            $query->whereNull('conversations.user_id');
        } elseif ($f['assignee'] !== '') {
            $query->where('conversations.user_id', (int) $f['assignee']);
        }

        if ($f['q'] !== '') {
            $this->applySearch($query, $f['q']);
        }

        return $query;
    }

    /** Rights + filters + sort. */
    public function query(array $except = [])
    {
        $query = $this->base($except);
        $sorts = self::sorts();
        $dir = $this->filters['order'] === 'asc' ? 'asc' : 'desc';
        $column = $sorts[$this->filters['sort']] ?? 'last_reply_at';
        $query->orderBy('conversations.'.$column, $dir);

        return $query->orderBy('conversations.id', $dir);
    }

    public function paginate($per_page)
    {
        return $this->query()->with(['mailbox', 'customer', 'user'])->paginate($per_page);
    }

    /** mailbox_id => number of tickets with the current filters except the mailbox filter (one grouped query). */
    public function countsByMailbox()
    {
        return $this->grouped('mailbox_id', ['mailboxes']);
    }

    /** status => number of tickets with the current filters except the status filter (one grouped query). */
    public function countsByStatus()
    {
        return $this->grouped('status', ['status']);
    }

    protected function grouped($column, array $except)
    {
        $rows = $this->base($except)->toBase()
            ->select('conversations.'.$column.' as k', \DB::raw('COUNT(*) as n'))
            ->groupBy('conversations.'.$column)
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->k] = (int) $row->n;
        }

        return $out;
    }

    protected function applySearch($query, $text)
    {
        $op = self::likeOperator();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($text)).'%';
        $number = ltrim($text, '#');
        $words = preg_split('/\s+/u', $text, 2);

        $query->where(function ($w) use ($op, $like, $number, $words) {
            $w->where('conversations.subject', $op, $like)
                ->orWhere('conversations.customer_email', $op, $like)
                ->orWhereIn('conversations.customer_id', function ($s) use ($op, $like, $words) {
                    $s->select('customers.id')->from('customers')
                        ->where('customers.first_name', $op, $like)
                        ->orWhere('customers.last_name', $op, $like);
                    // "First Last": first word in the first name, the rest in the last name (works on every database)
                    if (count($words) === 2) {
                        $first = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($words[0])).'%';
                        $last = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($words[1])).'%';
                        $s->orWhere(function ($n) use ($op, $first, $last) {
                            $n->where('customers.first_name', $op, $first)->where('customers.last_name', $op, $last);
                        });
                    }
                });
            if ($number !== '' && preg_match('/^\d{1,10}$/', $number) && (int) $number <= 2147483647) {
                $w->orWhere('conversations.'.self::numberColumn(), (int) $number);
            }
        });
    }

    /** "number" column of FreeScout (or "id" when custom numbering is off): Conversation::numberFieldName(), app/Conversation.php:2695. */
    public static function numberColumn()
    {
        if (method_exists(Conversation::class, 'numberFieldName')) {
            return Conversation::numberFieldName();
        }

        return 'number';
    }

    /** "ilike" on PostgreSQL (case-insensitive), like Conversation::search() (app/Conversation.php:2571-2574). */
    public static function likeOperator()
    {
        if (method_exists(\App\Misc\Helper::class, 'isPgSql') && \App\Misc\Helper::isPgSql()) {
            return 'ilike';
        }

        return 'like';
    }
}
