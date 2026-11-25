<?php

namespace Modules\RefreshGlobal\Services;

use App\Conversation;

/**
 * Tickets waiting in a flow of Workflow Packs (1.28.0): when the flow goes on and what it does then, for the badge of
 * the lists and of the ticket page. Workflow Packs has no API for this: its tables are only READ, here only
 * (workflowpacks_flow_runs, workflowpacks_flow_versions, workflowpacks_flows; INTEGRATION_MAP.md). No code of it is called.
 *
 * Nothing is shown without Workflow Packs 3.20+, with its engine off, or when a table or column is missing. The texts of
 * notes, notifications and conditions are never read out: only the kind of the next step.
 */
class FlowTimers
{
    const RUNS = 'workflowpacks_flow_runs';
    const RUN_COLUMNS = ['id', 'flow_id', 'version_id', 'conversation_id', 'block', 'state', 'wake_at', 'resume'];
    const VERSIONS = 'workflowpacks_flow_versions';
    const FLOWS = 'workflowpacks_flows';
    /** waits shown for one list, for one ticket */
    const MAX_RUNS = 500;
    const MAX_TICKET = 5;
    /** different next steps shown, steps of a chain, blocks followed (a loop can not go on for ever) */
    const MAX_STEPS = 5;
    const MAX_CHAIN = 4;
    const MAX_BLOCKS = 60;

    const STATUSES = ['active' => Conversation::STATUS_ACTIVE, 'pending' => Conversation::STATUS_PENDING,
        'closed' => Conversation::STATUS_CLOSED, 'spam' => Conversation::STATUS_SPAM];

    protected static $users = [];
    /** @var array conversation id => waits, read once per request */
    protected static $read = [];

    public static function available()
    {
        try {
            return WorkflowPacksLink::available() && SchemaCache::has(self::RUNS, self::RUN_COLUMNS)
                && SchemaCache::has(self::VERSIONS, ['id', 'data']) && SchemaCache::has(self::FLOWS, ['id', 'name']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function reset()
    {
        self::$read = [];
        self::$users = [];
    }

    /**
     * @return array [conversation id => [['flow' => name, 'at' => timestamp, 'steps' => string[], 'depends' => bool], …]],
     *               the earliest first; only the tickets that wait
     */
    public static function forConversations(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $new = array_values(array_diff($ids, array_keys(self::$read)));
        if ($new && self::available()) {
            try {
                self::$read += self::load($new) + array_fill_keys($new, []);
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [flow-timers] '.get_class($e));
            }
        }

        return array_filter(array_intersect_key(self::$read, array_flip($ids)));
    }

    protected static function load(array $ids)
    {
        $runs = \DB::table(self::RUNS)->whereIn('conversation_id', $ids)->where('state', 'waiting')->whereNotNull('wake_at')
            ->orderBy('wake_at')->limit(self::MAX_RUNS)->get(self::RUN_COLUMNS);
        if (!count($runs)) {
            return [];
        }
        $versions = [];
        foreach (\DB::table(self::VERSIONS)->whereIn('id', $runs->pluck('version_id')->unique()->all())->get(['id', 'data']) as $v) {
            $versions[$v->id] = self::graph($v->data);
        }
        $names = \DB::table(self::FLOWS)->whereIn('id', $runs->pluck('flow_id')->unique()->all())->pluck('name', 'id');
        $out = [];
        foreach ($runs as $run) {
            $id = (int) $run->conversation_id;
            if (!isset($versions[$run->version_id]) || count($out[$id] ?? []) >= self::MAX_TICKET) {
                continue;
            }
            $next = self::next($versions[$run->version_id], (string) $run->block, $run->resume);
            $out[$id][] = ['flow' => (string) ($names[$run->flow_id] ?? ''), 'at' => \Carbon\Carbon::parse($run->wake_at)->getTimestamp()] + $next;
        }

        return $out;
    }

    /** A published version ({blocks, links}) as ['blocks' => id => block, 'links' => "from|port" => to]. */
    public static function graph($data)
    {
        $data = is_array($data) ? $data : json_decode((string) $data, true);
        $graph = ['blocks' => [], 'links' => []];
        foreach (is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $b) {
            if (is_array($b) && is_scalar($b['id'] ?? null)) {
                $graph['blocks'][(string) $b['id']] = ['type' => is_string($b['type'] ?? null) ? $b['type'] : '', 'params' => is_array($b['params'] ?? null) ? $b['params'] : []];
            }
        }
        foreach (is_array($data['links'] ?? null) ? $data['links'] : [] as $l) {
            if (is_array($l) && is_scalar($l['from'] ?? null) && is_scalar($l['to'] ?? null)) {
                $port = is_scalar($l['port'] ?? null) ? $l['port'] : 'next';
                $graph['links'][$l['from'].'|'.$port] = (string) $l['to'];
            }
        }

        return $graph;
    }

    /**
     * What a wait leads to. $resume: output followed on wake-up; null: the block itself runs again (Engine::walk()).
     *
     * @return array ['steps' => texts of the possible next steps, 'depends' => conditions decide between them]
     */
    public static function next(array $graph, $block, $resume)
    {
        $steps = [];
        $depends = false;
        $seen = [];
        $walk = function ($id) use (&$walk, &$steps, &$depends, &$seen, $graph) {
            if (isset($seen[$id]) || count($seen) >= self::MAX_BLOCKS) {
                return;
            }
            $b = $id !== null && isset($graph['blocks'][$id]) ? $graph['blocks'][$id] : null;
            $seen[$id] = true;
            if (!$b) {
                $steps[] = __('refreshglobal::flows.timer_end');

                return;
            }
            if (strpos($b['type'], 'condition.') === 0) {
                $depends = true;
                foreach (['yes', 'no'] as $port) {
                    if (isset($graph['links'][$id.'|'.$port])) {
                        $walk($graph['links'][$id.'|'.$port]);
                    } else {
                        $steps[] = __('refreshglobal::flows.timer_end');
                    }
                }

                return;
            }
            // actions one after the other, up to a wait or a condition
            $chain = [];
            while ($b && count($chain) < self::MAX_CHAIN && strpos($b['type'], 'condition.') !== 0) {
                $chain[] = self::label($b);
                $id = $graph['links'][$id.'|next'] ?? null;
                if (strpos($b['type'], 'wait.') === 0 || $id === null) {
                    break;
                }
                $b = $graph['blocks'][$id] ?? null;
            }
            $steps[] = implode(' → ', $chain);
        };
        $walk($resume === null ? $block : ($graph['links'][$block.'|'.$resume] ?? ''));

        return ['steps' => array_slice(array_values(array_unique($steps)), 0, self::MAX_STEPS), 'depends' => $depends];
    }

    /** A block in a few words, in the language of the user. */
    protected static function label(array $block)
    {
        $p = $block['params'];
        $text = function ($key) use ($p) {
            return is_scalar($p[$key] ?? null) ? (string) $p[$key] : '';
        };
        switch ($block['type']) {
            case 'action.status':
                $status = self::STATUSES[$text('status')] ?? null;

                return $status === null ? __('refreshglobal::flows.timer_other') : __('refreshglobal::flows.timer_status', ['status' => Conversation::statusCodeToName($status)]);
            case 'action.assign':
                return (int) $text('user') > 0 ? __('refreshglobal::flows.timer_assign', ['user' => self::user((int) $text('user'))]) : __('refreshglobal::flows.timer_unassign');
            case 'action.delete':
                return __('refreshglobal::flows.timer_delete');
            case 'action.imap_folder':
                return __('refreshglobal::flows.timer_folder', ['folder' => mb_substr(str_replace('/', AutoFolders::SEP, $text('path')), 0, 80)]);
            case 'action.note':
                return __('refreshglobal::flows.timer_note');
            case 'action.push':
                return __('refreshglobal::flows.timer_push');
        }

        return __('refreshglobal::flows.'.(strpos($block['type'], 'wait.') === 0 ? 'timer_wait' : 'timer_other'));
    }

    protected static function user($id)
    {
        if (!isset(self::$users[$id])) {
            $user = \App\User::find($id);
            self::$users[$id] = $user ? $user->getFullName() : '#'.$id;
        }

        return self::$users[$id];
    }

    /** "2 d 3 h", "1 h 05", "30 min" (same rules in Public/js/shell.js, which keeps it up to date). */
    public static function left($seconds)
    {
        $minutes = (int) ceil($seconds / 60);
        if ($minutes < 1) {
            return __('refreshglobal::flows.timer_now');
        }
        if ($minutes < 60) {
            return $minutes.' min';
        }
        if ($minutes < 2880) {
            return floor($minutes / 60).' h'.($minutes % 60 ? ' '.sprintf('%02d', $minutes % 60) : '');
        }
        $hours = (int) floor($minutes / 60);

        return floor($hours / 24).' '.__('refreshglobal::flows.timer_day').($hours % 24 ? ' '.($hours % 24).' h' : '');
    }

    /** Badge of a ticket ('' when it does not wait). $focus: reached with the keyboard (not inside the link of a list). */
    public static function badge(array $waits, $focus = false)
    {
        if (!$waits) {
            return '';
        }
        $first = $waits[0];
        $what = count($waits) === 1 && count($first['steps']) === 1 && mb_strlen($first['steps'][0]) <= 40 ? ' → '.$first['steps'][0] : '';
        // class rf-badge: Refresh's phone version makes a pill with the text
        return '<span class="rf-badge rg-mailbox-badge rg-timer" data-rg-timer="'.e(json_encode($waits, JSON_UNESCAPED_UNICODE)).'" data-rg-what="'.e($what).'"'
            .($focus ? ' tabindex="0" role="button"' : '').'>'.e(self::left($first['at'] - time()).$what).'</span>';
    }

    /** Texts for Public/js/shell.js. */
    public static function texts()
    {
        $out = [];
        foreach (['title', 'flow', 'depends', 'now', 'day', 'in'] as $key) {
            $out[$key] = __('refreshglobal::flows.timer_'.$key);
        }

        return $out;
    }
}
