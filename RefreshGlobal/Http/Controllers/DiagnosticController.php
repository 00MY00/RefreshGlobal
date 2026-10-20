<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;

/**
 * Admin page /refresh-global/diagnostic: full compatibility report (same checks as php artisan refreshglobal:check),
 * navigation option, and who updates the module (FreeScout's Modules page or UpdateGuard, Services/Updates.php).
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
                'replace_tickets' => \Modules\RefreshGlobal\Services\Settings::replaceRefreshTickets(),
                'refresh_active' => (bool) $report['versions']['refresh_active'],
                'interop'        => self::interop(),
            ])->render());
        });
    }

    /**
     * FreeScout Interop section of the page (docs/INTEROPERABILITY.md §45): the module's own contract (protocol, manifest
     * valid) and how it cooperates with Refresh on this installation (Services/Interop/PeerStatus.php).
     */
    protected static function interop()
    {
        $manifest = \Modules\RefreshGlobal\Services\Interop\Manifest::own();
        try {
            $peer = \Modules\RefreshGlobal\Services\Interop\PeerStatus::fromInstallation('refresh');
        } catch (\Throwable $e) {
            $peer = null;
        }

        return [
            'protocol' => $manifest ? $manifest->protocol() : '',
            'errors'   => $manifest ? $manifest->validate() : ['interop.json missing or not valid JSON'],
            'refresh'  => $peer,
        ];
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
}
