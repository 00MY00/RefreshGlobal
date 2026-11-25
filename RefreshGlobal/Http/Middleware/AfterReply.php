<?php

namespace Modules\RefreshGlobal\Http\Middleware;

use App\Conversation;
use App\MailboxUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;
use Modules\RefreshGlobal\Services\Settings;

/**
 * Where a reply or a note leads (1.24.0): FreeScout's POST conversations.ajax, action send_reply, with "next active
 * conversation" (its default, ConversationsController::getRedirectUrl(), app/Http/Controllers/ConversationsController.php:2953).
 * When there is no next ticket FreeScout (and Refresh, for its views) goes back to a list: the agent now stays on the
 * ticket, unless it was closed. Setting "next ticket of All mailboxes": the ticket that followed in the list last shown,
 * when the ticket was in it. "Stay on page" and "back to folder" are never changed.
 *
 * Added twice to the "web" group: last ("inner": session and user are there, the list is read before the ticket moves
 * in it) and first ("outer": the answer is changed after the other modules'). Only the redirect_url of a successful
 * answer is changed.
 */
class AfterReply
{
    const ATTRIBUTE = 'refreshglobal.after_reply';
    const MAX_IDS = 2000;

    public function handle($request, Closure $next, $role = 'inner')
    {
        if ($role === 'outer') {
            $response = $next($request);
            try {
                $this->redirect($request, $response);
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [after-reply] '.get_class($e));
            }

            return $response;
        }
        try {
            $this->prepare($request);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [after-reply] '.get_class($e));
        }

        return $next($request);
    }

    protected function prepare($request)
    {
        $route = $request->route();
        if (!$route || $route->getName() !== 'conversations.ajax' || !$request->isMethod('POST') || !auth()->check()
            || $request->input('action') !== 'send_reply' || $request->input('is_create') || !is_numeric($request->input('conversation_id'))) {
            return;
        }
        $conversation = Conversation::find((int) $request->input('conversation_id'));
        if (!$conversation || !$conversation->mailbox) {
            return;
        }
        $after = (int) $request->input('after_send') ?: (int) $conversation->mailbox->getUserSettings(auth()->id())->after_send;
        if ($after !== MailboxUser::AFTER_SEND_NEXT) {
            return;
        }
        $request->attributes->set(self::ATTRIBUTE, ['id' => (int) $conversation->id, 'url' => $conversation->url(),
            'ids' => Settings::replyGoesNext() ? $this->following((int) $conversation->id) : []]);
    }

    /** Tickets to look for after this one, in the order of the "All mailboxes" list last shown ([] when it is not in it). */
    protected function following($id)
    {
        $last = session(AfterDelete::SESSION_KEY);
        if (!is_array($last) || !isset($last['filters'])) {
            return [];
        }
        $access = new MailboxAccess(auth()->user());
        $ids = array_map('intval', (new GlobalTicketQuery($access, GlobalTicketQuery::normalize((array) $last['filters'], $access)))->query()
            ->limit(self::MAX_IDS)->pluck('conversations.id')->all());
        $i = array_search($id, $ids, true);

        return $i === false ? [] : array_merge(array_slice($ids, $i + 1), array_reverse(array_slice($ids, 0, $i)));
    }

    protected function redirect($request, $response)
    {
        $todo = $request->attributes->get(self::ATTRIBUTE);
        if (!is_array($todo) || !($response instanceof JsonResponse)) {
            return;
        }
        $data = $response->getData(true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success' || !is_string($data['redirect_url'] ?? null) || $data['redirect_url'] === '') {
            return;
        }
        $url = null;
        if ($todo['ids']) {
            // rights again, after the reply: open and pending tickets of the agent's mailboxes
            $access = new MailboxAccess(auth()->user());
            $query = Conversation::whereIn('id', $todo['ids'])->whereIn('mailbox_id', $access->allowedIds() ?: [0])
                ->where('state', Conversation::STATE_PUBLISHED)->whereIn('status', [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING]);
            if ($access->onlyAssigned()) {
                $query->where('user_id', auth()->id());
            }
            $open = array_flip($query->pluck('id')->all());
            foreach ($todo['ids'] as $id) {
                if (isset($open[$id])) {
                    $url = route('conversations.view', ['id' => $id]);
                    break;
                }
            }
        }
        if ($url === null && Settings::replyStays()) {
            $url = $todo['url'];
        } elseif ($url === null && !preg_match('#/conversation/\d+#', $data['redirect_url'])
            && (int) Conversation::where('id', $todo['id'])->value('status') !== Conversation::STATUS_CLOSED) {
            $url = $todo['url'];
        }
        if ($url !== null) {
            $data['redirect_url'] = $url;
            $response->setData($data);
        }
    }
}
