<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\FlowChanges;
use Modules\RefreshGlobal\Services\FlowTemp;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\WorkflowPacksLink;

/**
 * Flows of Workflow Packs 3.20+: statistics (GET /refresh-global/flows), untreated e-mails
 * (GET /refresh-global/flows/untreated), temporary flow and history backfill (POST, administrators).
 *
 * Without Workflow Packs 3.20+ with its engine on (WorkflowPacksLink::available()), every route answers 404: nothing
 * of it exists. Rights are checked on every request: the mailboxes come from MailboxAccess, never from the browser.
 */
class FlowsController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAvailable();

        return $this->safely(function () use ($request) {
            $user = auth()->user();
            $access = new MailboxAccess($user);
            $f = FlowChanges::filters($request->all(), $access);

            return response(view('refreshglobal::flows', [
                'tab'       => 'stats',
                'filters'   => $f,
                'stats'     => FlowChanges::stats($f, $access),
                'mailboxes' => $access->mailboxes()->keyBy('id'),
                'flows'     => FlowChanges::knownFlows($access->allowedIds()),
                'is_admin'  => $user->isAdmin(),
                'backfill'  => FlowChanges::backfillStatus(),
            ])->render());
        });
    }

    public function untreated(Request $request)
    {
        $this->ensureAvailable();

        return $this->safely(function () use ($request) {
            $user = auth()->user();
            $access = new MailboxAccess($user);
            $f = FlowTemp::filters($request->all(), $access);
            $list = FlowTemp::untreated($f, $access);
            $is_admin = $user->isAdmin();

            return response(view('refreshglobal::flows_untreated', [
                'tab'       => 'untreated',
                'filters'   => $f,
                'list'      => $list,
                'tickets'   => FlowTemp::tickets($list['ids']),
                'mailboxes' => $access->mailboxes()->keyBy('id'),
                'flows'     => $is_admin ? FlowChanges::knownFlows($access->allowedIds()) : [],
                'is_admin'  => $is_admin,
                'temps'     => $is_admin ? FlowTemp::recent() : [],
                'max'       => WorkflowPacksLink::MAX_TEMP,
            ])->render());
        });
    }

    /** "Handle with a temporary flow": the list is computed again here, then the administrator goes to the editor. */
    public function createTemp(Request $request)
    {
        $this->ensureAvailable();
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }
        $this->validate($request, [
            'mb'         => 'nullable|integer|min:0',
            'statuses'   => 'nullable|array',
            'statuses.*' => 'string|in:'.implode(',', FlowTemp::STATUSES),
            'period'     => 'nullable|integer|min:0|max:'.FlowChanges::MAX_DAYS,
            'name'       => 'nullable|string|max:100',
            'from_flow'  => 'nullable|integer|min:0',
        ]);
        $input = $request->only(['mb', 'statuses', 'period', 'name', 'from_flow']);
        $back = route('refreshglobal.flows.untreated', array_filter(['mb' => $input['mb'] ?? null, 'statuses' => $input['statuses'] ?? null,
            'period' => $input['period'] ?? null], function ($v) {
                return $v !== null && $v !== '';
            }));

        $out = FlowTemp::create($user, new MailboxAccess($user), $input);
        if ($out['ok']) {
            return redirect()->away($out['edit_url']);
        }
        $redirect = redirect()->to($back)->with('rg_flow_error', $out['error']);
        if ($out['edit_url'] !== '') {
            $redirect->with('rg_flow_edit', $out['edit_url']);
        }

        return $redirect;
    }

    /** "Catch up on the history": run by the scheduler within a minute (command refreshglobal:flow-history --requested). */
    public function backfill(Request $request)
    {
        $this->ensureAvailable();
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }
        $this->validate($request, [
            'days' => 'required|integer|min:1|max:'.FlowChanges::MAX_DAYS,
            'mb'   => 'nullable|integer|min:0',
        ]);
        $access = new MailboxAccess($user);
        $mb = (int) $request->input('mb', 0);
        $mailboxes = $mb && $access->isAllowed($mb) ? [$mb] : [];
        if (!in_array(FlowChanges::backfillStatus()['state'], ['requested', 'running'], true)) {
            FlowChanges::requestBackfill($mailboxes, (int) $request->input('days'), $user->id);
        }

        return redirect()->route('refreshglobal.flows', $request->only(['days', 'mb']))
            ->with('flash_success', __('refreshglobal::flows.backfill_requested'));
    }

    protected function ensureAvailable()
    {
        if (!WorkflowPacksLink::available() || !FlowChanges::tableExists()) {
            abort(404);
        }
    }
}
