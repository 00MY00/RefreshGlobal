<?php

namespace Modules\RefreshGlobal\Services\Search;

use App\Conversation;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * Search provider "refreshglobal.global-search" of the FreeScout Interop contract (docs/INTEROPERABILITY.md, search):
 * tickets of every mailbox the user may see, matching a text (subject, customer e-mail or name, ticket number) —
 * the same search as the "All mailboxes" page (GlobalTicketQuery::applySearch()).
 *
 * Reached three ways, all with the same rights (MailboxAccess, "only assigned" right, published tickets, no spam):
 *  - GET /refresh-global/search (Http/Controllers/SearchController.php), for the browser provider;
 *  - the server filter fsinterop.search.results (Services/Interop/ServerHooks.php), for other modules;
 *  - this class, inside the module.
 * Results follow the schema search-results 1.0 (docs/interop/schemas/search-results-1.0.schema.json).
 */
class GlobalSearch
{
    const PROVIDER_ID = 'refreshglobal.global-search';
    const CONTEXT = 'global-mailboxes';
    const SCHEMA = '1.0';

    const DEFAULT_LIMIT = 10;
    const MAX_LIMIT = 50;

    /** Shortest text searched (one character matches far too many tickets to be useful). */
    const MIN_LENGTH = 2;

    /** Descriptor of the provider (browser registration and fsinterop.search.providers), schema search-provider 1.0. */
    public static function provider()
    {
        return [
            'id'       => self::PROVIDER_ID,
            'owner'    => 'refresh-global',
            'context'  => self::CONTEXT,
            'version'  => self::SCHEMA,
            'label'    => __('refreshglobal::messages.menu'),
            'endpoint' => route('refreshglobal.search'),
        ];
    }

    /**
     * Tickets matching $text for $user, most recently updated first.
     *
     * @param \App\User $user
     * @param string    $text       searched text (trimmed, cut to GlobalTicketQuery::MAX_QUERY_LENGTH)
     * @param int       $limit      1 … MAX_LIMIT
     * @param int[]     $mailboxIds optional: only these mailboxes (intersected with the user's)
     * @return array search-results 1.0
     */
    public static function run($user, $text, $limit = self::DEFAULT_LIMIT, array $mailboxIds = [])
    {
        $text = self::cleanText($text);
        $limit = self::cleanLimit($limit);
        $result = self::emptyResult($text, $limit);
        if (!$user || mb_strlen($text) < self::MIN_LENGTH) {
            return $result;
        }

        $access = new MailboxAccess($user);
        $input = ['q' => $text];
        if ($mailboxIds) {
            $input['mb'] = array_values(array_map('intval', $mailboxIds));
        }
        $query = new GlobalTicketQuery($access, GlobalTicketQuery::normalize($input, $access));

        $result['total'] = $query->base()->count();
        $names = $access->mailboxes()->pluck('name', 'id');
        foreach ($query->query()->with('customer')->take($limit)->get() as $conversation) {
            $result['items'][] = self::item($conversation, $names);
        }

        return $result;
    }

    /** One result (no message bodies: the provider only points to the ticket). */
    public static function item(Conversation $conversation, $mailboxNames)
    {
        $customer = $conversation->customer;

        return [
            'id'             => (int) $conversation->id,
            'number'         => (int) $conversation->number,
            'subject'        => (string) $conversation->getSubject(),
            'status'         => self::statusKey((int) $conversation->status),
            'mailbox'        => ['id' => (int) $conversation->mailbox_id, 'name' => (string) ($mailboxNames[$conversation->mailbox_id] ?? '')],
            'customer'       => [
                'name'  => $customer ? trim($customer->first_name.' '.$customer->last_name) : '',
                'email' => (string) $conversation->customer_email,
            ],
            'assignee_id'    => $conversation->user_id ? (int) $conversation->user_id : null,
            'updated_at'     => $conversation->updated_at ? $conversation->updated_at->toIso8601String() : null,
            'url'            => $conversation->url(),
        ];
    }

    /** Neutral status name of the contract (independent of FreeScout's translated names). */
    public static function statusKey($status)
    {
        $keys = [
            Conversation::STATUS_ACTIVE  => 'active',
            Conversation::STATUS_PENDING => 'pending',
            Conversation::STATUS_CLOSED  => 'closed',
            Conversation::STATUS_SPAM    => 'spam',
        ];

        return $keys[$status] ?? 'unknown';
    }

    /** Text as searched: a string, trimmed, at most MAX_QUERY_LENGTH characters. */
    public static function cleanText($text)
    {
        return is_scalar($text) ? mb_substr(trim((string) $text), 0, GlobalTicketQuery::MAX_QUERY_LENGTH) : '';
    }

    /** Number of results asked: DEFAULT_LIMIT when invalid, never above MAX_LIMIT. */
    public static function cleanLimit($limit)
    {
        $limit = is_numeric($limit) ? (int) $limit : self::DEFAULT_LIMIT;

        return $limit >= 1 ? min($limit, self::MAX_LIMIT) : self::DEFAULT_LIMIT;
    }

    /** Result without any ticket. */
    protected static function emptyResult($text, $limit)
    {
        return [
            'schema'        => self::SCHEMA,
            'source_module' => 'refresh-global',
            'provider'      => self::PROVIDER_ID,
            'context'       => self::CONTEXT,
            'query'         => $text,
            'limit'         => $limit,
            'total'         => 0,
            'items'         => [],
        ];
    }
}
