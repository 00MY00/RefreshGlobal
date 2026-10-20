<?php

namespace Modules\RefreshGlobal\Services\Interop;

use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * Server side of the FreeScout Interop contract (docs/INTEROPERABILITY.md §18–19): neutral Eventy hooks, declared in
 * interop.json (interop.provides.server_hooks), that let modules cooperate without calling each other's classes.
 *
 *   filter fsinterop.modules           [module id => summary]: every participating module adds itself
 *   filter fsinterop.dashboard.context the context of a dashboard about to be computed; another module may NARROW
 *                                      mailbox_ids (never widen them: the user's rights always apply)
 *   filter fsinterop.dashboard.data    figures of a dashboard scope (schema dashboard-data 1.1); RefreshGlobal answers
 *                                      the scope "global-mailboxes" for the given user
 *
 * The existing public hooks of RefreshGlobal (refreshglobal.mail_tags…, docs/api-tags.md) are declared in the same
 * manifest and stay unchanged.
 */
class ServerHooks
{
    const SCOPE_GLOBAL = 'global-mailboxes';

    /** Registers the hooks RefreshGlobal answers (Providers/RefreshGlobalServiceProvider::registerInterop()). */
    public static function register()
    {
        \Eventy::addFilter('fsinterop.modules', function ($modules) {
            return self::addOwnSummary(is_array($modules) ? $modules : []);
        }, 20, 1);

        \Eventy::addFilter('fsinterop.dashboard.data', function ($data, $context = []) {
            // another module already answered (a non-empty value; Eventy passes a null value on as ''), or not our scope
            if (!empty($data) || !is_array($context) || ($context['scope'] ?? '') !== self::SCOPE_GLOBAL) {
                return $data;
            }

            return self::dashboardData($context);
        }, 20, 2);
    }

    /** RefreshGlobal's entry of fsinterop.modules (from its manifest and module.json). */
    public static function addOwnSummary(array $modules)
    {
        $manifest = Manifest::own();
        if (!$manifest) {
            return $modules;
        }
        $modules[$manifest->id()] = [
            'id'           => $manifest->id(),
            'name'         => (string) $manifest->get('module.name'),
            'version'      => self::moduleVersion(),
            'protocol'     => $manifest->protocol(),
            'capabilities' => $manifest->capabilities(),
            'manifest'     => 'interop.json',
        ];

        return $modules;
    }

    /**
     * Context of the all-mailboxes dashboard for a user, after the fsinterop.dashboard.context filter. The mailbox ids
     * returned by other modules are intersected with the ones the user may see: a hook can only narrow the dashboard.
     *
     * @return array ['schema' => '1.0', 'scope' => 'global-mailboxes', 'user_id' => int, 'mailbox_ids' => int[]]
     */
    public static function dashboardContext($user, array $allowedIds)
    {
        $allowedIds = array_values(array_map('intval', $allowedIds));
        $context = ['schema' => '1.0', 'scope' => self::SCOPE_GLOBAL, 'user_id' => $user ? (int) $user->id : 0, 'mailbox_ids' => $allowedIds];
        try {
            $filtered = \Eventy::filter('fsinterop.dashboard.context', $context);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.dashboard.context: '.$e->getMessage());
            $filtered = $context;
        }
        $ids = is_array($filtered) && isset($filtered['mailbox_ids']) && is_array($filtered['mailbox_ids'])
            ? array_values(array_intersect($allowedIds, array_map('intval', $filtered['mailbox_ids'])))
            : $allowedIds;
        $context['mailbox_ids'] = $ids;

        return $context;
    }

    /**
     * Figures of the all-mailboxes scope in the shared schema dashboard-data 1.1
     * (RefreshGlobal/docs/interop/schemas/dashboard-data-1.1.schema.json), for $context['user_id'] or the logged-in user.
     * Same rights and same queries as the dashboard itself (GlobalDashboard::baseQuery / unresolvedByAgent).
     */
    public static function dashboardData(array $context)
    {
        try {
            $user = !empty($context['user_id']) ? \App\User::find((int) $context['user_id']) : auth()->user();
            if (!$user) {
                return null;
            }
            $ctx = self::dashboardContext($user, (new MailboxAccess($user))->allowedIds());
            $agents = GlobalDashboard::unresolvedByAgent(GlobalDashboard::baseQuery($user, $ctx['mailbox_ids']));
            $counts = ['open' => 0, 'pending' => 0, 'unassigned' => 0];
            $rows = [];
            foreach ($agents as $a) {
                $counts['open'] += $a['open'];
                $counts['pending'] += $a['pending'];
                if ($a['user_id'] === 0) {
                    $counts['unassigned'] += $a['total'];
                }
                $rows[] = ['user_id' => $a['user_id'] ?: null, 'name' => $a['name'], 'open' => $a['open'], 'pending' => $a['pending'], 'total' => $a['total']];
            }

            return ['schema' => '1.1', 'source_module' => 'refresh-global', 'scope' => self::SCOPE_GLOBAL,
                'mailbox_ids' => $ctx['mailbox_ids'], 'counts' => $counts, 'agents' => $rows];
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.dashboard.data: '.$e->getMessage());

            return null;
        }
    }

    protected static function moduleVersion()
    {
        $data = json_decode((string) @file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

        return is_array($data) ? (string) ($data['version'] ?? '') : '';
    }
}
