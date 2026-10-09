<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * Personal saved views of the "All mailboxes" page: create, rename, set as default, delete.
 * Plain HTML forms (POST / DELETE with FreeScout's CSRF token), so they work without any script.
 * A user only ever finds their own views (SavedView::findForUser): another id answers 404.
 */
class SavedViewsController extends Controller
{
    public function store(Request $request)
    {
        return $this->safely(function () use ($request) {
            $this->validate($request, [
                'name'       => 'required|string|max:100',
                'is_default' => 'nullable|boolean',
                'mb'         => 'nullable|array|max:1000',
                'mb.*'       => 'nullable|integer|min:1',
                'status'     => 'nullable|array|max:10',
                'status.*'   => 'nullable|integer',
                'assignee'   => 'nullable|string|max:20',
                'q'          => 'nullable|string|max:'.GlobalTicketQuery::MAX_QUERY_LENGTH,
                'sort'       => 'nullable|string|max:20',
                'order'      => 'nullable|string|in:asc,desc',
            ]);
            $user = auth()->user();
            if (SavedView::forUser($user)->count() >= (int) config('refreshglobal.max_saved_views', 50)) {
                return $this->back(['flash_error' => __('refreshglobal::messages.views_limit')]);
            }

            // Only allowed mailboxes are stored; they are checked again every time the view is loaded.
            $filters = GlobalTicketQuery::normalize($request->all(), new MailboxAccess($user));
            unset($filters['dropped_mailboxes']);

            $view = new SavedView();
            $view->user_id = $user->id;
            $view->name = trim($request->input('name'));
            $view->filters = $filters;
            $view->is_default = (bool) $request->input('is_default');
            \DB::transaction(function () use ($view, $user) {
                if ($view->is_default) {
                    SavedView::forUser($user)->update(['is_default' => false]);
                }
                $view->save();
            });

            return redirect()->route('refreshglobal.tickets', ['view' => $view->id])
                ->with('flash_success', __('refreshglobal::messages.view_saved'));
        });
    }

    public function rename(Request $request, $id)
    {
        return $this->safely(function () use ($request, $id) {
            $this->validate($request, ['name' => 'required|string|max:100']);
            $view = $this->findOrFail($id);
            $view->name = trim($request->input('name'));
            $view->save();

            return redirect()->route('refreshglobal.tickets', ['view' => $view->id])
                ->with('flash_success', __('refreshglobal::messages.view_renamed'));
        });
    }

    /** Sets the view as the default one (opened on /refresh-global/tickets), or removes the default if it already was. */
    public function setDefault(Request $request, $id)
    {
        return $this->safely(function () use ($id) {
            $view = $this->findOrFail($id);
            $user = auth()->user();
            $make_default = !$view->is_default;
            \DB::transaction(function () use ($view, $user, $make_default) {
                SavedView::forUser($user)->update(['is_default' => false]);
                if ($make_default) {
                    $view->is_default = true;
                    $view->save();
                }
            });

            return redirect()->route('refreshglobal.tickets', ['view' => $view->id])
                ->with('flash_success', $make_default ? __('refreshglobal::messages.view_default_set') : __('refreshglobal::messages.view_default_unset'));
        });
    }

    public function destroy(Request $request, $id)
    {
        return $this->safely(function () use ($id) {
            $this->findOrFail($id)->delete();

            return redirect()->route('refreshglobal.tickets', ['reset' => 1])
                ->with('flash_success', __('refreshglobal::messages.view_deleted'));
        });
    }

    protected function findOrFail($id)
    {
        $view = SavedView::findForUser($id, auth()->user());
        if (!$view) {
            abort(404);
        }

        return $view;
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
