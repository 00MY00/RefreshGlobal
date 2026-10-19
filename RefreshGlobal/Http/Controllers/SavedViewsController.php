<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\SavedViews;

/**
 * Saved views of the "All mailboxes" page: create (private, shared with users, for everyone), update with the filters
 * shown, rename, share, duplicate, pin, set as my default, order, export / import, delete; management page.
 * Plain HTML forms (POST / DELETE with FreeScout's CSRF token), so they work without any script.
 * Every action finds the view through SavedViews::find() (404 when the user can not see it) and checks the right it
 * needs (403); nothing here changes a ticket.
 */
class SavedViewsController extends Controller
{
    /** Filter fields posted with "Save" / "Update view" (the filters shown, cleaned by GlobalTicketQuery::normalize()). */
    protected static function filterRules()
    {
        return [
            'mb'       => 'nullable|array|max:1000',
            'mb.*'     => 'nullable|integer|min:1',
            'status'   => 'nullable|array|max:10',
            'status.*' => 'nullable|integer',
            'assignee' => 'nullable|string|max:20',
            'q'        => 'nullable|string|max:'.GlobalTicketQuery::MAX_QUERY_LENGTH,
            'rv'       => 'nullable|string|max:30',
            'wf'       => 'nullable|string|max:'.\Modules\RefreshGlobal\Services\AutoFolders::MAX_LENGTH,
            'mt'       => 'nullable|string|max:100',
            'sort'     => 'nullable|string|max:20',
            'order'    => 'nullable|string|in:asc,desc',
        ];
    }

    protected static function sharingRules()
    {
        return [
            'visibility'      => 'nullable|string|in:private,users,global',
            'users'           => 'nullable|array|max:1000',
            'users.*'         => 'nullable|integer|min:1',
            'can_edit'        => 'nullable|array|max:1000',
            'can_edit.*'      => 'nullable|integer|min:1',
            'allow_duplicate' => 'nullable|boolean',
            'default_scope'   => 'nullable|string|in:,all,shared',
        ];
    }

    /** Management page: the user's views, the views shared with him, the views for everyone; all shared views for an admin. */
    public function index(Request $request)
    {
        return $this->safely(function () {
            $user = auth()->user();
            if (!SavedViews::available()) {
                return response(view('refreshglobal::views', ['available' => false, 'rows' => [], 'is_admin' => $user->isAdmin(),
                    'shareable' => collect([]), 'audit' => [], 'can_global' => false])->render());
            }
            $visible = SavedViews::visibleFor($user);
            $rows = $visible;
            if ($user->isAdmin()) {
                // every shared / global view, also the ones not shared with this administrator
                $seen = array_column($visible, 'id');
                $others = SavedView::where('visibility', '!=', SavedView::PRIVATE_VIEW)->whereNotIn('id', $seen ?: [0])
                    ->orderBy('sort_order')->orderBy('name')->get();
                $owners = SavedViews::userNames($others->pluck('user_id')->all());
                foreach ($others as $v) {
                    $o = $owners[(int) $v->user_id] ?? null;
                    $rows[] = ['id' => $v->id, 'name' => $v->name, 'owner_id' => $v->user_id, 'owner_name' => $o ? $o['name'] : '',
                        'owner_deleted' => !$o || $o['deleted'], 'visibility' => $v->visibilityValue(), 'section' => 'shared',
                        'sort_order' => $v->sort_order, 'allow_duplicate' => $v->allow_duplicate, 'default_scope' => (string) $v->default_scope,
                        'shared_to_me' => false, 'share_edit' => false, 'pinned' => false, 'my_default' => false,
                        'updated_at' => (string) $v->updated_at, 'created_at' => (string) $v->created_at];
                }
            }
            $shares = SavedViews::sharedWithMany(array_column($rows, 'id'));
            $models = SavedView::whereIn('id', array_column($rows, 'id') ?: [0])->get()->keyBy('id');
            foreach ($rows as &$r) {
                $m = $models[$r['id']] ?? null;
                $r['shared_with'] = $shares[$r['id']] ?? [];
                $r['can_edit'] = $m ? SavedViews::canEdit($m, $user) : false;
                $r['can_manage'] = $m ? SavedViews::canManage($m, $user) : false;
                $r['can_duplicate'] = $m ? SavedViews::canDuplicate($m, $user) : false;
                $r['can_move'] = $m && ((int) $m->user_id === (int) $user->id || ($user->isAdmin() && $m->isShared()));
            }
            unset($r);

            return response(view('refreshglobal::views', [
                'available'  => true,
                'rows'       => $rows,
                'is_admin'   => $user->isAdmin(),
                'shareable'  => SavedViews::shareableUsers($user),
                'audit'      => $user->isAdmin() ? SavedViews::auditLog(30) : [],
                'can_global' => SavedViews::canPublishGlobal($user),
            ])->render());
        });
    }

    public function store(Request $request)
    {
        return $this->safely(function () use ($request) {
            $this->validate($request, ['name' => 'required|string|max:100', 'is_default' => 'nullable|boolean']
                + self::filterRules() + self::sharingRules());
            $user = auth()->user();
            if (!SavedViews::available()) {
                return $this->back(['flash_error' => __('refreshglobal::messages.views_unavailable')]);
            }
            if (SavedView::forUser($user)->count() >= (int) config('refreshglobal.max_saved_views', 50)) {
                return $this->back(['flash_error' => __('refreshglobal::messages.views_limit')]);
            }
            if ($request->input('visibility') === SavedView::GLOBAL_VIEW && !SavedViews::canPublishGlobal($user)) {
                abort(403);
            }

            // Only allowed mailboxes are stored; they are checked again every time the view is loaded, for each viewer.
            $filters = GlobalTicketQuery::normalize($request->all(), new MailboxAccess($user));
            $view = SavedViews::create($user, $request->input('name'), $filters, $this->sharingOptions($request) + [
                'my_default' => (bool) $request->input('is_default'),
            ]);

            return redirect()->route('refreshglobal.tickets', ['view' => $view->id])
                ->with('flash_success', __('refreshglobal::messages.view_saved'));
        });
    }

    /** "Update view": the filters shown replace the view's (never done without this explicit action). */
    public function update(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $this->validate($request, self::filterRules());
            $user = auth()->user();
            $view = $this->findOrFail($id);
            if (!SavedViews::canEdit($view, $user)) {
                abort(403);
            }
            SavedViews::updateDefinition($view, $user, GlobalTicketQuery::normalize($request->all(), new MailboxAccess($user)));

            return redirect()->route('refreshglobal.tickets', ['view' => $view->id])
                ->with('flash_success', __('refreshglobal::messages.view_changes_saved'));
        });
    }

    public function rename(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $this->validate($request, ['name' => 'required|string|max:100']);
            $view = $this->findOrFail($id);
            if (!SavedViews::canEdit($view, auth()->user())) {
                abort(403);
            }
            SavedViews::rename($view, auth()->user(), $request->input('name'));

            return $this->done($request, $view, __('refreshglobal::messages.view_renamed'));
        });
    }

    /** Visibility, users, rights, default for others (owner, or an administrator for a shared view). */
    public function share(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $this->validate($request, self::sharingRules() + ['name' => 'nullable|string|max:100']);
            $user = auth()->user();
            $view = $this->findOrFail($id);
            if (!SavedViews::canManage($view, $user)) {
                abort(403);
            }
            if ($request->input('visibility') === SavedView::GLOBAL_VIEW && $view->visibilityValue() !== SavedView::GLOBAL_VIEW
                && !SavedViews::canPublishGlobal($user)) {
                abort(403);
            }
            if (trim((string) $request->input('name')) !== '') {
                SavedViews::rename($view, $user, $request->input('name'));
            }
            SavedViews::updateSharing($view, $user, $this->sharingOptions($request));

            return $this->done($request, $view, __('refreshglobal::messages.view_shared'));
        });
    }

    /** Toggles "my default view" (a personal choice, also for a shared view). */
    public function setDefault(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $view = $this->findOrFail($id);
            $user = auth()->user();
            $row = self::visibleRow($user, $view);
            $make_default = !($row && $row['my_default']);
            SavedViews::setPref($user, $view, 'is_default', $make_default);

            return $this->done($request, $view, $make_default ? __('refreshglobal::messages.view_default_set') : __('refreshglobal::messages.view_default_unset'));
        });
    }

    public function pin(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $view = $this->findOrFail($id);
            $user = auth()->user();
            $row = self::visibleRow($user, $view);
            $pin = !($row && $row['pinned']);
            SavedViews::setPref($user, $view, 'pinned', $pin);

            return $this->done($request, $view, $pin ? __('refreshglobal::messages.view_pinned') : __('refreshglobal::messages.view_unpinned'));
        });
    }

    public function duplicate(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $user = auth()->user();
            $view = $this->findOrFail($id);
            if (!SavedViews::canDuplicate($view, $user)) {
                abort(403);
            }
            if (SavedView::forUser($user)->count() >= (int) config('refreshglobal.max_saved_views', 50)) {
                return $this->back(['flash_error' => __('refreshglobal::messages.views_limit')]);
            }
            $copy = SavedViews::duplicate($view, $user);

            return redirect()->route('refreshglobal.tickets', ['view' => $copy->id])
                ->with('flash_success', __('refreshglobal::messages.view_duplicated'));
        });
    }

    public function move(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $this->validate($request, ['direction' => 'required|string|in:up,down']);
            $view = $this->findOrFail($id);
            if (!SavedViews::move($view, auth()->user(), $request->input('direction'))) {
                abort(403);
            }

            return redirect()->route('refreshglobal.views');
        });
    }

    /** Definition as a JSON file (mailboxes by address, no user id). */
    public function export(Request $request, $id)
    {
        return $this->safely(function () use ($id) {
            $view = $this->findOrFail($id);
            $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', \Illuminate\Support\Str::ascii($view->name)) ?: 'view';

            return response(json_encode(SavedViews::export($view), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->header('Content-Type', 'application/json; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="refreshglobal-view-'.trim($name, '-').'.json"')
                ->header('X-Content-Type-Options', 'nosniff');
        });
    }

    /** A view exported here or elsewhere becomes a private view of the user. */
    public function import(Request $request)
    {
        return $this->safely(function () use ($request) {
            $this->validate($request, ['file' => 'nullable|file|max:64', 'json' => 'nullable|string|max:65000']);
            $user = auth()->user();
            if (!SavedViews::available()) {
                return $this->back(['flash_error' => __('refreshglobal::messages.views_unavailable')]);
            }
            $text = $request->hasFile('file') ? (string) @file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('json');
            $data = json_decode($text, true);
            if (SavedView::forUser($user)->count() >= (int) config('refreshglobal.max_saved_views', 50)) {
                return redirect()->route('refreshglobal.views')->with('flash_error', __('refreshglobal::messages.views_limit'));
            }
            $result = is_array($data) ? SavedViews::import($data, $user, new MailboxAccess($user)) : null;
            if (!$result) {
                return redirect()->route('refreshglobal.views')->with('flash_error', __('refreshglobal::messages.view_import_invalid'));
            }
            list($view, $left_out) = $result;
            $message = __('refreshglobal::messages.view_imported');
            if ($left_out) {
                $message .= ' '.__('refreshglobal::messages.view_issue_mailbox_hidden', ['count' => $left_out]);
            }

            return redirect()->route('refreshglobal.views')->with('flash_success', $message);
        });
    }

    public function destroy(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $user = auth()->user();
            $view = $this->findOrFail($id);
            if (!SavedViews::canManage($view, $user)) {
                abort(403);
            }
            SavedViews::delete($view, $user);
            if ($request->input('back') === 'manage') {
                return redirect()->route('refreshglobal.views')->with('flash_success', __('refreshglobal::messages.view_deleted'));
            }

            return redirect()->route('refreshglobal.tickets', ['reset' => 1])
                ->with('flash_success', __('refreshglobal::messages.view_deleted'));
        });
    }

    protected function sharingOptions(Request $request)
    {
        $options = [
            'visibility'      => $request->input('visibility', SavedView::PRIVATE_VIEW),
            'users'           => (array) $request->input('users', []),
            'allow_duplicate' => $request->has('allow_duplicate') ? (bool) $request->input('allow_duplicate') : true,
        ];
        if (SavedViews::canAdminister(auth()->user())) {
            $options['can_edit'] = (array) $request->input('can_edit', []);
            $options['default_scope'] = (string) $request->input('default_scope', '');
        }

        return $options;
    }

    /** Row of SavedViews::visibleFor() for this view (preferences of the user), or null. */
    protected static function visibleRow($user, SavedView $view)
    {
        foreach (SavedViews::visibleFor($user) as $row) {
            if ($row['id'] === (int) $view->id) {
                return $row;
            }
        }

        return null;
    }

    protected function findOrFail($id)
    {
        $view = SavedViews::find($id, auth()->user());
        if (!$view) {
            abort(404);
        }

        return $view;
    }

    /** Back to the management page when the form came from there, else to the view. */
    protected function done(Request $request, SavedView $view, $message)
    {
        $route = $request->input('back') === 'manage' ? redirect()->route('refreshglobal.views')
            : redirect()->route('refreshglobal.tickets', ['view' => $view->id]);

        return $route->with('flash_success', $message);
    }

    protected function back(array $flash)
    {
        $redirect = redirect()->route('refreshglobal.tickets');
        foreach ($flash as $key => $value) {
            $redirect->with($key, $value);
        }

        return $redirect;
    }
}
