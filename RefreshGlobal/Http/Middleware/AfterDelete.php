<?php

namespace Modules\RefreshGlobal\Http\Middleware;

use App\Conversation;
use Closure;
use Illuminate\Http\JsonResponse;
use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\MailServerTrash;
use Modules\RefreshGlobal\Services\Settings;

/**
 * Deleting a ticket (FreeScout's POST conversations.ajax, routes/web.php:64, actions of
 * app/Http/Controllers/ConversationsController.php:1944-1990 and 2164-2200).
 *
 * 1. Where to go next. FreeScout sends the agent back to the ticket's own mailbox folder, or to the next ticket of
 *    that folder (ConversationsController::getRedirectUrlAfterDelete()). With this module the agent goes back to the
 *    "All mailboxes" page as last shown (same filters, saved view, page), or, setting "next", to the ticket that
 *    followed in that list. Only the redirect_url of a successful answer is changed.
 * 2. Trash or permanent. Setting "permanent": the ticket and its e-mails are removed from FreeScout at once, with
 *    FreeScout's own action delete_conversation_forever (same permission check), instead of going to the trash.
 *    Nothing is changed on the mail server.
 *
 * The controller does the deletion and the permission checks; this middleware never deletes a ticket the controller
 * did not accept (bulk: only the tickets the controller has just moved to the trash).
 */
class AfterDelete
{
    const SESSION_KEY = 'refreshglobal.last_list';
    const MAX_IDS = 5000; // safety limit on the size of the list used to find the next ticket

    public function handle($request, Closure $next)
    {
        $route = $request->route();
        $action = (string) $request->input('action');
        if (!$route || $route->getName() !== 'conversations.ajax' || !$request->isMethod('POST') || !auth()->check()) {
            return $next($request);
        }

        // Deletions asked by the user that can remove tickets for good: their e-mails also go to the mail server's
        // trash (Services/MailServerTrash.php; FreeScout's own "Empty trash" of a mailbox included)
        $final = in_array($action, ['delete_conversation_forever', 'bulk_delete_conversation', 'empty_folder'], true)
            || ($action === 'delete_conversation' && Settings::deletePermanently());
        if ($final) {
            MailServerTrash::arm();
        }
        try {
            if (!in_array($action, ['delete_conversation', 'delete_conversation_forever', 'bulk_delete_conversation'], true)) {
                return $next($request);
            }

            return $this->handleDelete($request, $next, $action);
        } finally {
            if ($final) {
                MailServerTrash::disarm();
            }
        }
    }

    protected function handleDelete($request, Closure $next, $action)
    {
        try {
            $permanent = Settings::deletePermanently();
            if ($action === 'bulk_delete_conversation') {
                $ids = array_filter(array_map('intval', (array) $request->input('conversation_id')));
                $was_published = $permanent && $ids
                    ? Conversation::whereIn('id', $ids)->where('state', '!=', Conversation::STATE_DELETED)->pluck('id')->all() : [];
                $response = $next($request);
                if ($was_published && $this->succeeded($response)) {
                    $this->finishBulk($was_published);
                }

                return $response;
            }

            if ($permanent && $action === 'delete_conversation') {
                $request->merge(['action' => 'delete_conversation_forever']);
            }
            $target = $this->usable() ? $this->target((int) $request->input('conversation_id')) : null;
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [RG-ERR-04] after delete: '.$e->getMessage());
            $target = null;
        }

        $response = $next($request);

        if ($target === null || !$this->succeeded($response, true)) {
            return $response;
        }
        $data = $response->getData(true);
        $data['redirect_url'] = $target();

        return $response->setData($data);
    }

    /** Remembers the "All mailboxes" list the user is looking at (called by the page). */
    public static function rememberList(array $params, array $filters)
    {
        unset($filters['dropped_mailboxes']);
        session([self::SESSION_KEY => ['params' => $params, 'filters' => $filters]]);
    }

    /** Successful JSON answer of the controller (with a redirect_url when $redirect). */
    protected function succeeded($response, $redirect = false)
    {
        if (!($response instanceof JsonResponse)) {
            return false;
        }
        $data = $response->getData(true);

        return is_array($data) && ($data['status'] ?? '') === 'success' && (!$redirect || !empty($data['redirect_url']));
    }

    /** The module page is shown (not blocked by a missing component). */
    protected function usable()
    {
        $report = CompatibilityChecker::cached();

        return ($report['state'] ?? '') !== 'blocking';
    }

    /**
     * Closure giving the address to open once the ticket is deleted. The list is read BEFORE the deletion (the
     * deleted ticket leaves it); the candidates are checked again AFTER.
     */
    protected function target($conversation_id)
    {
        $last = session(self::SESSION_KEY);
        $params = is_array($last) && isset($last['params']) && is_array($last['params']) ? $last['params'] : [];
        $list_url = route('refreshglobal.tickets', $params ?: ['reset' => 1]);
        // marker read by Public/js/refreshglobal.js: the list opens at the place of the deleted ticket (setting on)
        if ($conversation_id && Settings::keepPosition()) {
            $list_url .= '#rg-deleted='.(int) $conversation_id;
        }

        if (!Settings::deleteGoesNext() || !$conversation_id) {
            return function () use ($list_url) {
                return $list_url;
            };
        }

        $access = new MailboxAccess(auth()->user());
        $filters = GlobalTicketQuery::normalize(is_array($last) && isset($last['filters']) ? (array) $last['filters'] : [], $access);
        $ids = array_map('intval', (new GlobalTicketQuery($access, $filters))->query()
            ->limit(self::MAX_IDS)->pluck('conversations.id')->all());
        $i = array_search((int) $conversation_id, $ids, true);

        return function () use ($ids, $i, $list_url, $access) {
            if ($i === false) {
                return $list_url;
            }
            $candidates = array_merge(array_slice($ids, $i + 1), array_reverse(array_slice($ids, 0, $i)));
            if ($candidates) {
                $alive = array_flip(Conversation::whereIn('id', $candidates)
                    ->whereIn('mailbox_id', $access->allowedIds() ?: [0])
                    ->where('state', Conversation::STATE_PUBLISHED)
                    ->pluck('id')->all());
                foreach ($candidates as $id) {
                    if (isset($alive[$id])) {
                        return route('conversations.view', ['id' => $id]);
                    }
                }
            }

            return $list_url;
        };
    }

    /** Bulk delete with the "permanent" setting: the tickets FreeScout has just put in the trash are removed. */
    protected function finishBulk(array $ids)
    {
        $mailboxes = [];
        foreach (Conversation::whereIn('id', $ids)->where('state', Conversation::STATE_DELETED)->get() as $conversation) {
            $mailboxes[$conversation->mailbox_id] = $conversation->mailbox;
            $conversation->deleteForever();
        }
        foreach ($mailboxes as $mailbox) {
            if ($mailbox) {
                $mailbox->updateFoldersCounters();
            }
        }
    }
}
