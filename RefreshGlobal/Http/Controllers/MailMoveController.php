<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use App\Conversation;
use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\MailMove;
use Modules\RefreshGlobal\Services\Settings;

/**
 * "Move to a folder" of the mail server (Services/MailMove.php), JSON for Public/js/shell.js (ticket page, list:
 * swipe to the left on touch screens, selection bar): folders of a ticket's mailbox, move one or several tickets,
 * undo. Each ticket is checked: the user may change it (FreeScout's policy), its mailbox is an IMAP one; the folder
 * must be one of that mailbox. Nothing else of the ticket changes.
 */
class MailMoveController extends Controller
{
    /** Folders of the mailbox of a ticket, with the folder(s) its e-mails are in now. */
    public function folders(Request $request)
    {
        $conversation = $this->conversation($request->input('conversation_id'));
        if (!$conversation) {
            return $this->fail(__('refreshglobal::messages.move_forbidden'), 403);
        }
        try {
            $folders = MailMove::folders($conversation->mailbox, (bool) $request->input('fresh'));
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [mail-move] folders: '.$e->getMessage());

            return $this->fail(__('refreshglobal::messages.move_server_error', ['error' => $e->getMessage()]));
        }

        return response()->json(['status' => 'success', 'mailbox' => $conversation->mailbox->name, 'folders' => $folders,
            'current' => array_values(array_unique(array_filter(MailMove::currentFolders($conversation))))]);
    }

    /** Moves the e-mails of one or several tickets (conversation_id or conversation_ids[]) to a folder. */
    public function move(Request $request)
    {
        $this->validate($request, ['folder' => 'required|string|max:500', 'conversation_id' => 'nullable|integer',
            'conversation_ids' => 'nullable|array|max:200', 'conversation_ids.*' => 'nullable|integer']);
        $ids = array_filter(array_merge([(int) $request->input('conversation_id')], array_map('intval', (array) $request->input('conversation_ids', []))));
        if (!$ids) {
            return $this->fail(__('refreshglobal::messages.move_forbidden'), 403);
        }
        $out = ['status' => 'success', 'moved' => 0, 'not_found' => 0, 'tickets' => [], 'errors' => [], 'label' => ''];
        foreach (array_unique($ids) as $id) {
            $conversation = $this->conversation($id);
            if (!$conversation) {
                $out['errors'][] = '#'.$id.' : '.__('refreshglobal::messages.move_forbidden');
                continue;
            }
            try {
                $r = MailMove::move($conversation, $request->input('folder'));
                $out['moved'] += $r['moved'];
                $out['not_found'] += $r['not_found'];
                $out['label'] = $r['label'];
                $out['tickets'][] = ['conversation_id' => $conversation->id, 'previous' => $r['previous'], 'moved' => $r['moved']];
            } catch (\Throwable $e) {
                $out['errors'][] = '#'.$conversation->number.' : '.$e->getMessage();
            }
        }
        if (!$out['tickets']) {
            return $this->fail(implode(' — ', $out['errors']) ?: __('refreshglobal::messages.move_forbidden'));
        }
        $out['message'] = $out['moved']
            ? trans_choice('refreshglobal::messages.move_done', $out['moved'], ['count' => $out['moved'], 'folder' => $out['label']])
            : __('refreshglobal::messages.move_nothing');
        if ($out['not_found']) {
            $out['message'] .= ' '.trans_choice('refreshglobal::messages.move_not_found', $out['not_found'], ['count' => $out['not_found']]);
        }

        return response()->json($out);
    }

    /** Undo: tickets = [{conversation_id, previous: {thread id: folder}}] as returned by move(). */
    public function undo(Request $request)
    {
        $tickets = json_decode((string) $request->input('tickets'), true);
        if (!is_array($tickets) || count($tickets) > 200) {
            return $this->fail(__('refreshglobal::messages.move_forbidden'), 422);
        }
        $done = 0;
        $errors = [];
        foreach ($tickets as $t) {
            $conversation = is_array($t) ? $this->conversation($t['conversation_id'] ?? 0) : null;
            if (!$conversation || !is_array($t['previous'] ?? null)) {
                continue;
            }
            try {
                $done += MailMove::undo($conversation, $t['previous'])['moved'];
            } catch (\Throwable $e) {
                $errors[] = '#'.$conversation->number.' : '.$e->getMessage();
            }
        }

        return response()->json(['status' => $errors && !$done ? 'error' : 'success', 'moved' => $done, 'errors' => $errors,
            'message' => trans_choice('refreshglobal::messages.move_undone', max(1, $done), ['count' => $done])]);
    }

    /** The ticket if the feature is on, the user may change it and its mailbox is IMAP; null otherwise. */
    protected function conversation($id)
    {
        if (!Settings::mailMove() || !is_scalar($id) || !preg_match('/^\d{1,10}$/', (string) $id)) {
            return null;
        }
        $conversation = Conversation::find((int) $id);
        if (!$conversation || !MailMove::allowed(auth()->user(), $conversation) || !MailMove::supported($conversation->mailbox)) {
            return null;
        }

        return $conversation;
    }

    protected function fail($message, $code = 200)
    {
        return response()->json(['status' => 'error', 'message' => $message], $code);
    }
}
