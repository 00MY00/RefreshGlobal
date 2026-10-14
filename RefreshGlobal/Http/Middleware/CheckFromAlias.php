<?php

namespace Modules\RefreshGlobal\Http\Middleware;

use Closure;
use Modules\RefreshGlobal\Services\SendFrom;

/**
 * Messages sent through FreeScout's conversations.ajax (routes/web.php:64: new e-mail, reply, draft) and Refresh's
 * "New ticket" (refresh.new_ticket): a "from_alias" that is not an address of the ticket's mailbox is removed before
 * FreeScout applies it (Services/SendFrom.php sanitize()). Added to the "web" group like AfterDelete.
 */
class CheckFromAlias
{
    const ROUTES = ['conversations.ajax', 'refresh.new_ticket'];

    public function handle($request, Closure $next)
    {
        $route = $request->route();
        if ($route && in_array($route->getName(), self::ROUTES, true) && $request->isMethod('POST') && $request->has('from_alias')) {
            try {
                SendFrom::sanitize($request);
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [send-from] '.$e->getMessage());
            }
        }

        return $next($request);
    }
}
