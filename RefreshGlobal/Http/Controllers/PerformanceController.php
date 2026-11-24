<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\Perf\Hardware;
use Modules\RefreshGlobal\Services\Perf\Probe;
use Modules\RefreshGlobal\Services\Perf\Recorder;
use Modules\RefreshGlobal\Services\SchemaCache;

/**
 * Performance page (administrators, 1.23.0): machine, state of the server, time of the module's heavy parts, and the
 * sensors (recording of the requests for a limited time). browser(): what a page took in the browser, sent by every
 * user's pages while recording.
 */
class PerformanceController extends Controller
{
    public function index()
    {
        return $this->safely(function () {
            $hardware = Hardware::items();
            $server = Probe::server();
            $parts = Probe::parts(auth()->user());
            $summary = Recorder::summary();

            return response(view('refreshglobal::performance', [
                'available' => SchemaCache::has(Recorder::TABLE),
                'hardware'  => $hardware,
                'server'    => $server,
                'parts'     => $parts,
                'summary'   => $summary,
                'until'     => Recorder::until(),
                'text'      => Probe::text(array_merge($hardware, $server), $parts, $summary),
            ])->render());
        });
    }

    /** Starts (minutes > 0) or stops (0) the recording. */
    public function record(Request $request)
    {
        $this->validate($request, ['minutes' => 'required|integer|min:0|max:'.Recorder::MAX_MINUTES]);
        if (!SchemaCache::has(Recorder::TABLE)) {
            return redirect()->route('refreshglobal.performance');
        }
        $minutes = (int) $request->input('minutes');
        $minutes ? Recorder::start($minutes) : Recorder::stop();

        return redirect()->route('refreshglobal.performance')->with('flash_success', $minutes
            ? __('refreshglobal::perf.started', ['n' => $minutes]) : __('refreshglobal::perf.stopped'));
    }

    public function clear()
    {
        if (SchemaCache::has(Recorder::TABLE)) {
            Recorder::clear();
        }

        return redirect()->route('refreshglobal.performance')->with('flash_success', __('refreshglobal::perf.cleared'));
    }

    public function browser(Request $request)
    {
        $done = false;
        if (Recorder::active()) {
            try {
                $done = Recorder::browser($request->input('id'), $request->input('ttfb'), $request->input('ready'), $request->input('load'));
            } catch (\Throwable $e) {
                $done = false;
            }
        }

        return response()->json(['status' => $done ? 'success' : 'ignored']);
    }
}
