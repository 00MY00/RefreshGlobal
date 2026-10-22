<?php

namespace Modules\RefreshGlobal\Services\Interop;

use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\Search\GlobalSearch;

/**
 * Server side of the FreeScout Interop contract (docs/INTEROPERABILITY.md §18–19): neutral Eventy hooks, declared in
 * interop.json (interop.provides.server_hooks), that let modules cooperate without calling each other's classes.
 *
 *   filter fsinterop.modules           [module id => summary]: every participating module adds itself
 *   filter fsinterop.dashboard.context the context of a dashboard about to be computed; another module may NARROW
 *                                      mailbox_ids (never widen them: the user's rights always apply)
 *   filter fsinterop.dashboard.data    figures of a dashboard scope (schema dashboard-data 1.1); RefreshGlobal answers
 *                                      the scope "global-mailboxes" for the given user
 *   filter fsinterop.dashboard.owner   module that renders the dashboard of FreeScout's dashboard page for a user;
 *                                      RefreshGlobal answers "refresh-global" when its dashboard of all mailboxes is on
 *                                      (Refresh, with the contract, then does not render its own block)
 *   filter fsinterop.navigation.context which module renders which surface on the current page (NavigationContext)
 *   filter fsinterop.conversation.context what each module knows about a ticket, for a user (ConversationContext)
 *   filter fsinterop.search.providers  [provider id => descriptor]  } RefreshGlobal extension, outside the shared
 *   filter fsinterop.search.results    results of a search scope    } protocol 1.0 (search: later, by agreement)
 *
 * Consumed (asked, never answered by RefreshGlobal): fsinterop.sla.targets (Services/Dashboard/Sla.php).
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

        \Eventy::addFilter('fsinterop.dashboard.owner', function ($owner, $context = []) {
            return self::dashboardOwner($owner, is_array($context) ? $context : []);
        }, 20, 2);

        \Eventy::addFilter('fsinterop.navigation.context', function ($context) {
            return NavigationContext::addOwnClaims($context);
        }, 20, 1);

        \Eventy::addFilter('fsinterop.conversation.context', function ($context, $conversation = null, $user = null) {
            return ConversationContext::addOwn($context, $conversation, $user);
        }, 20, 3);

        \Eventy::addFilter('fsinterop.search.providers', function ($providers) {
            return self::addSearchProvider(is_array($providers) ? $providers : []);
        }, 20, 1);

        \Eventy::addFilter('fsinterop.search.results', function ($results, $context = []) {
            // another module already answered, or not our scope
            if (!empty($results) || !is_array($context) || ($context['scope'] ?? '') !== GlobalSearch::CONTEXT) {
                return $results;
            }

            return self::searchResults($context);
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

    /**
     * Answer to fsinterop.dashboard.owner. Context (schema dashboard-owner 1.0): ['schema' => '1.0', 'page' =>
     * 'dashboard', 'user_id' => int]. An answer already given by another module is kept.
     *
     * @return string|mixed "refresh-global", or the value received
     */
    public static function dashboardOwner($owner, array $context)
    {
        if (!empty($owner) || ($context['page'] ?? 'dashboard') !== 'dashboard') {
            return $owner;
        }
        $user = !empty($context['user_id']) ? \App\User::find((int) $context['user_id']) : auth()->user();

        return GlobalDashboard::ownsDashboardFor($user) ? 'refresh-global' : $owner;
    }

    /** RefreshGlobal's entry of fsinterop.search.providers. */
    public static function addSearchProvider(array $providers)
    {
        try {
            $providers[GlobalSearch::PROVIDER_ID] = GlobalSearch::provider();
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.search.providers: '.$e->getMessage());
        }

        return $providers;
    }

    /**
     * Results of the scope "global-mailboxes" (schema search-results 1.0) for $context['user_id'] or the logged-in user.
     * Context: ['scope' => 'global-mailboxes', 'query' => text, 'limit' => int, 'user_id' => int, 'mailbox_ids' => int[]].
     */
    public static function searchResults(array $context)
    {
        try {
            $user = !empty($context['user_id']) ? \App\User::find((int) $context['user_id']) : auth()->user();
            if (!$user) {
                return null;
            }
            $mailboxes = is_array($context['mailbox_ids'] ?? null) ? $context['mailbox_ids'] : [];

            return GlobalSearch::run($user, $context['query'] ?? '', $context['limit'] ?? GlobalSearch::DEFAULT_LIMIT, $mailboxes);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.search.results: '.$e->getMessage());

            return null;
        }
    }

    protected static function moduleVersion()
    {
        $data = json_decode((string) @file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

        return is_array($data) ? (string) ($data['version'] ?? '') : '';
    }
}
