<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Providers\RefreshGlobalServiceProvider;
use Modules\RefreshGlobal\Services\Compatibility\Messages;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\SavedViews;

/**
 * "All mailboxes" page (GET /refresh-global/tickets): tickets of every mailbox the user can view, in one list.
 * Opening a ticket goes to FreeScout's own conversation page (Conversation::url()), so replies leave from the
 * ticket's own mailbox. Filters live in the URL: links can be shared and the Back button works.
 */
class GlobalTicketsController extends Controller
{
    /** Filter parameters; a request without any of them can load the user's default view. */
    const FILTER_PARAMS = ['mb', 'status', 'assignee', 'q', 'rv', 'wf', 'mt', 'sort', 'order', 'view', 'vm'];

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
                'rv'       => 'nullable|string|max:30',
                'wf'       => 'nullable|string|max:'.\Modules\RefreshGlobal\Services\AutoFolders::MAX_LENGTH,
                'mt'       => 'nullable|string|max:100',
                'sort'     => 'nullable|string|in:'.implode(',', array_keys(GlobalTicketQuery::sorts())),
                'order'    => 'nullable|string|in:asc,desc',
                'page'     => 'nullable|integer|min:1',
                'view'     => 'nullable|integer|min:1',
                'vm'       => 'nullable|in:1',
            ]);
            $ignored_params = $validator->fails();

            // Saved view (Services/SavedViews.php): ?view=<id> (a view the user may open), with its filters, or with
            // filters changed after opening it (?vm=1); else the user's default view on a bare address
            $resolved = self::resolveFilters($request, $user, $access, true);
            $filters = $resolved['filters'];
            $active_view = $resolved['view'];
            $query = new GlobalTicketQuery($access, $filters);

            $params = GlobalTicketQuery::toQueryParams($filters);
            if ($active_view) {
                $params['view'] = $active_view->id;
                if ($resolved['changed']) {
                    $params['vm'] = 1;
                }
            }
            $saved_views_ok = SavedViews::available();
            $visible_views = $saved_views_ok ? SavedViews::visibleFor($user) : [];
            $conversations = $query->paginate(max(1, (int) config('refreshglobal.per_page', 30)))->appends($params);

            // where to come back after deleting a ticket opened from this list (Http\Middleware\AfterDelete)
            \Modules\RefreshGlobal\Http\Middleware\AfterDelete::rememberList(
                $conversations->currentPage() > 1 ? $params + ['page' => $conversations->currentPage()] : $params,
                $filters
            );

            $counts_by_mailbox = $query->countsByMailbox();
            $counts_by_status = $query->countsByStatus();

            // The "Mailbox" column of the native table is printed by the module's hooks on this page only
            RefreshGlobalServiceProvider::$show_mailbox_column = RefreshGlobalServiceProvider::mailboxColumnMode();

            // Automatic folders of Workflow Packs (when it is installed): views panel + folder of each ticket (badge)
            $auto_folders = self::autoFolders($access, $query, $conversations);

            // List a ticket opened from here goes back to (breadcrumb of the ticket page, Public/js/shell.js): this
            // list without the automatic folder, at the same page when nothing was removed
            $back = self::backTo($access, $filters, $params, $conversations, $active_view);

            // origin tags of the e-mails (Services/MailTags.php): panel counts and badge of each ticket, when shown
            $mail_tags = self::mailTags($query, $conversations);

            // rendered here so that a rendering error is caught by safely() too
            return response(view('refreshglobal::tickets', [
                'rg_back'           => $back,
                'rg_mail_tags'      => $mail_tags,
                'rg_folders'        => $auto_folders['tree'],
                'rg_folder_label'   => $auto_folders['label'],

                'skin'              => $report['skin'],
                'report'            => $report,
                'notices'           => Messages::failed($report, ['degraded', 'warning']),
                'is_admin'          => $user->isAdmin(),
                'conversations'     => $conversations,
                'folder'            => RefreshGlobalServiceProvider::virtualFolder(),
                'filters'           => $filters,
                // not "params": the native table reads a $params variable of its own (data-param_* attributes)
                'rg_params'         => $params,
                // "Fetch e-mails now" through the SyncNow module, when it is installed (IMAP mailboxes of the list)
                'rg_sync'           => \Modules\RefreshGlobal\Services\SyncNow::mailboxes($user, $filters['mailboxes']),
                // fingerprint of this list when shown (automatic refresh compares with it)
                'rg_fp'             => \Modules\RefreshGlobal\Services\Settings::autoRefresh() ? $query->fingerprint() : '',
                'filters_count'     => GlobalTicketQuery::activeCount($filters),
                'mailboxes'         => $access->mailboxes(),
                'rg_users'          => $access->assignableUsers(),
                'only_assigned'     => $access->onlyAssigned(),
                'rg_statuses'       => GlobalTicketQuery::statuses(),
                'sorts'             => self::sortLabels(),
                'counts_by_mailbox' => $counts_by_mailbox,
                'counts_by_status'  => $counts_by_status,
                'total_all'         => array_sum($counts_by_mailbox),
                'saved_views'       => $visible_views,
                'saved_views_ok'    => $saved_views_ok,
                'active_view'       => $active_view,
                'rg_view'           => self::viewState($active_view, $user, $visible_views, $resolved['modified'], $filters),
                'rg_shareable'      => $saved_views_ok ? SavedViews::shareableUsers($user) : collect([]),
                'dropped_mailboxes' => $active_view && !$resolved['changed'] ? (int) $filters['dropped_mailboxes'] : 0,
                'ignored_params'    => $ignored_params,
                'export_max'        => (int) config('refreshglobal.export_max_rows', 5000),
            ])->render());
        });
    }

    /**
     * Automatic refresh (GET /refresh-global/state, same parameters as the page): fingerprint of the list the page
     * shows, with the same rights and filters. The page reloads itself when it changes (Public/js/refreshglobal.js,
     * Public/js/shell.js for the dashboard). Small JSON answer, one aggregate query.
     */
    public function state(Request $request)
    {
        try {
            $user = auth()->user();
            $access = new MailboxAccess($user);
            $fp = (new GlobalTicketQuery($access, self::resolveFilters($request, $user, $access, false)['filters']))->fingerprint();

            return response()->json(['fp' => $fp])->header('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            // no refresh rather than an error on the page
            return response()->json(['fp' => null], 200)->header('Cache-Control', 'no-store');
        }
    }

    /**
     * Automatic folders of Workflow Packs (Services/AutoFolders.php): tree of the views panel, with the number of
     * tickets of the list in each folder (the list's filters except the folder), label of the chosen folder, and the
     * folder of each ticket of the page (badge printed by RefreshGlobalServiceProvider). Nothing when Workflow Packs is
     * missing; an error only hides them.
     */
    protected static function autoFolders(MailboxAccess $access, GlobalTicketQuery $query, $conversations)
    {
        $out = ['tree' => [], 'label' => ''];
        RefreshGlobalServiceProvider::$folder_badges = [];
        if (!\Modules\RefreshGlobal\Services\AutoFolders::available()) {
            return $out;
        }
        try {
            $rights = new GlobalTicketQuery($access, GlobalTicketQuery::normalize([], $access));
            $folders = \Modules\RefreshGlobal\Services\AutoFolders::folders($rights->base());
            $out['tree'] = \Modules\RefreshGlobal\Services\AutoFolders::tree($folders, $query->countsByFolder());
            $wf = $query->filters()['wfolder'];
            $out['label'] = $wf !== '' ? \Modules\RefreshGlobal\Services\AutoFolders::labelOf($wf, $folders) : '';
            RefreshGlobalServiceProvider::$folder_badges = \Modules\RefreshGlobal\Services\AutoFolders::labels(
                $conversations->getCollection()->pluck('id')->all()
            );
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [auto-folders] '.$e->getMessage());
        }

        return $out;
    }

    /**
     * List to come back to from a ticket opened here: address (without the automatic folder:
     * AfterDelete::backParams()), its name (saved view, or "All mailboxes") and its number of tickets.
     *
     * @return array ['url', 'label', 'count']
     */
    protected static function backTo(MailboxAccess $access, array $filters, array $params, $conversations, $active_view)
    {
        $in_folder = $filters['wfolder'] !== '';
        $back = \Modules\RefreshGlobal\Http\Middleware\AfterDelete::backParams($params, $filters);
        if (!$in_folder && $conversations->currentPage() > 1) {
            $back['page'] = $conversations->currentPage();
        }
        $count = $conversations->total();
        if ($in_folder) {
            try {
                $count = (new GlobalTicketQuery($access, array_merge($filters, ['wfolder' => ''])))->base()->count();
            } catch (\Throwable $e) {
                $count = null;
            }
        }

        return [
            'url'   => route('refreshglobal.tickets', $back ?: ['reset' => 1]),
            'label' => $active_view && isset($back['view']) ? $active_view->name : __('refreshglobal::messages.title'),
            'count' => $count,
        ];
    }

    /** Origin tags: [tag => tickets of the list] for the panel; badges of the page (RefreshGlobalServiceProvider). */
    protected static function mailTags(GlobalTicketQuery $query, $conversations)
    {
        RefreshGlobalServiceProvider::$mail_tag_badges = [];
        if (!\Modules\RefreshGlobal\Services\MailTags::enabled() || !\Modules\RefreshGlobal\Services\Settings::showMailTags()) {
            return [];
        }
        try {
            RefreshGlobalServiceProvider::$mail_tag_badges = \Modules\RefreshGlobal\Services\MailTags::forConversations($conversations->getCollection()->pluck('id')->all());

            return $query->countsByMailTag();
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [mail-tags] '.$e->getMessage());

            return [];
        }
    }

    /** Address of the state of a list (parameters of the page, or the whole list). */
    public static function stateUrl(array $params)
    {
        return route('refreshglobal.state', $params ?: ['reset' => 1]);
    }

    /**
     * Filters of the list asked for (page, automatic refresh, export): a saved view the user may open
     * (SavedViews::find(), never trusted from the URL), as saved (?view=<id>) or with the filters of the URL when they
     * were changed after opening it (?view=<id>&vm=1); otherwise the URL's filters; on a bare address (and $use_default)
     * the user's default view. The viewer's rights always apply (GlobalTicketQuery::normalize()).
     *
     * @return array ['filters', 'view' (SavedView|null), 'changed' (URL filters used with a view), 'modified' (they differ)]
     */
    public static function resolveFilters(Request $request, $user, MailboxAccess $access, $use_default)
    {
        $out = ['filters' => null, 'view' => null, 'changed' => false, 'modified' => false];
        $input = $request->only(self::FILTER_PARAMS);
        try {
            if ($request->filled('view')) {
                $out['view'] = SavedViews::find($request->input('view'), $user);
            } elseif ($use_default && !$request->has('reset') && !array_filter($input, function ($v) {
                return $v !== null && $v !== '' && $v !== [];
            })) {
                $default = SavedViews::defaultFor($user);
                $out['view'] = $default ? SavedViews::find($default, $user) : null;
            }
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [RG-DB-05] '.$e->getMessage());
            $out['view'] = null;
        }
        if ($out['view']) {
            $of_view = SavedViews::filtersFor($out['view'], $access);
            if ((string) $request->input('vm') === '1') {
                $out['changed'] = true;
                $out['filters'] = GlobalTicketQuery::normalize($input, $access);
                $out['modified'] = SavedViews::differs($out['filters'], $of_view);
            } else {
                $out['filters'] = $of_view;
            }
        } else {
            $out['filters'] = GlobalTicketQuery::normalize($input, $access);
        }

        return $out;
    }

    /**
     * What the page shows about the open view: its row (section, owner, pinned, my default), the user's rights on it,
     * "changed since opened", and what the viewer gets without (SavedViews::issues()).
     */
    protected static function viewState($view, $user, array $visible, $modified, array $filters)
    {
        if (!$view) {
            return null;
        }
        $row = null;
        foreach ($visible as $v) {
            if ($v['id'] === (int) $view->id) {
                $row = $v;
            }
        }
        try {
            $issues = $modified ? [] : SavedViews::issues($view, $filters);
        } catch (\Throwable $e) {
            $issues = [];
        }
        $owner = $row ? $row['owner_name'] : (SavedViews::userNames([$view->user_id])[(int) $view->user_id]['name'] ?? '');

        return [
            'row'           => $row,
            'visibility'    => $view->visibilityValue(),
            'own'           => (int) $view->user_id === (int) $user->id,
            'owner_name'    => $owner,
            'modified'      => $modified,
            'can_edit'      => SavedViews::canEdit($view, $user),
            'can_manage'    => SavedViews::canManage($view, $user),
            'can_duplicate' => SavedViews::canDuplicate($view, $user),
            'can_global'    => SavedViews::canPublishGlobal($user),
            'pinned'        => $row ? $row['pinned'] : false,
            'my_default'    => $row ? $row['my_default'] : false,
            'allow_duplicate' => (bool) $view->allow_duplicate,
            'default_scope' => (string) $view->default_scope,
            'shared_with'   => $view->visibilityValue() === SavedView::SHARED_USERS ? SavedViews::sharedWith($view) : [],
            'issues'        => $issues,
            'reset_url'     => route('refreshglobal.tickets', ['view' => $view->id]),
        ];
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
