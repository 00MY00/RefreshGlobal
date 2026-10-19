<?php

namespace Modules\RefreshGlobal\Services;

use App\User;
use Modules\RefreshGlobal\Entities\SavedView;

/**
 * Saved views of the "All mailboxes" page: private, shared with chosen users, or for everyone.
 *
 * A view is a definition only (filters + sort). Loading one goes:
 *   definition (versioned, upgraded here) -> input of GlobalTicketQuery::normalize() (the viewer's rights: mailboxes
 *   he can not see are dropped) -> GlobalTicketQuery (the same query as the page, the counters and the export).
 * So a view never grants access to a mailbox and there is no second ticket query. Dynamic values are stored as tokens
 * and resolved for the person looking at the list: "assignee" = "me" is the viewer (GlobalTicketQuery::base(), user
 * of MailboxAccess), never the author of the view; "none" = unassigned.
 *
 * Who may do what (checked here, on every request; a view id from a request is never trusted):
 *   see       owner; everyone for "global"; the chosen users for "users"; an administrator for any shared view
 *   edit      (filters, name) owner; administrator for a shared view; a chosen user given "may edit" by an admin
 *   manage    (sharing, visibility, delete) owner; administrator for a shared view
 *   duplicate whoever sees it, when the view allows it (always for its owner and administrators)
 *   publish   for everyone: every user, or administrators only (setting Settings::VIEWS_GLOBAL_ADMINS)
 * Pinned / "my default" are per-user preferences (refreshglobal_view_prefs) and never change the view itself.
 *
 * Sharing with a team: FreeScout has no team of its own (teams of the paid Teams module are users of type "robot",
 * app/User.php:57, whose members the module can not read without that module's code), so it is not offered;
 * SavedView::SHARED_TEAM is kept for it.
 */
class SavedViews
{
    const SCHEMA_VERSION = 2;
    /** Visible views of a user are kept 1 minute; any change of a view (or of its sharing) clears them for everyone. */
    const CACHE_MINUTES = 1;
    const VERSION_KEY = 'refreshglobal.views_version';
    const EXPORT_FORMAT = 'refreshglobal-view';

    /** @var bool|null */
    protected static $available;

    /**
     * Dynamic values of a definition, resolved for the viewer when the list is shown (more can be added here:
     * "my team", "current mailbox"…). filter => [token => label key].
     */
    public static function dynamicValues()
    {
        return ['assignee' => ['me' => 'refreshglobal::messages.me', 'none' => 'refreshglobal::messages.unassigned']];
    }

    /** Tables and columns of 1.15.0 present (migration run); otherwise the views are disabled, the list works. */
    public static function available()
    {
        if (self::$available === null) {
            try {
                self::$available = \Schema::hasTable('refreshglobal_saved_views')
                    && \Schema::hasColumn('refreshglobal_saved_views', 'visibility')
                    && \Schema::hasTable('refreshglobal_view_users')
                    && \Schema::hasTable('refreshglobal_view_prefs')
                    && \Schema::hasTable('refreshglobal_view_audit');
            } catch (\Throwable $e) {
                self::$available = false;
            }
        }

        return self::$available;
    }

    public static function reset()
    {
        self::$available = null;
    }

    /* ------------------------------------------------------------------ definition */

    /**
     * Definition of a view, always in the current format:
     *   ['schema_version' => 2, 'filters' => [mailboxes, status, assignee, q, rview, wfolder, mailtag],
     *    'sorting' => [field, direction], 'display' => [] (reserved), 'corrupt' => bool]
     * Version 1 (1.0 to 1.14: flat normalized filters with sort / order) is upgraded on the fly; an unreadable
     * definition gives an empty one (the whole list) with 'corrupt' => true, never an error.
     */
    public static function definition(SavedView $view)
    {
        $raw = $view->filters;
        if (!is_array($raw)) {
            return self::emptyDefinition(true);
        }
        $version = (int) ($view->schema_version ?: 1);
        if ($version >= 2 && isset($raw['filters']) && is_array($raw['filters'])) {
            $filters = $raw['filters'];
            $sorting = isset($raw['sorting']) && is_array($raw['sorting']) ? $raw['sorting'] : [];
            $display = isset($raw['display']) && is_array($raw['display']) ? $raw['display'] : [];
        } else {
            // schema 1: the normalized filters themselves
            $filters = $raw;
            $sorting = ['field' => $raw['sort'] ?? 'updated', 'direction' => $raw['order'] ?? 'desc'];
            $display = [];
        }

        return self::build($filters, $sorting, $display);
    }

    /** Definition of the filters shown now (output of GlobalTicketQuery::normalize()). */
    public static function fromFilters(array $f)
    {
        return self::build($f, ['field' => $f['sort'] ?? 'updated', 'direction' => $f['order'] ?? 'desc'], []);
    }

    /** The definition as the page's input (URL parameter names), for GlobalTicketQuery::normalize(). */
    public static function input(array $def)
    {
        $f = $def['filters'];

        return [
            'mb'       => $f['mailboxes'],
            'status'   => $f['status'],
            'assignee' => $f['assignee'],
            'q'        => $f['q'],
            'rv'       => $f['rview'],
            'wf'       => $f['wfolder'],
            'mt'       => $f['mailtag'],
            'sort'     => $def['sorting']['field'],
            'order'    => $def['sorting']['direction'],
        ];
    }

    /** Filters of a view for this viewer: his rights applied (GlobalTicketQuery::normalize()). */
    public static function filtersFor(SavedView $view, MailboxAccess $access)
    {
        return GlobalTicketQuery::normalize(self::input(self::definition($view)), $access);
    }

    /** True when the filters shown are not the ones of the view (the user changed something after opening it). */
    public static function differs(array $shown, array $of_view)
    {
        $clean = function (array $f) {
            unset($f['dropped_mailboxes']);
            sort($f['mailboxes']);
            sort($f['status']);
            ksort($f);

            return $f;
        };

        return $clean($shown) != $clean($of_view);
    }

    protected static function build(array $filters, array $sorting, array $display)
    {
        $scalar = function ($v) {
            return is_scalar($v) ? (string) $v : '';
        };
        $ints = function ($v) {
            $out = [];
            foreach ((array) $v as $x) {
                if (is_scalar($x) && preg_match('/^\d{1,10}$/', (string) $x)) {
                    $out[] = (int) $x;
                }
            }

            return array_values(array_unique($out));
        };
        $field = $scalar($sorting['field'] ?? 'updated');

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'filters'        => [
                'mailboxes' => $ints($filters['mailboxes'] ?? ($filters['mb'] ?? [])),
                'status'    => $ints($filters['status'] ?? []),
                'assignee'  => $scalar($filters['assignee'] ?? ''),
                'q'         => $scalar($filters['q'] ?? ''),
                'rview'     => $scalar($filters['rview'] ?? ($filters['rv'] ?? '')),
                'wfolder'   => $scalar($filters['wfolder'] ?? ($filters['wf'] ?? '')),
                'mailtag'   => $scalar($filters['mailtag'] ?? ($filters['mt'] ?? '')),
            ],
            'sorting'        => [
                'field'     => array_key_exists($field, GlobalTicketQuery::sorts()) ? $field : 'updated',
                'direction' => $scalar($sorting['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            ],
            'display'        => $display,
            'corrupt'        => false,
        ];
    }

    protected static function emptyDefinition($corrupt = false)
    {
        $def = self::build([], [], []);
        $def['corrupt'] = $corrupt;

        return $def;
    }

    /** What is stored: the definition without the 'corrupt' flag. */
    protected static function stored(array $def)
    {
        unset($def['corrupt']);

        return $def;
    }

    /**
     * Parts of a view the viewer gets without (shown above the list): mailboxes that no longer exist or that he can not
     * see, an agent that no longer exists, an automatic folder / origin tag / Refresh view no longer available, an
     * unreadable definition. Message keys => count.
     */
    public static function issues(SavedView $view, array $filters)
    {
        $def = self::definition($view);
        $out = [];
        if ($def['corrupt']) {
            $out['view_issue_corrupt'] = 1;
        }
        $wanted = $def['filters']['mailboxes'];
        if ($wanted) {
            $existing = array_map('intval', \App\Mailbox::whereIn('id', $wanted)->pluck('id')->all());
            $missing = count(array_diff($wanted, $existing));
            $hidden = count(array_diff($existing, $filters['mailboxes']));
            if ($missing) {
                $out['view_issue_mailbox_missing'] = $missing;
            }
            if ($hidden) {
                $out['view_issue_mailbox_hidden'] = $hidden;
            }
        }
        $assignee = $def['filters']['assignee'];
        if (preg_match('/^[1-9]\d{0,9}$/', $assignee) && !User::nonDeleted()->where('id', (int) $assignee)->exists()) {
            $out['view_issue_user_missing'] = 1;
        }
        foreach (['wfolder' => 'view_issue_folder', 'mailtag' => 'view_issue_tag', 'rview' => 'view_issue_rview'] as $key => $message) {
            if ($def['filters'][$key] !== '' && ($filters[$key] ?? '') === '') {
                $out[$message] = 1;
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ who sees what */

    /**
     * Views the user can open, as plain rows (cached 1 minute per user, cleared by any change of a view):
     * id, name, owner_id, owner_name, owner_deleted, visibility, section (mine | shared | global), sort_order,
     * allow_duplicate, default_scope, shared_to_me, share_edit, pinned, my_default, updated_at, created_at.
     * Three queries whatever the number of views: views (+ the user's share), owners, the user's preferences.
     */
    public static function visibleFor($user)
    {
        if (!$user || !self::available()) {
            return [];
        }
        $key = 'refreshglobal.visible_views.'.(int) $user->id.'.'.self::version();
        try {
            return \Cache::remember($key, self::CACHE_MINUTES, function () use ($user) {
                return self::loadVisible($user);
            });
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [views] '.$e->getMessage());

            return [];
        }
    }

    protected static function loadVisible($user)
    {
        $uid = (int) $user->id;
        $rows = \DB::table('refreshglobal_saved_views as v')
            ->leftJoin('refreshglobal_view_users as vu', function ($join) use ($uid) {
                $join->on('vu.view_id', '=', 'v.id')->where('vu.user_id', '=', $uid);
            })
            ->where(function ($w) use ($uid) {
                $w->where('v.user_id', $uid)
                    ->orWhere('v.visibility', SavedView::GLOBAL_VIEW)
                    ->orWhere(function ($x) {
                        $x->where('v.visibility', SavedView::SHARED_USERS)->whereNotNull('vu.id');
                    });
            })
            ->orderBy('v.sort_order')->orderBy('v.name')->orderBy('v.id')
            ->get(['v.id', 'v.name', 'v.user_id', 'v.visibility', 'v.sort_order', 'v.allow_duplicate', 'v.default_scope',
                'v.updated_at', 'v.created_at', 'vu.id as share_id', 'vu.can_edit as share_edit']);
        if (!count($rows)) {
            return [];
        }
        $owners = self::userNames($rows->pluck('user_id')->unique()->all());
        $prefs = \DB::table('refreshglobal_view_prefs')->where('user_id', $uid)
            ->whereIn('view_id', $rows->pluck('id')->all())->get()->keyBy('view_id');

        $out = [];
        foreach ($rows as $r) {
            $owner = $owners[(int) $r->user_id] ?? null;
            $pref = $prefs[$r->id] ?? null;
            $visibility = in_array($r->visibility, [SavedView::SHARED_USERS, SavedView::GLOBAL_VIEW], true) ? $r->visibility : SavedView::PRIVATE_VIEW;
            $out[] = [
                'id'              => (int) $r->id,
                'name'            => (string) $r->name,
                'owner_id'        => (int) $r->user_id,
                'owner_name'      => $owner ? $owner['name'] : '',
                'owner_deleted'   => !$owner || $owner['deleted'],
                'visibility'      => $visibility,
                'section'         => (int) $r->user_id === $uid ? 'mine' : ($visibility === SavedView::GLOBAL_VIEW ? 'global' : 'shared'),
                'sort_order'      => (int) $r->sort_order,
                'allow_duplicate' => (bool) $r->allow_duplicate,
                'default_scope'   => (string) $r->default_scope,
                'shared_to_me'    => $r->share_id !== null,
                'share_edit'      => (bool) $r->share_edit,
                'pinned'          => $pref ? (bool) $pref->pinned : false,
                'my_default'      => $pref ? (bool) $pref->is_default : false,
                'updated_at'      => (string) $r->updated_at,
                'created_at'      => (string) $r->created_at,
            ];
        }

        return $out;
    }

    /** [user id => ['name', 'deleted']] in one query (deleted users included, marked). */
    public static function userNames(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (User::whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'email', 'status']) as $u) {
            $out[(int) $u->id] = ['name' => trim($u->getFullName()) ?: (string) $u->email, 'deleted' => $u->isDeleted()];
        }

        return $out;
    }

    /** The view if this user may open it, else null (ids from a request are never trusted). */
    public static function find($id, $user)
    {
        if (!$user || !self::available() || !is_scalar($id) || !preg_match('/^\d{1,10}$/', (string) $id)) {
            return null;
        }
        $view = SavedView::find((int) $id);

        return $view && self::canSee($view, $user) ? $view : null;
    }

    public static function canSee(SavedView $view, $user)
    {
        if (!$user) {
            return false;
        }
        if ((int) $view->user_id === (int) $user->id) {
            return true;
        }
        $visibility = $view->visibilityValue();
        if ($visibility === SavedView::GLOBAL_VIEW || ($view->isShared() && $user->isAdmin())) {
            return true;
        }

        return $visibility === SavedView::SHARED_USERS && self::share($view, $user) !== null;
    }

    /** Filters and name. */
    public static function canEdit(SavedView $view, $user)
    {
        if (!$user || !self::canSee($view, $user)) {
            return false;
        }
        if (self::canManage($view, $user)) {
            return true;
        }
        $share = $view->visibilityValue() === SavedView::SHARED_USERS ? self::share($view, $user) : null;

        return $share !== null && (bool) $share->can_edit;
    }

    /** Sharing, visibility, defaults for others, delete. */
    public static function canManage(SavedView $view, $user)
    {
        return $user && ((int) $view->user_id === (int) $user->id || ($user->isAdmin() && $view->isShared()));
    }

    public static function canDuplicate(SavedView $view, $user)
    {
        return self::canSee($view, $user) && ((int) $view->user_id === (int) $user->id || $user->isAdmin() || $view->allow_duplicate);
    }

    public static function canPublishGlobal($user)
    {
        return $user && ($user->isAdmin() || !Settings::viewsGlobalAdminsOnly());
    }

    /** Defaults for other users and "may edit" for the chosen users: administrators. */
    public static function canAdminister($user)
    {
        return $user && $user->isAdmin();
    }

    protected static function share(SavedView $view, $user)
    {
        return \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->where('user_id', $user->id)->first();
    }

    /**
     * Users a view can be shared with: for an administrator every user (not deleted, not a "robot": workflows, teams),
     * otherwise the users of the mailboxes he works in (FreeScout's own list, User::whichUsersCanView(),
     * app/User.php:1189). The user himself is left out.
     */
    public static function shareableUsers($user)
    {
        if (!$user) {
            return collect([]);
        }
        try {
            $users = $user->isAdmin() ? User::nonDeleted()->get() : collect($user->whichUsersCanView());
        } catch (\Throwable $e) {
            $users = collect([]);
        }

        return $users->filter(function ($u) use ($user) {
            return (int) $u->id !== (int) $user->id && (int) ($u->type ?? User::TYPE_USER) !== User::TYPE_ROBOT && !$u->isDeleted();
        })->sortBy(function ($u) {
            return mb_strtolower($u->getFullName());
        })->values();
    }

    /**
     * Default view for this user: his own choice ("my default"), else a view an administrator set as default for
     * everyone (global) or for the users it is shared with. Null: the whole list.
     */
    public static function defaultFor($user, array $visible = null)
    {
        $visible = $visible === null ? self::visibleFor($user) : $visible;
        foreach ($visible as $v) {
            if ($v['my_default']) {
                return $v['id'];
            }
        }
        foreach ($visible as $v) {
            if (($v['visibility'] === SavedView::GLOBAL_VIEW && $v['default_scope'] === 'all')
                || ($v['visibility'] === SavedView::SHARED_USERS && $v['default_scope'] === 'shared' && $v['shared_to_me'])) {
                return $v['id'];
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ changes */

    /**
     * Creates a view of the filters shown. $options: visibility, users (ids), can_edit, allow_duplicate,
     * default_scope, my_default. Values the user may not set are refused before (controller) and checked again here.
     */
    public static function create($user, $name, array $filters, array $options = [])
    {
        $view = new SavedView();
        $view->user_id = $user->id;
        $view->name = self::cleanName($name);
        $view->filters = self::stored(self::fromFilters($filters));
        $view->schema_version = self::SCHEMA_VERSION;
        $view->visibility = SavedView::PRIVATE_VIEW;
        $view->allow_duplicate = true;
        $view->default_scope = '';
        $view->updated_by = $user->id;
        $view->sort_order = (int) SavedView::where('user_id', $user->id)->max('sort_order') + 10;
        \DB::transaction(function () use ($view, $user, $options) {
            $view->save();
            self::applySharing($view, $user, $options);
            if (!empty($options['my_default'])) {
                self::setPref($user, $view, 'is_default', true);
            }
        });
        self::audit($view, $user, 'created');
        self::bump();

        return $view->fresh();
    }

    /** "Update view": the filters shown become the view's definition (explicit action, edit right checked before). */
    public static function updateDefinition(SavedView $view, $user, array $filters)
    {
        $view->filters = self::stored(self::fromFilters($filters));
        $view->schema_version = self::SCHEMA_VERSION;
        $view->updated_by = $user->id;
        $view->save();
        self::audit($view, $user, 'updated');
        self::bump();
    }

    public static function rename(SavedView $view, $user, $name)
    {
        $old = $view->name;
        $view->name = self::cleanName($name);
        $view->updated_by = $user->id;
        $view->save();
        if ($old !== $view->name) {
            self::audit($view, $user, 'renamed', $old.' → '.$view->name);
        }
        self::bump();
    }

    /** Visibility, users, rights and defaults (manage right checked before). */
    public static function updateSharing(SavedView $view, $user, array $options)
    {
        $before = $view->visibilityValue();
        \DB::transaction(function () use ($view, $user, $options) {
            self::applySharing($view, $user, $options);
        });
        $after = $view->fresh()->visibilityValue();
        self::audit($view, $user, $before !== $after ? 'visibility' : 'shared', $before.' → '.$after, true);
        self::bump();
    }

    protected static function applySharing(SavedView $view, $user, array $options)
    {
        $visibility = $options['visibility'] ?? $view->visibilityValue();
        if (!in_array($visibility, [SavedView::PRIVATE_VIEW, SavedView::SHARED_USERS, SavedView::GLOBAL_VIEW], true)) {
            $visibility = SavedView::PRIVATE_VIEW;
        }
        if ($visibility === SavedView::GLOBAL_VIEW && !self::canPublishGlobal($user)) {
            $visibility = $view->visibilityValue() === SavedView::GLOBAL_VIEW ? SavedView::GLOBAL_VIEW : SavedView::PRIVATE_VIEW;
        }
        $admin = self::canAdminister($user);
        $view->visibility = $visibility;
        if (array_key_exists('allow_duplicate', $options)) {
            $view->allow_duplicate = (bool) $options['allow_duplicate'];
        }
        if ($admin && array_key_exists('default_scope', $options)) {
            $scope = (string) $options['default_scope'];
            $view->default_scope = ($scope === 'all' && $visibility === SavedView::GLOBAL_VIEW) || ($scope === 'shared' && $visibility === SavedView::SHARED_USERS) ? $scope : '';
        } elseif ($visibility === SavedView::PRIVATE_VIEW) {
            $view->default_scope = '';
        }
        $view->updated_by = $user->id;
        $view->save();

        if ($visibility !== SavedView::SHARED_USERS) {
            \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->delete();

            return;
        }
        if (!array_key_exists('users', $options)) {
            return;
        }
        $allowed = self::shareableUsers($user)->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $wanted = array_values(array_intersect(array_map('intval', (array) $options['users']), $allowed));
        // users a non-administrator can not choose (another mailbox) keep their share
        $kept = $admin ? [] : \DB::table('refreshglobal_view_users')->where('view_id', $view->id)
            ->whereNotIn('user_id', $allowed ?: [0])->pluck('user_id')->map(function ($id) {
                return (int) $id;
            })->all();
        $editors = $admin ? array_map('intval', (array) ($options['can_edit'] ?? [])) : null;
        $current = \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->get()->keyBy('user_id');
        \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->whereNotIn('user_id', array_merge($wanted, $kept) ?: [0])->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($wanted as $id) {
            $can_edit = $editors !== null ? in_array($id, $editors, true) : (isset($current[$id]) && $current[$id]->can_edit);
            if (isset($current[$id])) {
                \DB::table('refreshglobal_view_users')->where('id', $current[$id]->id)->update(['can_edit' => $can_edit, 'updated_at' => $now]);
            } else {
                \DB::table('refreshglobal_view_users')->insert(['view_id' => $view->id, 'user_id' => $id, 'can_edit' => $can_edit, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /** Users a view is shared with: [['id', 'name', 'can_edit']], deleted users left out (two queries). */
    public static function sharedWith(SavedView $view)
    {
        $shares = \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->get();

        return self::sharesList($shares);
    }

    /** Shares of several views at once: [view id => [['id', 'name', 'can_edit']]] (two queries). */
    public static function sharedWithMany(array $view_ids)
    {
        if (!$view_ids) {
            return [];
        }
        $out = [];
        $shares = \DB::table('refreshglobal_view_users')->whereIn('view_id', $view_ids)->get();
        $names = self::userNames($shares->pluck('user_id')->all());
        foreach ($shares as $s) {
            $u = $names[(int) $s->user_id] ?? null;
            if ($u && !$u['deleted']) {
                $out[(int) $s->view_id][] = ['id' => (int) $s->user_id, 'name' => $u['name'], 'can_edit' => (bool) $s->can_edit];
            }
        }

        return $out;
    }

    protected static function sharesList($shares)
    {
        $names = self::userNames($shares->pluck('user_id')->all());
        $out = [];
        foreach ($shares as $s) {
            $u = $names[(int) $s->user_id] ?? null;
            if ($u && !$u['deleted']) {
                $out[] = ['id' => (int) $s->user_id, 'name' => $u['name'], 'can_edit' => (bool) $s->can_edit];
            }
        }

        return $out;
    }

    /** "Duplicate to my views": a private copy owned by the user, "Name (copy)". */
    public static function duplicate(SavedView $view, $user)
    {
        $copy = new SavedView();
        $copy->user_id = $user->id;
        $copy->name = self::cleanName(__('refreshglobal::messages.view_copy_name', ['name' => $view->name]));
        $copy->filters = self::stored(self::definition($view));
        $copy->schema_version = self::SCHEMA_VERSION;
        $copy->visibility = SavedView::PRIVATE_VIEW;
        $copy->allow_duplicate = true;
        $copy->default_scope = '';
        $copy->updated_by = $user->id;
        $copy->sort_order = (int) SavedView::where('user_id', $user->id)->max('sort_order') + 10;
        $copy->save();
        self::bump();

        return $copy;
    }

    public static function delete(SavedView $view, $user)
    {
        self::audit($view, $user, 'deleted');
        \DB::transaction(function () use ($view) {
            \DB::table('refreshglobal_view_users')->where('view_id', $view->id)->delete();
            \DB::table('refreshglobal_view_prefs')->where('view_id', $view->id)->delete();
            $view->delete();
        });
        self::bump();
    }

    /** Per-user preference: 'pinned' or 'is_default' (one default per user). */
    public static function setPref($user, SavedView $view, $key, $on)
    {
        if (!in_array($key, ['pinned', 'is_default'], true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if ($key === 'is_default' && $on) {
            \DB::table('refreshglobal_view_prefs')->where('user_id', $user->id)->update(['is_default' => false, 'updated_at' => $now]);
        }
        $row = \DB::table('refreshglobal_view_prefs')->where('user_id', $user->id)->where('view_id', $view->id)->first();
        if ($row) {
            \DB::table('refreshglobal_view_prefs')->where('id', $row->id)->update([$key => (bool) $on, 'updated_at' => $now]);
        } else {
            \DB::table('refreshglobal_view_prefs')->insert(['user_id' => $user->id, 'view_id' => $view->id,
                'pinned' => $key === 'pinned' && $on, 'is_default' => $key === 'is_default' && $on, 'created_at' => $now, 'updated_at' => $now]);
        }
        if ($key === 'is_default') {
            // kept for older module versions (their own default view)
            SavedView::where('user_id', $user->id)->update(['is_default' => false]);
            if ($on && (int) $view->user_id === (int) $user->id) {
                SavedView::where('id', $view->id)->update(['is_default' => true]);
            }
        }
        self::bump();
    }

    /**
     * Moves a view up or down: the user's own views among his own, shared / global views (administrators) among the
     * shared ones. Returns false when the user may not order this view.
     */
    public static function move(SavedView $view, $user, $direction)
    {
        $own = (int) $view->user_id === (int) $user->id;
        if (!$own && !($user->isAdmin() && $view->isShared())) {
            return false;
        }
        $set = $own ? SavedView::where('user_id', $user->id) : SavedView::where('visibility', '!=', SavedView::PRIVATE_VIEW);
        $list = $set->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(['id', 'sort_order']);
        $ids = $list->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $i = array_search((int) $view->id, $ids, true);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return true;
        }
        list($ids[$i], $ids[$j]) = [$ids[$j], $ids[$i]];
        \DB::transaction(function () use ($ids) {
            foreach ($ids as $n => $id) {
                SavedView::where('id', $id)->update(['sort_order' => ($n + 1) * 10]);
            }
        });
        self::bump();

        return true;
    }

    /* ------------------------------------------------------------------ export / import */

    /**
     * Portable definition: mailboxes by e-mail address (ids differ between installations), an agent only as a dynamic
     * value ("me", "none"): no user id leaves the installation.
     */
    public static function export(SavedView $view)
    {
        $def = self::stored(self::definition($view));
        $mailboxes = [];
        if ($def['filters']['mailboxes']) {
            foreach (\App\Mailbox::whereIn('id', $def['filters']['mailboxes'])->get(['id', 'name', 'email']) as $mb) {
                $mailboxes[] = ['email' => (string) $mb->email, 'name' => (string) $mb->name];
            }
        }
        $def['filters']['mailboxes'] = $mailboxes;
        if (!array_key_exists($def['filters']['assignee'], self::dynamicValues()['assignee'])) {
            $def['filters']['assignee'] = '';
        }

        return ['format' => self::EXPORT_FORMAT, 'schema_version' => self::SCHEMA_VERSION, 'name' => $view->name, 'definition' => $def];
    }

    /**
     * Imports an exported view as a private view of the user. Mailboxes are found by e-mail address and kept only when
     * the user can see them. Returns [view, mailboxes left out] or null when the data is not an exported view.
     */
    public static function import(array $data, $user, MailboxAccess $access)
    {
        if (($data['format'] ?? '') !== self::EXPORT_FORMAT || !isset($data['definition']) || !is_array($data['definition'])
            || !isset($data['name']) || !is_scalar($data['name']) || trim((string) $data['name']) === '') {
            return null;
        }
        $def = $data['definition'];
        $filters = isset($def['filters']) && is_array($def['filters']) ? $def['filters'] : [];
        $emails = [];
        foreach ((array) ($filters['mailboxes'] ?? []) as $mb) {
            if (is_array($mb) && isset($mb['email']) && is_scalar($mb['email'])) {
                $emails[] = mb_strtolower((string) $mb['email']);
            }
        }
        $ids = $emails ? array_map('intval', \App\Mailbox::whereIn('email', $emails)->pluck('id')->all()) : [];
        $filters['mailboxes'] = $ids;
        if (!array_key_exists((string) ($filters['assignee'] ?? ''), self::dynamicValues()['assignee'])) {
            $filters['assignee'] = '';
        }
        $sorting = isset($def['sorting']) && is_array($def['sorting']) ? $def['sorting'] : [];
        $built = self::build($filters, $sorting, []);
        $normalized = GlobalTicketQuery::normalize(self::input($built), $access);
        $left_out = count($emails) - count($normalized['mailboxes']);
        $view = self::create($user, (string) $data['name'], $normalized);

        return [$view, max(0, $left_out)];
    }

    /* ------------------------------------------------------------------ audit, cache */

    /**
     * Who did what on a shared view (created, updated, renamed, shared, visibility, deleted): only for views other
     * people see, or when the visibility changes. Opening a view is not recorded.
     */
    public static function audit(SavedView $view, $user, $action, $details = '', $force = false)
    {
        if (!$force && !$view->isShared()) {
            return;
        }
        try {
            \DB::table('refreshglobal_view_audit')->insert([
                'view_id'    => $view->id,
                'view_name'  => mb_substr((string) $view->name, 0, 100),
                'user_id'    => $user ? $user->id : null,
                'action'     => $action,
                'details'    => mb_substr((string) $details, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [views] audit: '.$e->getMessage());
        }
    }

    /** Last entries of the audit (management page of an administrator). */
    public static function auditLog($limit = 50)
    {
        try {
            $rows = \DB::table('refreshglobal_view_audit')->orderBy('id', 'desc')->limit((int) $limit)->get();
        } catch (\Throwable $e) {
            return [];
        }
        $names = self::userNames($rows->pluck('user_id')->all());
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['view' => $r->view_name, 'user' => $names[(int) $r->user_id]['name'] ?? '', 'action' => $r->action, 'details' => $r->details, 'at' => $r->created_at];
        }

        return $out;
    }

    protected static function version()
    {
        try {
            return (string) \Cache::get(self::VERSION_KEY, '0');
        } catch (\Throwable $e) {
            return '0';
        }
    }

    /** Clears the cached lists of every user (a view seen by several users changed). */
    public static function bump()
    {
        try {
            \Cache::forever(self::VERSION_KEY, uniqid('', true));
        } catch (\Throwable $e) {
            // the lists expire after one minute anyway
        }
    }

    protected static function cleanName($name)
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        return mb_substr($name !== '' ? $name : '—', 0, 100);
    }
}
