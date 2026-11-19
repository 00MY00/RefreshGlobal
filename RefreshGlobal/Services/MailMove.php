<?php

namespace Modules\RefreshGlobal\Services;

use App\Conversation;
use App\Mailbox;
use App\Thread;

/**
 * Moving the e-mails of a ticket to an existing folder of its mailbox ON THE MAIL SERVER (IMAP), with undo.
 * The ticket itself does not change in FreeScout (status, mailbox, assignee): only where its e-mails are kept on the
 * server, and their origin tags (Services/MailTags.php: the tag of the old folder replaced by the new one).
 *
 * Same IMAP access as FreeScout and as Services/MailServerTrash.php: \MailHelper::getMailboxClient() (app/Misc/Mail.php:808),
 * \MailHelper::getImapFolder() (:1307, UTF-8 name -> UTF7-IMAP), webklex Message::move() (overrides/webklex/php-imap/
 * src/Message.php:1038), Client::getFolders() (Client.php:475). The e-mails are found by their Message-ID in the folder
 * recorded when they were fetched (refreshglobal_mail_tags.folder), else in the mailbox's fetch folders.
 *
 * Other modules are told: \Eventy::action('refreshglobal.mail_moved', $conversationId, $threadId, $fromFolder, $toFolder)
 * (folders as UTF-8 paths), after the tags were updated (docs/api-tags.md).
 */
class MailMove
{
    /** Folders of a mailbox are kept 10 minutes (one IMAP connection to list them). */
    const CACHE_MINUTES = 10;

    /** @var callable|null tests: function (Mailbox $mailbox) returning a webklex-like client */
    public static $clientFactory;

    /** Moving needs an IMAP mailbox with a server. */
    public static function supported($mailbox)
    {
        return $mailbox && (int) $mailbox->in_protocol === Mailbox::IN_PROTOCOL_IMAP && trim((string) $mailbox->in_server) !== '';
    }

    /** The user may change this ticket (FreeScout's own policy, app/Policies/ConversationPolicy.php). */
    public static function allowed($user, Conversation $conversation)
    {
        try {
            return $user && $user->can('update', $conversation);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function client(Mailbox $mailbox)
    {
        $client = self::$clientFactory ? call_user_func(self::$clientFactory, $mailbox) : \MailHelper::getMailboxClient($mailbox);
        $client->connect();

        return $client;
    }

    /**
     * Folders of the mailbox on the mail server: [['path' => UTF-8 path, 'label' => "Parent › Child", 'tag' => tag]],
     * sorted, folders that can not hold messages (\Noselect) left out. Cached 10 minutes; $fresh = read them again.
     */
    public static function folders(Mailbox $mailbox, $fresh = false)
    {
        $key = 'refreshglobal.mail_folders.'.$mailbox->id;
        if (!$fresh) {
            $cached = \Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $client = self::client($mailbox);
        $out = [];
        try {
            foreach ($client->getFolders(false) as $folder) {
                if (!empty($folder->no_select)) {
                    continue;
                }
                $path = MailTags::decode($folder->path);
                $delimiter = isset($folder->delimiter) && $folder->delimiter !== '' ? (string) $folder->delimiter : '/';
                $parts = array_values(array_filter(explode($delimiter, $path), function ($p) {
                    return $p !== '';
                }));
                // sub-folders of INBOX shown without it: "[WP] Purchases › Deliveries"
                if (count($parts) > 1 && mb_strtolower($parts[0]) === 'inbox') {
                    array_shift($parts);
                }
                $out[] = ['path' => $path, 'label' => implode(' › ', $parts) ?: $path, 'tag' => (string) MailTags::tagForFolder($path)];
            }
        } finally {
            self::disconnect($client);
        }
        usort($out, function ($a, $b) {
            // INBOX first, then by name
            $ia = mb_strtolower($a['path']) === 'inbox' ? 0 : 1;
            $ib = mb_strtolower($b['path']) === 'inbox' ? 0 : 1;

            return $ia - $ib ?: strnatcasecmp($a['label'], $b['label']);
        });
        \Cache::put($key, $out, self::CACHE_MINUTES);

        return $out;
    }

    /** longest name of a new folder (one level) */
    const MAX_NAME = 100;

    /**
     * Creates a folder in the mailbox on the mail server (1.21.0), as a sub-folder of $parent (UTF-8 path of an existing
     * folder) or at the top level (''). At the top level, when every folder of the server is under INBOX (servers with
     * the "INBOX." namespace), the new folder goes under INBOX too. webklex Client::createFolder()
     * (overrides/webklex/php-imap/src/Client.php:608), name in UTF7-IMAP like FreeScout (\Helper::mbConvertEncoding(),
     * app/Misc/Helper.php:1695, as in \MailHelper::getImapFolder()).
     *
     * @return array the new folder ['path', 'label', 'tag'] (folders read again, cache renewed)
     * @throws \RuntimeException invalid name, unknown parent, folder already there, server error
     */
    public static function createFolder(Mailbox $mailbox, $name, $parent = '')
    {
        if (!self::supported($mailbox)) {
            throw new \RuntimeException(__('refreshglobal::messages.move_not_imap'));
        }
        $name = is_scalar($name) ? trim(preg_replace('/\s+/u', ' ', (string) $name)) : '';
        // control characters, IMAP wildcards and quoting characters are refused; the delimiter is checked below
        if ($name === '' || mb_strlen($name) > self::MAX_NAME || preg_match('/[\x00-\x1F\x7F*%"\\\\]/u', $name)
            || $name === '.' || $name === '..') {
            throw new \RuntimeException(__('refreshglobal::messages.move_new_invalid', ['max' => self::MAX_NAME]));
        }
        $client = self::client($mailbox);
        try {
            $existing = [];
            $delimiter = '/';
            foreach ($client->getFolders(false) as $folder) {
                $path = MailTags::decode($folder->path);
                $existing[mb_strtolower($path)] = $path;
                if (isset($folder->delimiter) && $folder->delimiter !== '' && $folder->delimiter !== null) {
                    $delimiter = (string) $folder->delimiter;
                }
            }
            if (mb_strpos($name, $delimiter) !== false) {
                throw new \RuntimeException(__('refreshglobal::messages.move_new_invalid', ['max' => self::MAX_NAME]));
            }
            $parent = is_scalar($parent) ? trim((string) $parent) : '';
            if ($parent !== '') {
                if (!isset($existing[mb_strtolower($parent)])) {
                    throw new \RuntimeException(__('refreshglobal::messages.move_unknown_folder'));
                }
                $parent = $existing[mb_strtolower($parent)];
            } else {
                $parent = self::topParent(array_values($existing), $delimiter);
            }
            $path = $parent !== '' ? $parent.$delimiter.$name : $name;
            if (isset($existing[mb_strtolower($path)])) {
                throw new \RuntimeException(__('refreshglobal::messages.move_new_exists'));
            }
            $client->createFolder(\Helper::mbConvertEncoding($path, 'UTF7-IMAP', 'UTF-8'), false);
        } finally {
            self::disconnect($client);
        }
        foreach (self::folders($mailbox, true) as $f) {
            if (mb_strtolower($f['path']) === mb_strtolower($path)) {
                return $f;
            }
        }
        throw new \RuntimeException(__('refreshglobal::messages.move_new_failed'));
    }

    /** Parent of a top-level folder: INBOX when every other folder is under it ("INBOX." namespace), else ''. */
    public static function topParent(array $paths, $delimiter)
    {
        $inbox = null;
        $others = 0;
        foreach ($paths as $p) {
            if (mb_strtolower($p) === 'inbox') {
                $inbox = $p;
            } elseif (mb_stripos($p, 'inbox'.$delimiter) === 0) {
                $others++;
            } else {
                return '';
            }
        }

        return $inbox !== null && $others > 0 ? $inbox : '';
    }

    /** E-mails of the ticket that came from the mail server: customer messages with a Message-ID. */
    protected static function threads(Conversation $conversation)
    {
        return Thread::where('conversation_id', $conversation->id)
            ->where('type', Thread::TYPE_CUSTOMER)
            ->whereNotNull('message_id')->where('message_id', '!=', '')
            ->orderBy('id')->get(['id', 'conversation_id', 'message_id']);
    }

    /** Current folder of each e-mail of the ticket (UTF-8 path, '' when unknown): [thread id => path]. */
    public static function currentFolders(Conversation $conversation)
    {
        $threads = self::threads($conversation);
        $known = MailTags::forThreads($threads->pluck('id')->all());
        $out = [];
        foreach ($threads as $t) {
            $out[(int) $t->id] = $known[(int) $t->id]['folder'] ?? '';
        }

        return $out;
    }

    /**
     * Moves the e-mails of the ticket to $target (UTF-8 path of an existing folder of its mailbox).
     * $only = [thread id => from folder] to move back given e-mails (undo); otherwise every e-mail of the ticket.
     *
     * @return array ['moved' => n, 'not_found' => n, 'previous' => [thread id => folder], 'target' => path, 'label' => label]
     * @throws \RuntimeException not an IMAP mailbox, unknown folder, server error
     */
    public static function move(Conversation $conversation, $target, ?array $only = null)
    {
        $mailbox = $conversation->mailbox;
        if (!self::supported($mailbox)) {
            throw new \RuntimeException(__('refreshglobal::messages.move_not_imap'));
        }
        $wanted = self::folderOf($mailbox, $target);
        if (!$wanted) {
            throw new \RuntimeException(__('refreshglobal::messages.move_unknown_folder'));
        }
        $threads = self::threads($conversation)->keyBy('id');
        $current = self::currentFolders($conversation);
        $todo = [];
        foreach ($threads as $id => $t) {
            if ($only !== null && !array_key_exists($id, $only)) {
                continue;
            }
            $todo[$id] = $t;
        }
        $result = ['moved' => 0, 'not_found' => 0, 'previous' => [], 'target' => $wanted['path'], 'label' => $wanted['label']];
        if (!$todo) {
            return $result;
        }

        $client = self::client($mailbox);
        try {
            $to = \MailHelper::getImapFolder($client, $wanted['path']);
            if (!$to) {
                throw new \RuntimeException(__('refreshglobal::messages.move_unknown_folder'));
            }
            $opened = [];
            $open = function ($path) use ($client, &$opened) {
                if (!array_key_exists($path, $opened)) {
                    try {
                        $opened[$path] = \MailHelper::getImapFolder($client, $path);
                    } catch (\Throwable $e) {
                        $opened[$path] = null;
                    }
                }

                return $opened[$path];
            };
            $fetchFolders = [];
            foreach ((array) $mailbox->getInImapFolders() as $name) {
                $fetchFolders[] = MailTags::decode($name);
            }
            foreach ($todo as $id => $thread) {
                $from = $current[$id] ?? '';
                if ($from !== '' && mb_strtolower($from) === mb_strtolower($wanted['path'])) {
                    continue; // already there
                }
                // where to look: the folder recorded at fetch time, then the fetch folders of the mailbox
                $candidates = array_values(array_unique(array_filter(array_merge([$from], $fetchFolders), function ($p) use ($wanted) {
                    return $p !== '' && mb_strtolower($p) !== mb_strtolower($wanted['path']);
                })));
                $found = false;
                foreach ($candidates as $path) {
                    $folder = $open($path);
                    if (!$folder) {
                        continue;
                    }
                    $ids = [$thread->message_id, '<'.trim($thread->message_id, '<>').'>'];
                    foreach (array_unique($ids) as $mid) {
                        foreach ($folder->query()->whereMessageId($mid)->leaveUnread()->get() as $message) {
                            $message->move($to->path);
                            $found = true;
                        }
                        if ($found) {
                            break;
                        }
                    }
                    if ($found) {
                        $result['previous'][$id] = $path;
                        self::retag($conversation, $thread, $path, $wanted['path']);
                        $result['moved']++;
                        break;
                    }
                }
                if (!$found) {
                    $result['not_found']++;
                }
            }
        } finally {
            self::disconnect($client);
        }

        return $result;
    }

    /** Undo: every e-mail back to the folder it was moved from ($previous = [thread id => folder], checked here). */
    public static function undo(Conversation $conversation, array $previous)
    {
        $mailbox = $conversation->mailbox;
        $known = [];
        foreach (self::folders($mailbox) as $f) {
            $known[mb_strtolower($f['path'])] = $f['path'];
        }
        $threads = self::threads($conversation)->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $byFolder = [];
        foreach ($previous as $thread_id => $folder) {
            $folder = (string) $folder;
            if (in_array((int) $thread_id, $threads, true) && isset($known[mb_strtolower($folder)])) {
                $byFolder[$known[mb_strtolower($folder)]][(int) $thread_id] = $folder;
            }
        }
        $done = ['moved' => 0, 'not_found' => 0];
        foreach ($byFolder as $folder => $only) {
            $r = self::move($conversation, $folder, $only);
            $done['moved'] += $r['moved'];
            $done['not_found'] += $r['not_found'];
        }

        return $done;
    }

    /** Folder of the mailbox matching a UTF-8 path (case-insensitive), or null. */
    public static function folderOf(Mailbox $mailbox, $path)
    {
        $path = mb_strtolower(trim((string) $path));
        foreach ([false, true] as $fresh) {
            foreach (self::folders($mailbox, $fresh) as $f) {
                if (mb_strtolower($f['path']) === $path) {
                    return $f;
                }
            }
        }

        return null;
    }

    /**
     * Origin tags of a moved e-mail: the tag of the old folder replaced by the tag of the new one, the folder recorded;
     * other tags (classified_spam…) kept. Then refreshglobal.mail_moved.
     */
    protected static function retag(Conversation $conversation, $thread, $from, $to)
    {
        if (MailTags::tableExists()) {
            $old = $from !== '' ? MailTags::tagForFolder($from) : null;
            $new = MailTags::tagForFolder($to);
            if ($old && $old !== $new) {
                \DB::table(MailTags::TABLE)->where('thread_id', $thread->id)->where('tag', $old)->delete();
            }
            \DB::table(MailTags::TABLE)->where('thread_id', $thread->id)->update(['folder' => mb_substr($to, 0, 191)]);
            if ($new && MailTags::enabled()) {
                MailTags::attach($conversation, $thread, [$new], $to);
            }
        }
        try {
            \Eventy::action('refreshglobal.mail_moved', (int) $conversation->id, (int) $thread->id, (string) $from, (string) $to);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [mail-move] listener: '.$e->getMessage());
        }
    }

    protected static function disconnect($client)
    {
        try {
            $client->disconnect();
        } catch (\Throwable $e) {
            // already closed
        }
    }
}
