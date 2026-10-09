<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\Update\Updater;

/**
 * Admin page /refresh-global/diagnostic: full compatibility report (same checks as php artisan refreshglobal:check)
 * and automatic update (state, last result, on/off). The update itself never runs in a web request.
 */
class DiagnosticController extends Controller
{
    public function index(Request $request)
    {
        return $this->safely(function () use ($request) {
            if ($request->has('refresh')) {
                CompatibilityChecker::forget();
            }
            $report = (new CompatibilityChecker(auth()->user()))->run();
            if ($request->has('refresh')) {
                CompatibilityChecker::log($report);
            }

            return response(view('refreshglobal::diagnostic', [
                'report'         => $report,
                'update'         => Updater::status(),
                'update_enabled' => Updater::enabled(),
                'update_time'    => (string) config('refreshglobal.auto_update_time', '03:30'),
                'current'        => Updater::currentVersion(),
                'replace_tickets' => \Modules\RefreshGlobal\Services\Settings::replaceRefreshTickets(),
                'refresh_active' => (bool) $report['versions']['refresh_active'],
            ])->render());
        });
    }

    /**
     * "Update now": only records the request; FreeScout's scheduler runs `refreshglobal:update --requested` every
     * minute, which performs the safe update (automatic rollback) outside any web request.
     */
    public function updateNow(Request $request)
    {
        return $this->safely(function () use ($request) {
            Updater::requestUpdate();
            $to = $request->input('back') === 'settings'
                ? route('settings', ['section' => 'refreshglobal'])
                : route('refreshglobal.diagnostic');

            return redirect($to)->with('flash_success', __('refreshglobal::messages.update_requested', ['time' => date('H:i')]));
        });
    }

    /**
     * "Check for updates": reads the module.json of the latest release now (nothing is installed) and tells the
     * result. Only a small file is downloaded, with a short time limit, so it can run in the web request.
     */
    public function checkUpdate(Request $request)
    {
        return $this->safely(function () use ($request) {
            $to = $request->input('back') === 'settings'
                ? route('settings', ['section' => 'refreshglobal'])
                : route('refreshglobal.diagnostic');
            try {
                $info = (new Updater())->setTimeout(15)->check();
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [update] check failed: '.$e->getMessage());
                // 404 with no usable fallback: no release and no main-branch module.json either
                if (Updater::isNotFound($e)) {
                    return redirect($to)->with('flash_error', __('refreshglobal::messages.update_check_no_release', [
                        'url' => rtrim((string) config('refreshglobal.update_url'), '/'),
                    ]));
                }

                return redirect($to)->with('flash_error', __('refreshglobal::messages.update_check_failed', ['error' => $e->getMessage()]));
            }
            // where the version was read: a published release, or the main branch when there is none
            $source = ($info['source'] ?? '') === 'branch' ? ' '.__('refreshglobal::messages.update_source_branch') : '';
            if (!$info['available']) {
                return redirect($to)->with('flash_success', __('refreshglobal::messages.update_check_up_to_date', ['version' => $info['current']]).$source);
            }
            if (!$info['compatible']) {
                return redirect($to)->with('flash_error', __('refreshglobal::messages.update_check_incompatible', [
                    'version' => $info['latest'], 'required' => $info['required_app'],
                ]).$source);
            }

            return redirect($to)->with('flash_success', __('refreshglobal::messages.update_check_available', [
                'version' => $info['latest'], 'current' => $info['current'],
            ]).$source);
        });
    }

    /** Navigation option: replace Refresh's "Tickets" entry by "All mailboxes". */
    public function navigation(Request $request)
    {
        return $this->safely(function () use ($request) {
            $this->validate($request, ['replace_refresh_tickets' => 'required|boolean']);
            $on = (bool) $request->input('replace_refresh_tickets');
            \Modules\RefreshGlobal\Services\Settings::setReplaceRefreshTickets($on);

            return redirect()->route('refreshglobal.diagnostic')->with('flash_success', $on
                ? __('refreshglobal::messages.replace_tickets_on') : __('refreshglobal::messages.replace_tickets_off'));
        });
    }

    public function autoUpdate(Request $request)
    {
        return $this->safely(function () use ($request) {
            $this->validate($request, ['enabled' => 'required|boolean']);
            Updater::setEnabled((bool) $request->input('enabled'));

            return redirect()->route('refreshglobal.diagnostic')->with('flash_success', $request->input('enabled')
                ? __('refreshglobal::messages.auto_update_on') : __('refreshglobal::messages.auto_update_off'));
        });
    }
}
