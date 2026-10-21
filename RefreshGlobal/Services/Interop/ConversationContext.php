<?php

namespace Modules\RefreshGlobal\Services\Interop;

use App\Conversation;
use Modules\RefreshGlobal\Services\MailMove;
use Modules\RefreshGlobal\Services\MailTags;
use Modules\RefreshGlobal\Services\Search\GlobalSearch;
use Modules\RefreshGlobal\Services\Settings;

/**
 * Server filter fsinterop.conversation.context (FreeScout Interop, docs/INTEROPERABILITY.md): what the participating
 * modules know about one ticket, for one user — read-only data another module (e.g. Refresh's ticket page) may show,
 * without calling RefreshGlobal's classes.
 *
 *   $context = \Eventy::filter('fsinterop.conversation.context', $base, $conversation, $user);
 *
 * Base (schema 1.0, built by base()): conversation_id, mailbox_id, number, status (neutral name), user_id, modules {}.
 * Each module adds ONLY its own entry modules[<module id>]. RefreshGlobal adds (addOwn()):
 *   all_mailboxes_url  the "All mailboxes" list
 *   mail_tags          origin tags of the ticket's e-mails (when that feature is on)
 *   actions            ids of its intents usable on this ticket ("refreshglobal.move-to-folder")
 * Nothing is added when the user may not view the ticket (FreeScout's ConversationPolicy::view).
 */
class ConversationContext
{
    const SCHEMA = '1.0';
    const OWN_ID = 'refresh-global';

    /**
     * Context of a ticket for a user after every module's listener, or null when the user may not view it.
     *
     * @param \App\Conversation $conversation
     * @param \App\User         $user
     */
    public static function resolve($conversation, $user)
    {
        if (!self::canView($conversation, $user)) {
            return null;
        }
        $base = self::base($conversation, $user);
        try {
            $filtered = \Eventy::filter('fsinterop.conversation.context', $base, $conversation, $user);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [interop] fsinterop.conversation.context: '.$e->getMessage());
            $filtered = $base;
        }
        // listeners may only add entries under "modules"; the base fields stay as built here
        $base['modules'] = is_array($filtered) && is_array($filtered['modules'] ?? null) ? $filtered['modules'] : [];

        return $base;
    }

    /** Neutral data of the ticket, before any module's entry. */
    public static function base(Conversation $conversation, $user)
    {
        return [
            'schema'          => self::SCHEMA,
            'conversation_id' => (int) $conversation->id,
            'mailbox_id'      => (int) $conversation->mailbox_id,
            'number'          => (int) $conversation->number,
            'status'          => GlobalSearch::statusKey((int) $conversation->status),
            'user_id'         => $user ? (int) $user->id : null,
            'modules'         => [],
        ];
    }

    /** RefreshGlobal's own listener: adds modules["refresh-global"]. */
    public static function addOwn($context, $conversation = null, $user = null)
    {
        if (!is_array($context) || !self::canView($conversation, $user)) {
            return $context;
        }
        $context['modules'][self::OWN_ID] = [
            'all_mailboxes_url' => route('refreshglobal.tickets'),
            'mail_tags'         => self::mailTags($conversation),
            'actions'           => self::actions($conversation, $user),
        ];

        return $context;
    }

    /** Origin tags of the ticket's e-mails, [] when the feature is off. */
    protected static function mailTags(Conversation $conversation)
    {
        if (!MailTags::enabled()) {
            return [];
        }
        $tags = MailTags::forConversations([(int) $conversation->id]);

        return array_values((array) ($tags[(int) $conversation->id] ?? []));
    }

    /** Intents of RefreshGlobal the user can run on this ticket. */
    protected static function actions(Conversation $conversation, $user)
    {
        $actions = [];
        if (Settings::mailMove() && MailMove::supported($conversation->mailbox) && MailMove::allowed($user, $conversation)) {
            $actions[] = 'refreshglobal.move-to-folder';
        }

        return $actions;
    }

    /** FreeScout's own right to view the ticket (ConversationPolicy::view, app/Policies/ConversationPolicy.php:22-38). */
    protected static function canView($conversation, $user)
    {
        try {
            return $conversation instanceof Conversation && $user && $user->can('view', $conversation);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
