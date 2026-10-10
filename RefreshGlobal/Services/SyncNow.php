<?php

namespace Modules\RefreshGlobal\Services;

use App\Mailbox;

/**
 * "Fetch e-mails now" button of the "All mailboxes" page, when the third-party module SyncNow is installed and active
 * (https://github.com/rabsym/freescout-syncnow, alias "syncnow", version read: 1.3.0).
 *
 * The button does exactly what SyncNow's own page does, for each IMAP mailbox shown (Public/js/refreshglobal.js):
 *  - POST syncnow.force  (/mailbox/{id}/syncnow/force)  → {status: running, sync_token} | cooldown | locked | skipped | error
 *  - GET  syncnow.status (/mailbox/{id}/syncnow/status?sync_token&offset) until the status is no longer "running";
 *    this second call also records SyncNow's history and releases its lock (Http/Controllers/SyncNowController.php:335-441).
 * Rights are SyncNow's (SyncNowController::canSync(), :534-544): administrators, or the permission
 * "syncnow.force_sync" (Entities/SyncNowLog.php) + access to the mailbox; SyncNow checks them again on every call.
 * Only IMAP mailboxes (SyncNowController.php:164). Nothing of SyncNow is modified or copied.
 */
class SyncNow
{
    const ALIAS = 'syncnow';
    const PERMISSION = 'syncnow.force_sync';

    public static function available()
    {
        try {
            return \App\Module::isActive(self::ALIAS) && \Route::has('syncnow.force') && \Route::has('syncnow.status');
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function userCanSync($user)
    {
        return $user && ($user->isAdmin() || $user->hasPermission(self::PERMISSION));
    }

    /**
     * IMAP mailboxes of the list the user may fetch with SyncNow ([] when SyncNow is not there or not allowed).
     *
     * @param int[] $only mailbox filter of the page (empty = every mailbox of the user)
     * @return array[] [['id', 'name', 'force', 'status'], …]
     */
    public static function mailboxes($user, array $only = [])
    {
        if (!self::available() || !self::userCanSync($user)) {
            return [];
        }
        $only = array_map('intval', $only);
        $out = [];
        foreach ((new MailboxAccess($user))->mailboxes() as $mailbox) {
            if ((int) $mailbox->in_protocol !== Mailbox::IN_PROTOCOL_IMAP || !$mailbox->in_server) {
                continue;
            }
            if ($only && !in_array((int) $mailbox->id, $only, true)) {
                continue;
            }
            $out[] = [
                'id'     => (int) $mailbox->id,
                'name'   => (string) $mailbox->name,
                'force'  => route('syncnow.force', ['mailbox_id' => $mailbox->id]),
                'status' => route('syncnow.status', ['mailbox_id' => $mailbox->id]),
            ];
        }

        return $out;
    }
}
