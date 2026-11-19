<?php

namespace Modules\RefreshGlobal\Services;

use Carbon\Carbon;

/**
 * E-mails no flow of Workflow Packs has acted on, and the temporary flows asked for them (Workflow Packs 3.20+,
 * docs/api-flux.md of Workflow Packs).
 *
 * RefreshGlobal has no definition of its own of an "untreated" ticket: the list is the one of Workflow Packs
 * ("workflowpacks.untreated"), always asked for the mailboxes the user may see only, and limited again by the
 * "only assigned" permission. A temporary flow is offered to administrators only; its list of tickets is computed
 * again on the server when the button is used, never taken from the browser.
 */
class FlowTemp
{
    const TABLE = 'refreshglobal_temp_flows';
    const STATUSES = ['active', 'pending', 'closed'];
    const DEFAULT_PERIOD = 30;
    /** tickets shown on the page */
    const SHOWN = 100;
    const ERRORS = ['engine_off', 'forbidden', 'no_tickets', 'too_many_tickets', 'flow_not_found', 'invalid_flow',
        'confirm_risky_required', 'nothing_to_do', 'internal'];
    const STATES = ['draft', 'running', 'paused', 'stopped', 'waiting', 'done', 'deleted'];

    /** Filters of the page: one allowed mailbox or all of them, statuses, period (days back, 0 = all). */
    public static function filters(array $input, MailboxAccess $access)
    {
        $allowed = $access->allowedIds();
        $mailbox = (int) ($input['mb'] ?? 0);
        $mailbox = $mailbox && in_array($mailbox, $allowed, true) ? $mailbox : 0;
        $statuses = array_values(array_intersect(self::STATUSES, array_map('strval', array_filter((array) ($input['statuses'] ?? []), 'is_scalar'))));
        $period = isset($input['period']) && is_numeric($input['period']) ? (int) $input['period'] : self::DEFAULT_PERIOD;

        return [
            'mailbox'   => $mailbox,
            'mailboxes' => $mailbox ? [$mailbox] : $allowed,
            'statuses'  => $statuses ?: ['active', 'pending'],
            'period'    => max(0, min(FlowChanges::MAX_DAYS, $period)),
        ];
    }

    /**
     * The untreated tickets the user may see.
     *
     * @return array ['total', 'ids' (at most 5000), 'truncated', 'ok' (false: Workflow Packs did not answer)]
     */
    public static function untreated(array $f, MailboxAccess $access)
    {
        $out = ['ok' => true, 'total' => 0, 'ids' => [], 'truncated' => false];
        if (!$f['mailboxes']) {
            return $out;
        }
        $r = WorkflowPacksLink::untreated(['mailboxes' => $f['mailboxes'], 'statuses' => $f['statuses'], 'period' => $f['period'],
            'limit' => WorkflowPacksLink::MAX_TEMP]);
        if (empty($r['ok'])) {
            return ['ok' => false] + $out;
        }
        $ids = array_values(array_filter(array_map('intval', (array) ($r['ids'] ?? []))));
        $total = max(count($ids), (int) ($r['total'] ?? 0));
        // only what the user may see: allowed mailboxes, "only assigned" permission
        $visible = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $q = \App\Conversation::whereIn('id', $chunk)->whereIn('mailbox_id', $f['mailboxes']);
            if ($access->onlyAssigned()) {
                $q->where('user_id', $access->user() ? $access->user()->id : 0);
            }
            foreach ($q->pluck('id') as $id) {
                $visible[] = (int) $id;
            }
        }
        sort($visible);
        $truncated = $total > count($ids);
        // tickets beyond the 5000 read are not checked one by one: with "only assigned", only those read are counted
        $total = $access->onlyAssigned() ? count($visible) : $total - (count($ids) - count($visible));

        return ['ok' => true, 'total' => max($total, count($visible)), 'ids' => $visible, 'truncated' => $truncated];
    }

    /** The first tickets of the list, for the page (newest first). */
    public static function tickets(array $ids)
    {
        if (!$ids) {
            return collect();
        }
        $shown = array_slice(array_reverse($ids), 0, self::SHOWN);

        return \App\Conversation::whereIn('id', $shown)->orderBy('id', 'desc')
            ->get(['id', 'number', 'subject', 'mailbox_id', 'status', 'created_at']);
    }

    /**
     * Asks Workflow Packs for a temporary flow on the untreated tickets of these filters (computed again here).
     *
     * @return array ['ok' => true, 'flow_id', 'edit_url'] or ['ok' => false, 'error' => code, 'edit_url' => '' | draft]
     */
    public static function create($user, MailboxAccess $access, array $input)
    {
        if (!$user || !$user->isAdmin() || (int) $user->status !== \App\User::STATUS_ACTIVE) {
            return ['ok' => false, 'error' => 'forbidden', 'edit_url' => ''];
        }
        $f = self::filters($input, $access);
        $list = self::untreated($f, $access);
        if (!$list['ok']) {
            return ['ok' => false, 'error' => 'internal', 'edit_url' => ''];
        }
        if ($list['truncated'] || count($list['ids']) > WorkflowPacksLink::MAX_TEMP) {
            return ['ok' => false, 'error' => 'too_many_tickets', 'edit_url' => ''];
        }
        if (!$list['ids']) {
            return ['ok' => false, 'error' => 'no_tickets', 'edit_url' => ''];
        }
        $name = isset($input['name']) && is_scalar($input['name']) ? trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $input['name'])) : '';
        $name = mb_substr($name, 0, 100) ?: __('refreshglobal::flows.temp_default_name', ['date' => Carbon::now()->format('Y-m-d')]);
        $request = ['ticket_ids' => $list['ids'], 'name' => $name, 'origin' => WorkflowPacksLink::ORIGIN, 'launch' => false];
        $from = isset($input['from_flow']) && is_scalar($input['from_flow']) ? (int) $input['from_flow'] : 0;
        if ($from > 0) {
            $request['from_flow'] = $from;
        }
        $out = WorkflowPacksLink::tempFlow($request);
        $edit = WorkflowPacksLink::safeUrl($out['edit_url'] ?? '');
        if (empty($out['ok'])) {
            $error = in_array($out['error'] ?? null, self::ERRORS, true) ? $out['error'] : 'internal';

            return ['ok' => false, 'error' => $error, 'edit_url' => $edit];
        }
        $flowId = (int) ($out['flow_id'] ?? 0);
        if ($flowId <= 0 || $edit === '') {
            return ['ok' => false, 'error' => 'internal', 'edit_url' => ''];
        }
        self::remember($flowId, (string) ($out['flow_name'] ?? $name), (int) $user->id, (int) ($out['tickets'] ?? count($list['ids'])),
            in_array($out['state'] ?? null, self::STATES, true) ? $out['state'] : 'draft');

        return ['ok' => true, 'flow_id' => $flowId, 'edit_url' => $edit];
    }

    protected static function remember($flowId, $name, $userId, $tickets, $state)
    {
        if (\DB::table(self::TABLE)->where('flow_id', $flowId)->exists()) {
            return;
        }
        \DB::table(self::TABLE)->insert(['flow_id' => $flowId, 'name' => mb_substr($name, 0, 191), 'user_id' => $userId,
            'tickets' => max(0, $tickets), 'state' => $state, 'created_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    /** Workflow Packs deleted a temporary flow after its run (action workflowpacks.temp_flow_finished). */
    public static function finished($flowId, $summary)
    {
        $flowId = is_numeric($flowId) ? (int) $flowId : 0;
        if ($flowId <= 0 || !is_array($summary)) {
            return;
        }
        $reason = in_array($summary['reason'] ?? null, ['done', 'expired'], true) ? $summary['reason'] : null;
        $handled = isset($summary['handled']) && is_numeric($summary['handled']) ? max(0, (int) $summary['handled']) : null;
        \DB::table(self::TABLE)->where('flow_id', $flowId)->update(['state' => 'deleted', 'reason' => $reason,
            'handled' => $handled, 'finished_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    /**
     * The temporary flows asked from RefreshGlobal (latest first), with their state read from Workflow Packs while
     * they exist.
     */
    public static function recent($limit = 10)
    {
        $rows = [];
        foreach (\DB::table(self::TABLE)->orderBy('id', 'desc')->limit($limit)->get() as $r) {
            $row = ['flow_id' => (int) $r->flow_id, 'name' => (string) $r->name, 'tickets' => (int) $r->tickets,
                'handled' => $r->handled === null ? null : (int) $r->handled, 'state' => (string) $r->state,
                'reason' => $r->reason, 'created_at' => $r->created_at, 'finished_at' => $r->finished_at, 'edit_url' => '',
                'done' => 0, 'total' => 0];
            if (!$r->finished_at) {
                $s = WorkflowPacksLink::tempFlowStatus($r->flow_id);
                $state = in_array($s['state'] ?? null, self::STATES, true) ? $s['state'] : '';
                if ($state !== '') {
                    $row['state'] = $state;
                    $row['done'] = (int) ($s['done'] ?? 0);
                    $row['total'] = (int) ($s['total'] ?? 0);
                    $row['edit_url'] = $state === 'deleted' ? '' : WorkflowPacksLink::safeUrl($s['edit_url'] ?? '');
                    if ($state !== $r->state) {
                        \DB::table(self::TABLE)->where('id', $r->id)->update($state === 'deleted'
                            ? ['state' => $state, 'finished_at' => Carbon::now()->format('Y-m-d H:i:s')] : ['state' => $state]);
                    }
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
