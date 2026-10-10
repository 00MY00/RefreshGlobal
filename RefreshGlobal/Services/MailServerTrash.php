<?php

namespace Modules\RefreshGlobal\Services;

use App\Mailbox;
use App\Thread;

/**
 * When a ticket is deleted for good, its e-mails are also moved to the TRASH FOLDER of the mail server (they can still
 * be restored from the webmail). FreeScout itself never touches the mail server: it only copies the e-mails.
 *
 * 1. Queue: FreeScout fires "conversations.before_delete_forever" (app/Conversation.php:2163) before removing the
 *    tickets. If the deletion was asked by a user (see arm(): "Delete forever", bulk delete, FreeScout's or the module's
 *    "Empty trash", automatic emptying), the Message-ID of each e-mail of these tickets is stored in the table
 *    refreshglobal_mail_deletions. Other permanent deletions of FreeScout (a whole mailbox deleted, an e-mail that
 *    could not be imported) are ignored: their e-mails stay on the server.
 * 2. Processing: the scheduled task refreshglobal:mail-trash (every minute) connects to each mailbox with FreeScout's
 *    own IMAP client (MailHelper::getMailboxClient(), app/Misc/Mail.php:808), looks for each e-mail by its Message-ID
 *    in the folders FreeScout fetches (Mailbox::getInImapFolders(), app/Mailbox.php:906) — agent replies in the
 *    mailbox's "Sent" folder (imap_sent_folder) — and moves it to the server's trash folder (Message::move(), MOVE or
 *    COPY + delete). POP3 mailboxes: nothing can be moved (skipped).
 */
class MailServerTrash
{
    const TABLE = 'refreshglobal_mail_deletions';
    const MAX_ATTEMPTS = 5;
    const BATCH = 200;

    /** Usual names of the trash folder, compared without case (the first one found on the server is used). */
    const TRASH_NAMES = [
        'Trash', 'INBOX.Trash', 'INBOX/Trash', 'Deleted Items', 'Deleted Messages', 'Deleted', 'INBOX.Deleted Items',
        '[Gmail]/Trash', '[Google Mail]/Trash', '[Gmail]/Corbeille', '[Gmail]/Papierkorb', '[Gmail]/Papelera', '[Gmail]/Cestino',
        'Corbeille', 'INBOX.Corbeille', 'INBOX/Corbeille', 'Éléments supprimés', 'Papierkorb', 'Gelöschte Elemente',
        'Gelöschte Objekte', 'Papelera', 'Elementos eliminados', 'Cestino', 'Posta eliminata', 'Prullenbak',
        'Verwijderde items', 'Lixeira', 'Itens Excluídos',
    ];

    /** True while a deletion asked by a user runs (set around FreeScout's actions and the module's own deletions). */
    protected static $armed = 0;

    public static function arm()
    {
        self::$armed++;
    }

    public static function disarm()
    {
        self::$armed = max(0, self::$armed - 1);
    }

    public static function enabled()
    {
        return Settings::serverTrash();
    }

    public static function tableExists()
    {
        try {
            return \Schema::hasTable(self::TABLE);
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Listener of "conversations.before_delete_forever": the tickets and their threads still exist here. */
    public static function queue($conversation_ids)
    {
        if (!self::$armed || !self::enabled() || !self::tableExists()) {
            return;
        }
        $conversation_ids = array_values(array_filter(array_map('intval', (array) $conversation_ids)));
        if (!$conversation_ids) {
            return;
        }
        try {
            $now = date('Y-m-d H:i:s');
            foreach (array_chunk($conversation_ids, 500) as $chunk) {
                $rows = Thread::query()
                    ->join('conversations', 'conversations.id', '=', 'threads.conversation_id')
                    ->whereIn('threads.conversation_id', $chunk)
                    ->whereNotNull('threads.message_id')->where('threads.message_id', '!=', '')
                    ->whereIn('threads.type', [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE])
                    ->get(['conversations.mailbox_id', 'threads.message_id', 'threads.type']);
                $insert = [];
                $seen = [];
                foreach ($rows as $row) {
                    $id = trim((string) $row->message_id, " <>\t\r\n");
                    $folder = (int) $row->type === Thread::TYPE_CUSTOMER ? 'in' : 'sent';
                    $key = $row->mailbox_id.'|'.$id.'|'.$folder;
                    if ($id === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $insert[] = ['mailbox_id' => (int) $row->mailbox_id, 'message_id' => mb_substr($id, 0, 998), 'folder' => $folder,
                        'status' => 'pending', 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now];
                }
                foreach (array_chunk($insert, 200) as $part) {
                    \DB::table(self::TABLE)->insert($part);
                }
            }
        } catch (\Throwable $e) {
            // never block FreeScout's deletion
            \Log::warning('[RefreshGlobal] [mail-trash] queue: '.$e->getMessage());
        }
    }

    /** Number of e-mails per status (settings page). */
    public static function counts()
    {
        if (!self::tableExists()) {
            return [];
        }

        return \DB::table(self::TABLE)->select('status', \DB::raw('COUNT(*) AS n'))->groupBy('status')->pluck('n', 'status')->all();
    }

    public static function lastError()
    {
        if (!self::tableExists()) {
            return null;
        }

        return \DB::table(self::TABLE)->whereNotNull('error')->orderBy('updated_at', 'desc')->first(['error', 'updated_at']);
    }

    /**
     * Moves the pending e-mails (scheduled task). $client_factory (tests) returns an IMAP client for a mailbox.
     * Returns ['moved' => n, 'not_found' => n, 'failed' => n, 'skipped' => n].
     */
    public static function process(callable $out = null, callable $client_factory = null)
    {
        $out = $out ?: function () {
        };
        $client_factory = $client_factory ?: function (Mailbox $mailbox) {
            return \MailHelper::getMailboxClient($mailbox);
        };
        $done = ['moved' => 0, 'not_found' => 0, 'failed' => 0, 'skipped' => 0];
        $pending = \DB::table(self::TABLE)->where('status', 'pending')->orderBy('id')->limit(self::BATCH)->get();
        foreach ($pending->groupBy('mailbox_id') as $mailbox_id => $rows) {
            $mailbox = Mailbox::find($mailbox_id);
            if (!$mailbox || (int) $mailbox->in_protocol !== Mailbox::IN_PROTOCOL_IMAP || !$mailbox->in_server) {
                self::mark($rows, 'skipped', $mailbox ? 'not an IMAP mailbox' : 'mailbox deleted');
                $done['skipped'] += count($rows);
                continue;
            }
            try {
                $client = $client_factory($mailbox);
                $client->connect();
                $trash = self::trashFolder($client);
                if (!$trash) {
                    throw new \RuntimeException('trash folder not found on the mail server (set it in Manage › Settings › RefreshGlobal)');
                }
                $folders = ['in' => [], 'sent' => []];
                foreach ($mailbox->getInImapFolders() as $name) {
                    $f = self::folder($client, $name);
                    if ($f && $f->path !== $trash->path) {
                        $folders['in'][] = $f;
                    }
                }
                if ($mailbox->imap_sent_folder) {
                    $f = self::folder($client, $mailbox->imap_sent_folder);
                    if ($f && $f->path !== $trash->path) {
                        $folders['sent'][] = $f;
                    }
                }
            } catch (\Throwable $e) {
                $out('Mailbox '.$mailbox->name.': '.$e->getMessage());
                foreach ($rows as $row) {
                    self::retry($row, $e->getMessage());
                }
                $done['failed'] += count($rows);
                continue;
            }
            foreach ($rows as $row) {
                try {
                    $found = false;
                    foreach ($folders[$row->folder === 'sent' ? 'sent' : 'in'] as $folder) {
                        foreach ($folder->query()->whereMessageId($row->message_id)->leaveUnread()->get() as $message) {
                            $message->move($trash->path);
                            $found = true;
                        }
                    }
                    self::mark([$row], $found ? 'moved' : 'not_found');
                    $done[$found ? 'moved' : 'not_found']++;
                } catch (\Throwable $e) {
                    self::retry($row, $e->getMessage());
                    $done['failed']++;
                }
            }
            try {
                $client->disconnect();
            } catch (\Throwable $e) {
                // already closed
            }
            $out('Mailbox '.$mailbox->name.': trash folder "'.self::decode($trash->path).'".');
        }

        return $done;
    }

    /** Trash folder of the server: the one set in the settings, otherwise the first usual name found. */
    public static function trashFolder($client)
    {
        $wanted = trim(Settings::serverTrashFolder());
        $folders = $client->getFolders(false);
        $byName = [];
        foreach ($folders as $f) {
            $byName[mb_strtolower(self::decode($f->path))] = $f;
        }
        foreach ($wanted !== '' ? [$wanted] : self::TRASH_NAMES as $name) {
            if (isset($byName[mb_strtolower($name)])) {
                return $byName[mb_strtolower($name)];
            }
        }

        return null;
    }

    protected static function folder($client, $name)
    {
        try {
            return \MailHelper::getImapFolder($client, $name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function decode($path)
    {
        return function_exists('mb_convert_encoding') ? (string) @mb_convert_encoding((string) $path, 'UTF-8', 'UTF7-IMAP') : (string) $path;
    }

    protected static function mark($rows, $status, $error = null)
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row->id;
        }
        \DB::table(self::TABLE)->whereIn('id', $ids ?: [0])->update(['status' => $status, 'error' => $error, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Error: tried again on the next runs, then "failed" after MAX_ATTEMPTS. */
    protected static function retry($row, $error)
    {
        $attempts = (int) $row->attempts + 1;
        \DB::table(self::TABLE)->where('id', $row->id)->update([
            'attempts'   => $attempts,
            'status'     => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
            'error'      => mb_substr($error, 0, 1000),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
