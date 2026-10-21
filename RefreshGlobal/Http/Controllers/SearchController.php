<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\Search\GlobalSearch;

/**
 * GET /refresh-global/search — endpoint of the search provider "refreshglobal.global-search" (FreeScout Interop
 * contract, registered in the browser by Public/js/interop-adapter.js). Read-only JSON, schema search-results 1.0.
 *
 * Parameters: q (text), limit (1-50, default 10), mb[] (optional mailbox ids, intersected with the user's).
 * Rights: the logged-in user's, applied by GlobalSearch (never the ids of the request alone).
 */
class SearchController extends Controller
{
    public function search(Request $request)
    {
        $mailboxes = array_values(array_filter(array_map('intval', (array) $request->input('mb', []))));
        try {
            $result = GlobalSearch::run(auth()->user(), $request->input('q', ''), $request->input('limit', GlobalSearch::DEFAULT_LIMIT), $mailboxes);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] search: '.$e->getMessage());

            return response()->json(['schema' => GlobalSearch::SCHEMA, 'provider' => GlobalSearch::PROVIDER_ID, 'error' => 'search_failed'], 500);
        }

        return response()->json($result);
    }
}
