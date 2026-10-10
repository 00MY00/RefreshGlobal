<?php

namespace Modules\RefreshGlobal\Services;

use App\Conversation;

/**
 * FreeScout's trash (tickets in state "deleted") over several mailboxes: count, empty now, empty automatically.
 *
 * Same rules as FreeScout's own "Empty trash" of a mailbox (ConversationsController::ajax "empty_folder",
 * app/Http/Controllers/ConversationsController.php:2203-2260): administrators or users with the permission
 * "Users are allowed to delete conversations" (User::PERM_DELETE_CONVERSATIONS); only mailboxes the user can access
 * (MailboxAccess); a user who sees only assigned tickets deletes only his own. The tickets are removed with
 * FreeScout's own Conversation::deleteConversationsForever() (app/Conversation.php:2161), threads and attachments
 * included. The e-mails on the mail server are not touched.
 *
 * When a ticket goes to the trash FreeScout sets user_updated_at (Conversation::deleteToFolder(), :2125): used as
 * "in the trash since" for the automatic emptying.
 */
class Trash
{
    const CHUNK = 500;

    public static function userCanEmpty($user)
    {
        return $user && ($user->isAdmin() || $user->hasPermission(\App\User::PERM_DELETE_CONVERSATIONS));
    }

    /** Tickets in the trash of the mailboxes the user can access (only his own if he sees only assigned tickets). */
    public static function query($user)
    {
        $access = new MailboxAccess($user);
        $query = Conversation::query()
            ->whereIn('mailbox_id', $access->allowedIds() ?: [0])
            ->where('state', Conversation::STATE_DELETED);
        if ($access->onlyAssigned()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function count($user)
    {
        try {
            return self::userCanEmpty($user) ? self::query($user)->count() : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** Empties the user's trash now; returns the number of tickets deleted for good. */
    public static function emptyFor($user)
    {
        if (!self::userCanEmpty($user)) {
            return 0;
        }

        return self::deleteForever(self::query($user));
    }

    /**
     * Automatic emptying (all mailboxes, scheduled task): tickets in the trash for more than $days days.
     * Returns the number of tickets deleted; 0 days = nothing.
     */
    public static function purgeOlderThan($days)
    {
        $days = (int) $days;
        if ($days < 1) {
            return 0;
        }
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        $query = Conversation::query()
            ->where('state', Conversation::STATE_DELETED)
            ->where(function ($q) use ($cutoff) {
                $q->where('user_updated_at', '<=', $cutoff)
                    ->orWhere(function ($q) use ($cutoff) {
                        $q->whereNull('user_updated_at')->where('updated_at', '<=', $cutoff);
                    });
            });

        return self::deleteForever($query);
    }

    protected static function deleteForever($query)
    {
        $rows = $query->get(['id', 'mailbox_id']);
        if (!count($rows)) {
            return 0;
        }
        $ids = $rows->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        // asked by a user (button) or by the automatic emptying: the e-mails also go to the mail server's trash
        MailServerTrash::arm();
        try {
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                Conversation::deleteConversationsForever($chunk);
            }
        } finally {
            MailServerTrash::disarm();
        }
        foreach (\App\Mailbox::whereIn('id', $rows->pluck('mailbox_id')->unique()->all())->get() as $mailbox) {
            $mailbox->updateFoldersCounters();
        }

        return count($ids);
    }
}
