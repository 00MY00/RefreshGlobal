<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;

/** Admin page /refresh-global/diagnostic: full compatibility report (same checks as php artisan refreshglobal:check). */
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

            return response(view('refreshglobal::diagnostic', ['report' => $report])->render());
        });
    }
}
