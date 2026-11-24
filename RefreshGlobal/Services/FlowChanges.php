<?php

namespace Modules\RefreshGlobal\Services;

use Carbon\Carbon;

/**
 * Statistics of the e-mails handled by the flows of Workflow Packs 3.20+ (docs/api-flux.md of Workflow Packs).
 *
 *  - record(): each change announced by the action "workflowpacks.ticket_changed" is validated and kept in the
 *    module's own table refreshglobal_flow_changes (one row per step and field: unique (event_id, field), so a change is
 *    never counted twice);
 *  - backfill(): the changes made before the listener existed, read with "workflowpacks.ticket_changes" by batches of
 *    1000 tickets (command refreshglobal:flow-history, or the administrators' button);
 *  - stats(): per flow, mailbox and origin, always limited to the mailboxes the user may see (MailboxAccess, checked on
 *    every request).
 *
 * Only used when WorkflowPacksLink::available(). No text of an e-mail or note is ever stored: numbers, the flow's name
 * and known values only.
 */
class FlowChanges
{
    const TABLE = 'refreshglobal_flow_changes';
    const OPTION_BACKFILL = 'refreshglobal.flow_backfill';

    const FIELDS = ['status', 'assignee', 'folder', 'note', 'deleted', 'push'];
    const STATUSES = ['active', 'pending', 'closed', 'spam'];
    const ORIGINS = ['new_mail', 'reapply', 'agent', 'temporary'];
    /** days of the statistics and of the backfill */
    const MAX_DAYS = 3650;
    const DEFAULT_DAYS = 30;
    /** changes given back at most by one call of workflowpacks.ticket_changes */
    const MAX_ROWS = 5000;

    /** tickets per call of workflowpacks.ticket_changes (tests use a smaller batch) */
    public static $batch = WorkflowPacksLink::MAX_IDS;

    protected static $registered = false;

    /**
     * Listens to Workflow Packs (once). Called by the service provider once every module has booted, only when
     * WorkflowPacksLink::available().
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        \Eventy::addAction('workflowpacks.ticket_changed', function ($conversationId = null, $change = []) {
            try {
                self::record($conversationId, $change);
            } catch (\Throwable $e) {
                // never stops the flow; no personal data in the log (class and code only)
                \Log::warning('[RefreshGlobal] [flows] ticket_changed: '.get_class($e).' '.$e->getCode());
            }
        }, 20, 2);
        \Eventy::addAction('workflowpacks.temp_flow_finished', function ($flowId = null, $summary = []) {
            try {
                FlowTemp::finished($flowId, $summary);
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [flows] temp_flow_finished: '.get_class($e).' '.$e->getCode());
            }
        }, 20, 2);
    }

    /** Tests: listen again after the hooks were reset. */
    public static function forgetRegistration()
    {
        self::$registered = false;
    }

    /**
     * Validates a change and stores it unless it is already there.
     *
     * @return bool true when a row was added
     */
    public static function record($conversationId, $change)
    {
        $row = self::normalize($conversationId, $change);
        if (!$row) {
            return false;
        }
        $key = ['event_id' => $row['event_id'], 'field' => $row['field']];
        // Laravel 5.5 cannot ignore a duplicate insert: check first, and the unique index catches a parallel insert
        if (\DB::table(self::TABLE)->where($key)->exists()) {
            return false;
        }
        try {
            \DB::table(self::TABLE)->insert($row);
        } catch (\Illuminate\Database\QueryException $e) {
            if (\DB::table(self::TABLE)->where($key)->exists()) {
                return false; // inserted meanwhile by another process
            }
            throw $e;
        }

        return true;
    }

    /**
     * A change as stored, or null when it is not valid (wrong format version, missing ids, unknown field name…).
     * Only known values are kept; anything else becomes null.
     */
    public static function normalize($conversationId, $change)
    {
        if (!is_array($change) || !self::positive($change['v'] ?? null) || (int) $change['v'] !== 1) {
            return null;
        }
        $conversation = self::positive($conversationId) ?: self::positive($change['conversation_id'] ?? null);
        $event = self::positive($change['event_id'] ?? null, PHP_INT_MAX);
        $flow = self::positive($change['flow_id'] ?? null);
        $field = is_string($change['field'] ?? null) ? $change['field'] : '';
        if (!$conversation || !$event || !$flow || !preg_match('/^[a-z0-9_.]{1,40}$/', $field)) {
            return null;
        }
        if (isset($change['conversation_id']) && self::positive($change['conversation_id']) && (int) $change['conversation_id'] !== $conversation) {
            return null; // two different tickets in one announcement
        }
        $origin = in_array($change['origin'] ?? null, self::ORIGINS, true) ? $change['origin'] : 'new_mail';
        $at = is_string($change['at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $change['at'])
            ? $change['at'] : Carbon::now()->format('Y-m-d H:i:s');

        return [
            'event_id'        => $event,
            'field'           => $field,
            'conversation_id' => $conversation,
            'mailbox_id'      => self::positive($change['mailbox_id'] ?? null) ?: null,
            'flow_id'         => $flow,
            'flow_name'       => self::text($change['flow_name'] ?? '', 191),
            'after'           => self::after($field, $change['after'] ?? null),
            'origin'          => $origin,
            'temporary'       => !empty($change['temporary']) || $origin === 'temporary' ? 1 : 0,
            'created_at'      => $at,
        ];
    }

    /** Value after the change, as text: known values only. */
    protected static function after($field, $value)
    {
        switch ($field) {
            case 'status':
                return in_array($value, self::STATUSES, true) ? $value : null;
            case 'assignee':
            case 'push':
                return self::positive($value) ? (string) (int) $value : null;
            case 'folder':
                return is_string($value) && trim($value) !== '' ? self::text($value, 191) : null;
            case 'note':
            case 'deleted':
                return $value === true || $value === 1 || $value === '1' || $value === 'true' ? '1' : null;
        }

        return null; // block of another module: its name only
    }

    /** Positive integer (int or digits), else 0. */
    protected static function positive($value, $max = 4294967295)
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) <= 19)) {
            $value = (int) $value;

            return $value > 0 && $value <= $max ? $value : 0;
        }

        return 0;
    }

    /** One line of text, without control characters, cut to $max characters. */
    protected static function text($value, $max)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value);

        return mb_substr(trim((string) $value), 0, $max);
    }

    /** Tickets deleted for good: their rows go too (FreeScout's action conversations.before_delete_forever). */
    public static function forget($ids)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if (!$ids || !self::tableExists()) {
            return;
        }
        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                \DB::table(self::TABLE)->whereIn('conversation_id', $chunk)->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [flows] forget: '.get_class($e));
        }
    }

    public static function tableExists()
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $exists = SchemaCache::has(self::TABLE);
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    /* ---------------------------------------------------------------- backfill */

    /**
     * Reads the changes of the tickets of these mailboxes updated during the last $days days, by batches of
     * self::$batch tickets (a batch that hits the 5000 changes of one answer is split in two), and records the missing
     * ones. Safe to run again: nothing is counted twice.
     *
     * @param int[]         $mailboxIds mailboxes ([] = none)
     * @param callable|null $progress   function (done, total, recorded)
     * @return array ['tickets' => read, 'total' => to read, 'changes' => seen, 'recorded' => added]
     */
    public static function backfill(array $mailboxIds, $days, ?callable $progress = null)
    {
        $out = ['tickets' => 0, 'total' => 0, 'changes' => 0, 'recorded' => 0];
        $mailboxIds = array_values(array_filter(array_map('intval', $mailboxIds)));
        if (!$mailboxIds || !WorkflowPacksLink::available()) {
            return $out;
        }
        $since = Carbon::now()->subDays(self::days($days));
        $query = \App\Conversation::whereIn('mailbox_id', $mailboxIds)->where('updated_at', '>=', $since);
        $out['total'] = (clone $query)->count();
        $last = 0;
        $batch = max(1, min(WorkflowPacksLink::MAX_IDS, (int) self::$batch));
        while (true) {
            $ids = (clone $query)->where('id', '>', $last)->orderBy('id')->limit($batch)->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();
            if (!$ids) {
                break;
            }
            foreach (self::changesOf($ids) as $id => $changes) {
                foreach ((array) $changes as $change) {
                    $out['changes']++;
                    if (self::record($id, $change)) {
                        $out['recorded']++;
                    }
                }
            }
            $out['tickets'] += count($ids);
            $last = end($ids);
            if ($progress) {
                $progress($out['tickets'], $out['total'], $out['recorded']);
            }
        }

        return $out;
    }

    /** Changes of these tickets; an answer that may have been cut (5000 changes) is asked again in two halves. */
    protected static function changesOf(array $ids)
    {
        $changes = WorkflowPacksLink::ticketChanges($ids);
        $n = 0;
        foreach ($changes as $list) {
            $n += count((array) $list);
        }
        if ($n >= self::MAX_ROWS && count($ids) > 1) {
            $half = (int) ceil(count($ids) / 2);

            return self::changesOf(array_slice($ids, 0, $half)) + self::changesOf(array_slice($ids, $half));
        }

        return $changes;
    }

    public static function days($days)
    {
        $days = is_numeric($days) ? (int) $days : self::DEFAULT_DAYS;

        return max(1, min(self::MAX_DAYS, $days));
    }

    /** Backfill asked by an administrator: run by the scheduler within a minute (command --requested). */
    public static function requestBackfill(array $mailboxIds, $days, $userId)
    {
        self::saveBackfill(['state' => 'requested', 'days' => self::days($days), 'mailboxes' => array_values(array_map('intval', $mailboxIds)),
            'user_id' => (int) $userId, 'requested_at' => date('Y-m-d H:i:s'), 'done' => 0, 'total' => 0, 'recorded' => 0]);
    }

    /** ['state' => '' | requested | running | done | failed, 'days', 'mailboxes', 'done', 'total', 'recorded', …] */
    public static function backfillStatus()
    {
        try {
            $s = Settings::option(self::OPTION_BACKFILL, []);
            if (is_string($s)) {
                $s = json_decode($s, true);
            }
        } catch (\Throwable $e) {
            $s = null;
        }

        return (is_array($s) ? $s : []) + ['state' => '', 'days' => self::DEFAULT_DAYS, 'mailboxes' => [], 'done' => 0, 'total' => 0, 'recorded' => 0];
    }

    public static function saveBackfill(array $status)
    {
        \App\Option::set(self::OPTION_BACKFILL, $status);
    }

    /** Runs the requested backfill (if any), saving the progress after each batch. */
    public static function runRequested(?callable $out = null)
    {
        $status = self::backfillStatus();
        if ($status['state'] !== 'requested') {
            return null;
        }
        $mailboxes = $status['mailboxes'] ?: \App\Mailbox::pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $status['state'] = 'running';
        $status['started_at'] = date('Y-m-d H:i:s');
        self::saveBackfill($status);
        try {
            $r = self::backfill($mailboxes, $status['days'], function ($done, $total, $recorded) use (&$status, $out) {
                $status = ['done' => $done, 'total' => $total, 'recorded' => $recorded] + $status;
                self::saveBackfill($status);
                if ($out) {
                    $out($done, $total, $recorded);
                }
            });
            $status = ['state' => 'done', 'finished_at' => date('Y-m-d H:i:s'), 'done' => $r['tickets'], 'total' => $r['total'], 'recorded' => $r['recorded']] + $status;
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [flows] backfill: '.get_class($e));
            $status = ['state' => 'failed', 'finished_at' => date('Y-m-d H:i:s')] + $status;
        }
        self::saveBackfill($status);

        return $status;
    }

    /* ---------------------------------------------------------------- statistics */

    /**
     * Cleans the filters of the statistics page. Mailboxes are limited to those the user may see.
     *
     * @return array ['days', 'mailboxes' (ids, never empty unless the user has none), 'mailbox' (one asked or 0), 'flow', 'origin']
     */
    public static function filters(array $input, MailboxAccess $access)
    {
        $allowed = $access->allowedIds();
        $mailbox = (int) ($input['mb'] ?? 0);
        $mailbox = $mailbox && in_array($mailbox, $allowed, true) ? $mailbox : 0;
        $origin = (string) ($input['origin'] ?? '');

        return [
            'days'      => self::days($input['days'] ?? self::DEFAULT_DAYS),
            'mailbox'   => $mailbox,
            'mailboxes' => $mailbox ? [$mailbox] : $allowed,
            'flow'      => max(0, (int) ($input['flow'] ?? 0)),
            'origin'    => in_array($origin, self::ORIGINS, true) ? $origin : '',
        ];
    }

    /** Rows the user may count: allowed mailboxes, period, filters, and "only assigned" permission. */
    public static function query(array $f, MailboxAccess $access)
    {
        $q = \DB::table(self::TABLE)
            ->whereIn('mailbox_id', $f['mailboxes'] ?: [0])
            ->where('created_at', '>=', Carbon::now()->subDays($f['days'])->format('Y-m-d H:i:s'));
        if ($f['flow']) {
            $q->where('flow_id', $f['flow']);
        }
        if ($f['origin'] !== '') {
            $q->where('origin', $f['origin']);
        }
        if ($access->onlyAssigned()) {
            $user = $access->user();
            $q->whereIn('conversation_id', function ($s) use ($user) {
                $s->select('id')->from('conversations')->where('user_id', $user ? $user->id : 0);
            });
        }

        return $q;
    }

    /**
     * Figures of the statistics page.
     *
     * @return array ['totals' => [...], 'flows' => [...], 'mailboxes' => [...], 'origins' => [...], 'since' => first row date]
     */
    public static function stats(array $f, MailboxAccess $access)
    {
        // kept one minute, and until the next recorded change; the key holds everything the rights depend on
        $user = $access->user();
        $key = 'refreshglobal.flowstats.'.md5(json_encode([$f, $access->onlyAssigned() ? ($user ? $user->id : 0) : null,
            \DB::table(self::TABLE)->max('id'), Compatibility\CompatibilityChecker::moduleVersion()]));

        return \Cache::remember($key, 1, function () use ($f, $access) {
            return self::computeStats($f, $access);
        });
    }

    const CACHE_DELETED = 'refreshglobal.flows_deleted';
    /** minutes between two readings of the deleted flows */
    const DELETED_MINUTES = 360;

    /** Ids of the flows Workflow Packs no longer has (as of the last scheduled reading). */
    public static function deletedFlows()
    {
        $row = \Cache::get(self::CACHE_DELETED);

        return is_array($row) && isset($row['ids']) ? array_map('intval', (array) $row['ids']) : [];
    }

    /**
     * 1.23.0: asks Workflow Packs which recorded flows were deleted. It reads the whole history of the flows (seconds on
     * a busy installation): scheduled task only (refreshglobal:flow-history --requested), every 6 hours.
     */
    public static function refreshDeleted($force = false)
    {
        $row = \Cache::get(self::CACHE_DELETED);
        if (!$force && is_array($row) && isset($row['at']) && time() - $row['at'] < self::DELETED_MINUTES * 60) {
            return false;
        }
        $known = WorkflowPacksLink::flowStats([
            'from' => Carbon::now()->subDays(365)->format('Y-m-d'), 'to' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
        $ids = [];
        foreach ((array) ($known['flows'] ?? []) as $k) {
            if (is_array($k) && (int) ($k['flow_id'] ?? 0) && array_key_exists('exists', $k) && $k['exists'] === false) {
                $ids[] = (int) $k['flow_id'];
            }
        }
        \Cache::forever(self::CACHE_DELETED, ['at' => time(), 'ids' => $ids]);

        return true;
    }

    public static function computeStats(array $f, MailboxAccess $access)
    {
        $metrics = function ($q) {
            $prefix = \DB::getTablePrefix();
            $c = $prefix.self::TABLE;
            $select = 'COUNT(*) as changes, COUNT(DISTINCT '.$c.'.conversation_id) as tickets,'
                .' COUNT(DISTINCT CASE WHEN '.$c.'.field = ? AND '.$c.'.after = ? THEN '.$c.'.conversation_id END) as closed,'
                .' MAX('.$c.'.temporary) as temporary, MAX('.$c.'.id) as last_id';
            $bindings = ['status', 'closed'];
            foreach (self::FIELDS as $field) {
                $select .= ', SUM(CASE WHEN '.$c.'.field = ? THEN 1 ELSE 0 END) as f_'.$field;
                $bindings[] = $field;
            }
            $select .= ', SUM(CASE WHEN '.$c.'.field NOT IN ('.implode(',', array_fill(0, count(self::FIELDS), '?')).') THEN 1 ELSE 0 END) as f_other';

            return $q->selectRaw($select, array_merge($bindings, self::FIELDS));
        };
        $row = function ($r) {
            $types = [];
            foreach (array_merge(self::FIELDS, ['other']) as $field) {
                $types[$field] = (int) ($r->{'f_'.$field} ?? 0);
            }

            return ['changes' => (int) $r->changes, 'tickets' => (int) $r->tickets, 'closed' => (int) $r->closed,
                'temporary' => (bool) $r->temporary, 'types' => $types];
        };

        $totals = $row($metrics(self::query($f, $access))->first() ?: (object) ['changes' => 0, 'tickets' => 0, 'closed' => 0, 'temporary' => 0]);

        $flows = [];
        $lastIds = [];
        foreach ($metrics(self::query($f, $access))->addSelect('flow_id')->groupBy('flow_id')->get() as $r) {
            $flows[(int) $r->flow_id] = ['flow_id' => (int) $r->flow_id, 'name' => '', 'exists' => true] + $row($r);
            $lastIds[] = (int) $r->last_id;
        }
        // the flow's latest name (it may have been renamed)
        foreach (array_chunk($lastIds, 500) as $chunk) {
            foreach (\DB::table(self::TABLE)->whereIn('id', $chunk)->get(['flow_id', 'flow_name']) as $r) {
                if (isset($flows[(int) $r->flow_id])) {
                    $flows[(int) $r->flow_id]['name'] = (string) $r->flow_name;
                }
            }
        }
        // flows deleted since: noted by the scheduled task (refreshDeleted()), never asked during a click
        $deleted = self::deletedFlows();
        foreach (array_keys($flows) as $id) {
            $flows[$id]['exists'] = !in_array($id, $deleted, true);
        }
        $flows = array_values($flows);
        usort($flows, function ($a, $b) {
            return $b['tickets'] - $a['tickets'] ?: strnatcasecmp($a['name'], $b['name']);
        });

        $mailboxes = [];
        foreach ($metrics(self::query($f, $access))->addSelect('mailbox_id')->groupBy('mailbox_id')->get() as $r) {
            $mailboxes[(int) $r->mailbox_id] = ['mailbox_id' => (int) $r->mailbox_id] + $row($r);
        }
        $origins = [];
        foreach ($metrics(self::query($f, $access))->addSelect('origin')->groupBy('origin')->get() as $r) {
            $origins[(string) $r->origin] = ['origin' => (string) $r->origin] + $row($r);
        }

        $since = \DB::table(self::TABLE)->whereIn('mailbox_id', $f['mailboxes'] ?: [0])->min('created_at');

        return ['totals' => $totals, 'flows' => $flows, 'mailboxes' => $mailboxes, 'origins' => $origins, 'since' => $since];
    }

    /** periods of the "Workflow Packs flows" card of the dashboard, in days (same meaning as "days" of the page) */
    const DASHBOARD_DAYS = [1, 7, 30];

    /**
     * "Workflow Packs flows" card of the dashboard of all mailboxes (1.21.0): tickets handled (any change) and closed by
     * the flows over the last 1, 7 and 30 days, in one query, within the same rights as the statistics page.
     *
     * @param array $ids mailboxes of the dashboard (already limited to the user's allowed ones)
     * @return array [['days', 'tickets', 'closed', 'url'], …] or [] when the module's table is missing
     */
    public static function dashboard(array $ids, MailboxAccess $access)
    {
        if (!$ids || !self::tableExists()) {
            return [];
        }
        $allowed = array_values(array_intersect(array_map('intval', $ids), $access->allowedIds()));
        $max = max(self::DASHBOARD_DAYS);
        $c = \DB::getTablePrefix().self::TABLE;
        $select = [];
        $bindings = [];
        foreach (self::DASHBOARD_DAYS as $days) {
            $from = Carbon::now()->subDays($days)->format('Y-m-d H:i:s');
            $select[] = 'COUNT(DISTINCT CASE WHEN '.$c.'.created_at >= ? THEN '.$c.'.conversation_id END) as t'.$days;
            $select[] = 'COUNT(DISTINCT CASE WHEN '.$c.'.created_at >= ? AND '.$c.'.field = ? AND '.$c.'.after = ? THEN '.$c.'.conversation_id END) as c'.$days;
            array_push($bindings, $from, $from, 'status', 'closed');
        }
        $r = self::query(['days' => $max, 'mailboxes' => $allowed, 'flow' => 0, 'origin' => ''], $access)
            ->selectRaw(implode(', ', $select), $bindings)->first();
        $rows = [];
        foreach (self::DASHBOARD_DAYS as $days) {
            $rows[] = [
                'days'    => $days,
                'tickets' => (int) ($r->{'t'.$days} ?? 0),
                'closed'  => (int) ($r->{'c'.$days} ?? 0),
                'url'     => route('refreshglobal.flows', ['days' => $days]),
            ];
        }

        return $rows;
    }

    /** Flows recorded in these mailboxes (permanent ones), for the filters and "copy of a flow": [id => name]. */
    public static function knownFlows(array $mailboxIds)
    {
        $out = [];
        $rows = \DB::table(self::TABLE)->whereIn('mailbox_id', $mailboxIds ?: [0])->where('temporary', 0)
            ->groupBy('flow_id')->selectRaw('flow_id, MAX(id) as last_id')->get();
        $ids = $rows->pluck('last_id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (\DB::table(self::TABLE)->whereIn('id', $chunk)->get(['flow_id', 'flow_name']) as $r) {
                $out[(int) $r->flow_id] = (string) $r->flow_name;
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }
}
