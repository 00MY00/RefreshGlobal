<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Modules\RefreshGlobal\Services\MailTags;

/**
 * Details and raw text of one message of a ticket (GET /refresh-global/thread/{id}/details), shown in a window on the
 * ticket page (Public/js/shell.js threadDetails()). Refresh hides FreeScout's message menu, and with it its "Show
 * original" window (resources/views/conversations/partials/thread.blade.php:333-336): this window gives the details
 * (sender, recipients, date, Message-ID, mailbox, origin tags and folder of the mail server), the raw text (e-mail
 * headers kept by FreeScout, threads.headers, and the message as plain text), and a link to FreeScout's own
 * original source (conversations.ajax_html, action show_original, ConversationsController.php:2606-2643).
 * Same permission as FreeScout: the user can view the ticket.
 */
class ThreadDetailsController extends Controller
{
    public function show($id)
    {
        $thread = \App\Thread::find((int) $id);
        if (!$thread || !$thread->conversation) {
            abort(404);
        }
        if (!auth()->user()->can('view', $thread->conversation)) {
            abort(403);
        }
        $tags = MailTags::forThreads([$thread->id])[$thread->id] ?? ['tags' => [], 'folder' => ''];

        return response(view('refreshglobal::thread_details', [
            'thread'       => $thread,
            'conversation' => $thread->conversation,
            'mailbox'      => $thread->conversation->mailbox,
            'tags'         => $tags,
            'plain'        => self::plainText((string) $thread->body),
            'original_url' => $thread->isReply() && \Route::has('conversations.ajax_html')
                ? route('conversations.ajax_html', ['action' => 'show_original', 'thread_id' => $thread->id]) : '',
        ])->render())->header('Cache-Control', 'no-store');
    }

    /** HTML message as plain text: line breaks kept, tags removed, entities decoded. */
    public static function plainText($html)
    {
        $text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = preg_replace('#</(p|div|tr|li|h[1-6]|blockquote)>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
