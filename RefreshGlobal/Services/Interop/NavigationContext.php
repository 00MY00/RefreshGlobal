<?php

namespace Modules\RefreshGlobal\Services\Interop;

use Modules\RefreshGlobal\Services\GlobalDashboard;
use Modules\RefreshGlobal\Services\Settings;

/**
 * Server filter fsinterop.navigation.context (FreeScout Interop, docs/INTEROPERABILITY.md: context-aware ownership):
 * which module renders which surface on the page being built.
 *
 * The context starts from FreeScout's route ("dashboard", "mailbox", "conversation", "global-mailboxes" or "other");
 * each participating module then CLAIMS the surfaces it renders in that context, as "surface@scope" => module id
 * ("ticket-list@global-mailboxes" => "refresh-global"). Claims are keyed by scope, so Refresh's mailbox dashboard and
 * RefreshGlobal's global dashboard never compete; an existing claim is never overwritten.
 *
 * RefreshGlobal publishes the result to the browser (<meta name="refreshglobal-interop">, key "page"), where
 * Public/js/interop-adapter.js registers its own surfaces from it (FreeScoutInterop.surfaces).
 *
 * Shape (schema 1.0): ['schema', 'route', 'context', 'mailbox_id', 'conversation_id', 'user_id', 'owners' => [...]]
 */
class NavigationContext
{
    const SCHEMA = '1.0';
    const OWN_ID = 'refresh-global';

    /** Route names of FreeScout (and of Refresh's mailbox list) => context. */
    const ROUTE_CONTEXTS = [
        'dashboard'             => 'dashboard',
        'mailboxes.view'        => 'mailbox',
        'mailboxes.view.folder' => 'mailbox',
        'refresh.tickets'       => 'mailbox',
        'conversations.view'    => 'conversation',
    ];

    /** Context of the current request after every module's claims, or null outside a logged-in page. */
    public static function current()
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return self::resolve(self::base(\Route::currentRouteName(), request()->route() ? request()->route()->parameters() : [], (int) $user->id));
    }

    /**
     * The starting context of a route, before any claim.
     *
     * @param string|null $route      route name
     * @param array       $parameters route parameters (id, mailbox_id…)
     */
    public static function base($route, array $parameters, $userId)
    {
        $route = (string) $route;
        $context = strpos($route, 'refreshglobal.') === 0 ? 'global-mailboxes' : (self::ROUTE_CONTEXTS[$route] ?? 'other');

        return [
            'schema'          => self::SCHEMA,
            'route'           => $route,
            'context'         => $context,
            'mailbox_id'      => self::mailboxId($context, $parameters),
            'conversation_id' => $context === 'conversation' && isset($parameters['id']) ? (int) $parameters['id'] : null,
            'user_id'         => (int) $userId,
            'owners'          => [],
        ];
    }

    /**
     * Applies fsinterop.navigation.context and keeps only what the contract allows: the starting fields cannot be
     * changed by a listener, only "owners" can grow (string => string).
     */
    public static function resolve(array $base)
    {
        try {
            $filtered = \Eventy::filter('fsinterop.navigation.context', $base);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.navigation.context: '.$e->getMessage());
            $filtered = $base;
        }
        $owners = [];
        foreach ((is_array($filtered) && is_array($filtered['owners'] ?? null)) ? $filtered['owners'] : [] as $key => $module) {
            if (is_string($key) && is_string($module) && preg_match('/^[a-z0-9-]+@[a-z0-9*-]+$/', $key)) {
                $owners[$key] = $module;
            }
        }
        $base['owners'] = $owners;

        return $base;
    }

    /** RefreshGlobal's own listener: claims the surfaces it renders in this context. */
    public static function addOwnClaims($context)
    {
        if (!is_array($context)) {
            return $context;
        }
        foreach (self::ownSurfaces((string) ($context['context'] ?? '')) as $key) {
            if (!isset($context['owners'][$key])) {
                $context['owners'][$key] = self::OWN_ID;
            }
        }

        return $context;
    }

    /** "surface@scope" keys RefreshGlobal renders in a context. */
    public static function ownSurfaces($context)
    {
        if ($context === 'global-mailboxes') {
            return ['ticket-list@global-mailboxes', 'saved-views@global-mailboxes', 'search@global-mailboxes'];
        }
        if ($context === 'dashboard' && Settings::globalDashboard() && GlobalDashboard::renderedMode() !== null) {
            return ['dashboard@global-mailboxes'];
        }

        return [];
    }

    /** Mailbox of a mailbox page (FreeScout: {id}, Refresh: {mailbox_id}). */
    protected static function mailboxId($context, array $parameters)
    {
        if ($context !== 'mailbox') {
            return null;
        }
        $id = $parameters['mailbox_id'] ?? ($parameters['id'] ?? null);

        return is_numeric($id) ? (int) $id : null;
    }
}
