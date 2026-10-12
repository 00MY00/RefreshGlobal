<?php

namespace Modules\RefreshGlobal\Services;

/**
 * "Automatic folders" of the Workflow Packs module (https://github.com/00MY00/WorkflowPacks, 1.14.0 and later): its
 * rules file the e-mails into folders of the mail server ("[WP] Factures › Fournisseurs") while the tickets stay where
 * they are in FreeScout. Workflow Packs keeps the current folder of each ticket in its table
 * workflowpacks_ticket_folders (documented in its docs/api-dossiers.md), one row per ticket:
 *   conversation_id (unique), mailbox_id, folder ("Factures/Fournisseurs", as in the rules), label ("[WP] Factures ›
 *   Fournisseurs"), path (on the mail server), state (filed = still in the folder | cleaned = removed by its automatic
 *   clean-up), filed_at, cleaned_at.
 *
 * RefreshGlobal only READS it, here only, to:
 *  - list the folders in the views panel, one entry per folder for all the user's mailboxes, with the number of
 *    tickets of the current list in it (sub-folders included);
 *  - filter the "All mailboxes" list on a folder (URL parameter wf, sub-folders included);
 *  - show the folder of each ticket (badge) and an overview page.
 * Nothing is shown when Workflow Packs is not active or its table is missing (older version).
 */
class AutoFolders
{
    const TABLE = 'workflowpacks_ticket_folders';
    const ALIAS = 'workflowpacks';
    const COLUMNS = ['conversation_id', 'mailbox_id', 'folder', 'label', 'path', 'state', 'filed_at'];
    /** separator of the levels in the labels of Workflow Packs (FolderPath::label()) */
    const SEP = ' › ';
    const MAX_LENGTH = 191;

    /** Tests: the module is considered active (the table is created by the test). */
    public static $assumeActive = false;

    protected static $available = null;
    protected static $cleanups = null;

    public static function available()
    {
        if (self::$available === null) {
            try {
                self::$available = (self::$assumeActive || \App\Module::isActive(self::ALIAS))
                    && \Schema::hasTable(self::TABLE) && \Schema::hasColumns(self::TABLE, self::COLUMNS);
            } catch (\Throwable $e) {
                self::$available = false;
            }
        }

        return self::$available;
    }

    /** Folder icon (inline SVG: an emoji is not drawn on systems without an emoji font). */
    public static function icon()
    {
        return '<svg class="rg-folder-svg" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">'
            .'<path fill="currentColor" d="M1.5 3.5A1.5 1.5 0 0 1 3 2h3.1a1.5 1.5 0 0 1 1.06.44L8.2 3.5H13a1.5 1.5 0 0 1 1.5 1.5v7.5A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5z"/></svg>';
    }

    /** Forget what was read (tests). */
    public static function reset()
    {
        self::$available = null;
        self::$cleanups = null;
    }

    /** Folder as written in the rules ("Factures / Fournisseurs" -> "Factures/Fournisseurs"), '' when not valid. */
    public static function normalize($value)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $parts = array_values(array_filter(array_map('trim', explode('/', (string) $value)), 'strlen'));
        $folder = implode('/', $parts);

        return mb_strlen($folder) <= self::MAX_LENGTH ? $folder : '';
    }

    /** Tickets in the folder $folder or one of its sub-folders (rows still "filed"). */
    public static function applyFilter($query, $folder)
    {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $folder).'/%';
        $query->whereIn('conversations.id', function ($s) use ($folder, $like) {
            $s->select('conversation_id')->from(self::TABLE)->where('state', 'filed')
                ->where(function ($w) use ($folder, $like) {
                    $w->where('folder', $folder)->orWhere('folder', 'like', $like);
                });
        });
    }

    /**
     * Number of tickets of a list per folder (exact folder, without its sub-folders): one grouped query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base the list (rights + filters, without the folder filter)
     * @return array [folder => n]
     */
    public static function counts($base)
    {
        $rows = $base->toBase()
            ->join(self::TABLE.' as rgwf', 'rgwf.conversation_id', '=', 'conversations.id')
            ->where('rgwf.state', 'filed')
            ->select('rgwf.folder as k', \DB::raw('COUNT(*) as n'))
            ->groupBy('rgwf.folder')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->k] = (int) $row->n;
        }

        return $out;
    }

    /**
     * Folders holding tickets the user can see: [folder => label] (the newest label when the prefix changed).
     *
     * @param \Illuminate\Database\Eloquent\Builder $base the user's tickets, rights only (GlobalTicketQuery::base())
     */
    public static function folders($base)
    {
        $out = [];
        $rows = $base->toBase()
            ->join(self::TABLE.' as rgwf', 'rgwf.conversation_id', '=', 'conversations.id')
            ->where('rgwf.state', 'filed')
            ->select('rgwf.folder', 'rgwf.label', \DB::raw('MAX(rgwf.updated_at) as u'))
            ->groupBy('rgwf.folder', 'rgwf.label')->orderBy('u')->get();
        foreach ($rows as $row) {
            if ((string) $row->folder !== '') {
                $out[(string) $row->folder] = (string) $row->label;
            }
        }

        return $out;
    }

    /**
     * Folder tree for the views panel: every folder and parent folder, sorted, each with its number of tickets
     * (its sub-folders included).
     *
     * @param array $folders [folder => label] (folders())
     * @param array $counts  [folder => n] (counts())
     * @return array[] [['folder', 'name' (its level of the label), 'label', 'depth' (0..), 'count'], …]
     */
    public static function tree(array $folders, array $counts)
    {
        $nodes = [];
        foreach ($folders as $folder => $label) {
            $parts = explode('/', $folder);
            $names = explode(self::SEP, $label);
            if (count($names) !== count($parts)) {
                $names = $parts; // label not made of the same levels: the folder's own names
            }
            for ($i = 1; $i <= count($parts); $i++) {
                $key = implode('/', array_slice($parts, 0, $i));
                if (!isset($nodes[$key])) {
                    $nodes[$key] = ['folder' => $key, 'name' => $names[$i - 1], 'label' => implode(self::SEP, array_slice($names, 0, $i)),
                        'depth' => $i - 1, 'count' => 0];
                }
            }
        }
        foreach ($counts as $folder => $n) {
            $parts = explode('/', (string) $folder);
            for ($i = 1; $i <= count($parts); $i++) {
                $key = implode('/', array_slice($parts, 0, $i));
                if (isset($nodes[$key])) {
                    $nodes[$key]['count'] += (int) $n;
                }
            }
        }
        uksort($nodes, function ($a, $b) {
            // "/" sorted before any character: a folder comes right before its sub-folders
            return strnatcasecmp(str_replace('/', "\x01", $a), str_replace('/', "\x01", $b));
        });

        return array_values($nodes);
    }

    /** Label of the folder of each of these tickets still in one (badge): [conversation_id => label]. */
    public static function labels(array $conversation_ids)
    {
        $out = [];
        $ids = array_values(array_filter(array_map('intval', $conversation_ids)));
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (\DB::table(self::TABLE)->whereIn('conversation_id', $chunk)->where('state', 'filed')->get(['conversation_id', 'label']) as $row) {
                $out[(int) $row->conversation_id] = (string) $row->label;
            }
        }

        return $out;
    }

    /** Label of a folder from the tree, or the folder itself. */
    public static function labelOf($folder, array $folders)
    {
        foreach (self::tree($folders, []) as $node) {
            if ($node['folder'] === $folder) {
                return $node['label'];
            }
        }

        return $folder;
    }

    /**
     * Overview: per folder, its tickets (all statuses but spam, with the user's rights), open ones, per mailbox, the
     * last filing and the automatic clean-up set in Workflow Packs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base the user's tickets (GlobalTicketQuery::base())
     * @param \App\Mailbox[] $mailboxes the user's mailboxes (names, for the order)
     * @return array[] [['folder', 'label', 'depth', 'total', 'open', 'last', 'mailboxes' => [mailbox id => n], 'cleanup'], …]
     */
    public static function overview($base, $mailboxes)
    {
        $names = [];
        foreach ($mailboxes as $m) {
            $names[(int) $m->id] = (string) $m->name;
        }
        $rows = $base->toBase()
            ->join(self::TABLE.' as rgwf', 'rgwf.conversation_id', '=', 'conversations.id')
            ->where('rgwf.state', 'filed')
            ->select('rgwf.folder', 'rgwf.label', 'conversations.mailbox_id', 'conversations.status',
                \DB::raw('COUNT(*) as n'), \DB::raw('MAX(rgwf.filed_at) as last'))
            ->groupBy('rgwf.folder', 'rgwf.label', 'conversations.mailbox_id', 'conversations.status')
            ->get();
        $open = [\App\Conversation::STATUS_ACTIVE, \App\Conversation::STATUS_PENDING];
        $out = [];
        foreach ($rows as $row) {
            $folder = (string) $row->folder;
            if (!isset($out[$folder])) {
                $out[$folder] = ['folder' => $folder, 'label' => (string) $row->label, 'depth' => substr_count($folder, '/'), 'total' => 0, 'open' => 0, 'last' => '',
                    'mailboxes' => [], 'cleanup' => self::cleanup($folder)];
            }
            $n = (int) $row->n;
            $out[$folder]['total'] += $n;
            if (in_array((int) $row->status, $open, true)) {
                $out[$folder]['open'] += $n;
            }
            $id = (int) $row->mailbox_id;
            $out[$folder]['mailboxes'][$id] = ($out[$folder]['mailboxes'][$id] ?? 0) + $n;
            if ((string) $row->last > $out[$folder]['last']) {
                $out[$folder]['last'] = (string) $row->last;
            }
        }
        uksort($out, function ($a, $b) {
            return strnatcasecmp(str_replace('/', "\x01", $a), str_replace('/', "\x01", $b));
        });
        // mailboxes by name
        foreach ($out as &$f) {
            uksort($f['mailboxes'], function ($a, $b) use ($names) {
                return strnatcasecmp($names[$a] ?? '', $names[$b] ?? '') ?: $a - $b;
            });
        }
        unset($f);

        return array_values($out);
    }

    /**
     * Automatic clean-up set in Workflow Packs for this folder (its table workflowpacks_retention, keyed by the folder
     * as in the rules), or null.
     *
     * @return array|null ['number', 'metric' (i minutes | h hours | d days), 'mode' (trash | delete)]
     */
    public static function cleanup($folder)
    {
        if (self::$cleanups === null) {
            $rows = [];
            try {
                if (\Schema::hasTable('workflowpacks_retention')) {
                    foreach (\DB::table('workflowpacks_retention')->get(['folder', 'number', 'metric', 'mode']) as $r) {
                        $rows[mb_strtolower((string) $r->folder)] = ['number' => (int) $r->number, 'metric' => (string) $r->metric, 'mode' => (string) $r->mode];
                    }
                }
            } catch (\Throwable $e) {
                $rows = [];
            }
            self::$cleanups = $rows;
        }

        return self::$cleanups[mb_strtolower((string) $folder)] ?? null;
    }

    /**
     * Folder of the mail server where Workflow Packs filed the e-mail of each of these tickets (still there):
     * [conversation_id => path]. Read when a ticket is deleted for good (Services/MailServerTrash.php), before
     * Workflow Packs removes its row (it does so at a later priority of the same action).
     */
    public static function paths(array $conversation_ids)
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        try {
            foreach (array_chunk(array_values(array_filter(array_map('intval', $conversation_ids))), 500) as $chunk) {
                foreach (\DB::table(self::TABLE)->whereIn('conversation_id', $chunk)->where('state', 'filed')->where('path', '!=', '')->get(['conversation_id', 'path']) as $row) {
                    $out[(int) $row->conversation_id] = (string) $row->path;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $out;
    }
}
