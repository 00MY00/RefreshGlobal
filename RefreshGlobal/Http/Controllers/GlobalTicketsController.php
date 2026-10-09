<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider;
use Modules\RefreshGlobal\Services\Compatibility\Messages;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * "All mailboxes" page (GET /refresh-global/tickets): tickets of every mailbox the user can view, in one list.
 * Opening a ticket goes to FreeScout's own conversation page (Conversation::url()), so replies leave from the
 * ticket's own mailbox. Filters live in the URL: links can be shared and the Back button works.
 */
class GlobalTicketsController extends Controller
{
    /** Filter parameters; a request without any of them can load the user's default view. */
    const FILTER_PARAMS = ['mb', 'status', 'assignee', 'q', 'sort', 'order', 'view'];

    public function home()
    {
        return redirect()->route('refreshglobal.tickets');
    }

    public function index(Request $request)
    {
        return $this->safely(function () use ($request) {
            $report = $this->report();
            if ($report['state'] === 'blocking') {
                return $this->blockedResponse(Messages::failed($report, ['blocking']));
            }

            $user = auth()->user();
            $access = new MailboxAccess($user);

            // Types are checked; values are then cleaned by GlobalTicketQuery::normalize() (unknown ones dropped).
            $validator = \Validator::make($request->all(), [
                'mb'       => 'nullable|array|max:1000',
                'mb.*'     => 'nullable|integer|min:1',
                'status'   => 'nullable|array|max:10',
                'status.*' => 'nullable|integer',
                'assignee' => 'nullable|string|max:20',
                'q'        => 'nullable|string|max:'.GlobalTicketQuery::MAX_QUERY_LENGTH,
                'sort'     => 'nullable|string|in:'.implode(',', array_keys(GlobalTicketQuery::sorts())),
                'order'    => 'nullable|string|in:asc,desc',
                'page'     => 'nullable|integer|min:1',
                'view'     => 'nullable|integer|min:1',
            ]);
            $ignored_params = $validator->fails();

            // Saved view: ?view=<id> (only the user's own views), else the default view on a bare address
            $saved_views = $this->savedViews($user);
            $active_view = null;
            $input = $request->only(self::FILTER_PARAMS);
            if ($request->filled('view')) {
                $active_view = $saved_views ? SavedView::findForUser($request->input('view'), $user) : null;
            } elseif (!$request->has('reset') && !array_filter($input, function ($v) {
                return $v !== null && $v !== '' && $v !== [];
            })) {
                $active_view = $saved_views ? $saved_views->first(function ($view) {
                    return $view->is_default;
                }) : null;
            }
            if ($active_view) {
                $input = (array) $active_view->filters;
            }

            $filters = GlobalTicketQuery::normalize($input, $access);
            $query = new GlobalTicketQuery($access, $filters);

            $params = GlobalTicketQuery::toQueryParams($filters);
            if ($active_view) {
                $params['view'] = $active_view->id;
            }
            $conversations = $query->paginate(max(1, (int) config('refreshglobal.per_page', 30)))->appends($params);

            $counts_by_mailbox = $query->countsByMailbox();
            $counts_by_status = $query->countsByStatus();

            // The "Mailbox" column of the native table is printed by the module's hooks on this page only
            RefreshGlobalServiceProvider::$show_mailbox_column = true;

            // rendered here so that a rendering error is caught by safely() too
            return response(view('refreshglobal::tickets', [
                'skin'              => $report['skin'],
                'report'            => $report,
                'notices'           => Messages::failed($report, ['degraded', 'warning']),
                'is_admin'          => $user->isAdmin(),
                'conversations'     => $conversations,
                'folder'            => RefreshGlobalServiceProvider::virtualFolder(),
                'filters'           => $filters,
                // not "params": the native table reads a $params variable of its own (data-param_* attributes)
                'rg_params'         => $params,
                'filters_count'     => GlobalTicketQuery::activeCount($filters),
                'mailboxes'         => $access->mailboxes(),
                'rg_users'          => $access->assignableUsers(),
                'only_assigned'     => $access->onlyAssigned(),
                'rg_statuses'       => GlobalTicketQuery::statuses(),
                'sorts'             => self::sortLabels(),
                'counts_by_mailbox' => $counts_by_mailbox,
                'counts_by_status'  => $counts_by_status,
                'total_all'         => array_sum($counts_by_mailbox),
                'saved_views'       => $saved_views,
                'saved_views_ok'    => $saved_views !== null,
                'active_view'       => $active_view,
                'dropped_mailboxes' => $active_view ? (int) $filters['dropped_mailboxes'] : 0,
                'ignored_params'    => $ignored_params,
                'export_max'        => (int) config('refreshglobal.export_max_rows', 5000),
            ])->render());
        });
    }

    /** The user's saved views, or null when the table is missing (RG-DB-05: saved views disabled). */
    protected function savedViews($user)
    {
        try {
            if (!\Schema::hasTable('refreshglobal_saved_views')) {
                return null;
            }

            return SavedView::forUser($user)->orderBy('name')->get();
        } catch (\Exception $e) {
            \Log::warning('[RefreshGlobal] [RG-DB-05] '.$e->getMessage());

            return null;
        }
    }

    public static function sortLabels()
    {
        return [
            'updated' => __('refreshglobal::messages.sort_updated'),
            'created' => __('refreshglobal::messages.sort_created'),
            'number'  => __('refreshglobal::messages.sort_number'),
            'subject' => __('refreshglobal::messages.sort_subject'),
            'status'  => __('refreshglobal::messages.sort_status'),
        ];
    }
}
