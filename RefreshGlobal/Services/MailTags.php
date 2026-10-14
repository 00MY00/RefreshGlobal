<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Origin tags of the e-mails fetched by FreeScout (app/Console/Commands/FetchEmails.php), always in English:
 *  - folder of the mail server the e-mail was fetched from: "inbox", "spam", "trash", "sent", "drafts", "archive",
 *    or "folder:<name>" for any other folder ("[WP] Invoices" -> "folder:wp-invoices"); names of the standard folders
 *    in several languages (STANDARD), plus the administrator's own ("Folder name = tag" in the settings);
 *  - "classified_spam": the mail server marked it as spam (X-Spam-Flag / X-Spam-Status / X-Spam headers, Exchange
 *    SCL >= 5), or it came from a spam folder — only when spam is imported: by default (setting "skip spam") such an
 *    e-mail is NOT turned into a ticket and stays on the mail server (FreeScout marks it read, as any e-mail it
 *    processed: FetchEmails.php:1051-1057).
 * One row per e-mail and tag in refreshglobal_mail_tags; a ticket shows the tags of all its e-mails. Copied into the
 * official Tags module when it is installed (\Modules\Tags\Entities\Tag::attachByName(), as Refresh does:
 * Modules/Refresh/Http/Controllers/NewTicketController.php:192), so Workflows rules and FreeScout's search can use them.
 * Other modules: filters "refreshglobal.mail_tags" / "refreshglobal.thread_mail_tags", action "refreshglobal.mail_tagged"
 * (docs/api-tags.md).
 */
class MailTags
{
    const TABLE = 'refreshglobal_mail_tags';
    const SPAM = 'classified_spam';
    const OPTION_RECALC = 'refreshglobal.mail_tags_recalc';
    /** messages read per page while recalculating, pages per folder and run */
    const PAGE = 100;
    const MAX_PAGES = 200;

    /** Standard folders: tag => names (compared without case to the whole path and to its last level). */
    const STANDARD = [
        'inbox'   => ['inbox', 'boîte de réception', 'boite de reception', 'posteingang', 'bandeja de entrada', 'posta in arrivo', 'postvak in', 'caixa de entrada'],
        'spam'    => ['spam', 'junk', 'junk e-mail', 'junk email', 'junk mail', 'bulk mail', 'courrier indésirable', 'courrier indesirable',
            'indésirables', 'indesirables', 'pourriel', 'pourriels', 'spam-verdacht', 'unerwünscht', 'junk-e-mail', 'correo no deseado',
            'posta indesiderata', 'ongewenste e-mail', 'ongewenste email', 'lixo eletrônico', 'lixo eletronico', '[gmail]/spam'],
        'trash'   => ['trash', 'bin', 'deleted', 'deleted items', 'deleted messages', 'corbeille', 'éléments supprimés', 'elements supprimes',
            'papierkorb', 'gelöschte elemente', 'gelöschte objekte', 'papelera', 'elementos eliminados', 'cestino', 'posta eliminata',
            'prullenbak', 'verwijderde items', 'lixeira', 'itens excluídos', '[gmail]/trash', '[gmail]/corbeille'],
        'sent'    => ['sent', 'sent items', 'sent mail', 'sent messages', 'envoyés', 'envoyes', 'éléments envoyés', 'elements envoyes',
            'messages envoyés', 'gesendet', 'gesendete elemente', 'gesendete objekte', 'enviados', 'elementos enviados', 'posta inviata',
            'inviati', 'verzonden', 'verzonden items', 'itens enviados', '[gmail]/sent mail', '[gmail]/messages envoyés'],
        'drafts'  => ['drafts', 'draft', 'brouillons', 'entwürfe', 'borradores', 'bozze', 'concepten', 'rascunhos', '[gmail]/drafts', '[gmail]/brouillons'],
        'archive' => ['archive', 'archives', 'archiv', 'archivo', 'archivio', 'archief', 'arquivo', 'all mail', '[gmail]/all mail', 'tous les messages', '[gmail]/tous les messages'],
    ];

    /** Folder being fetched (set for each message, FetchEmails.php:380) and tags waiting for its saved thread. */
    public static $folder = null;
    public static $pending = null;

    protected static $tableExists = null;

    public static function tableExists()
    {
        if (self::$tableExists === null) {
            try {
                self::$tableExists = \Schema::hasTable(self::TABLE);
            } catch (\Throwable $e) {
                self::$tableExists = false;
            }
        }

        return self::$tableExists;
    }

    public static function enabled()
    {
        return Settings::mailTags() && self::tableExists();
    }

    /** Folder path as text: decoded only when it is in IMAP's modified UTF-7 (plain ASCII with "&...-" sequences). */
    public static function decode($path)
    {
        $path = (string) $path;
        if (strpos($path, '&') === false || preg_match('/[^ -~]/', $path) || !function_exists('mb_convert_encoding')) {
            return $path;
        }
        $decoded = @mb_convert_encoding($path, 'UTF-8', 'UTF7-IMAP');

        return is_string($decoded) && $decoded !== '' ? $decoded : $path;
    }

    /** "Folder name = tag" lines of the settings: [lowercased name => tag]. */
    public static function customFolders()
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', Settings::mailTagsFolders()) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            list($name, $tag) = array_map('trim', explode('=', $line, 2));
            $tag = self::slug($tag, true);
            if ($name !== '' && $tag !== '') {
                $out[mb_strtolower($name)] = $tag;
            }
        }

        return $out;
    }

    /** Tag of a folder of the mail server (UTF-8 or UTF7-IMAP path), null for an empty path. */
    public static function tagForFolder($path)
    {
        $path = trim(self::decode($path));
        if ($path === '') {
            return null;
        }
        $full = mb_strtolower($path);
        $parts = preg_split('#[./\\\\]#u', $path);
        $last = mb_strtolower(trim((string) end($parts)));
        $candidates = array_unique([$full, $last]);
        foreach (self::customFolders() as $name => $tag) {
            if (in_array($name, $candidates, true)) {
                return $tag;
            }
        }
        if ($full === 'inbox') {
            return 'inbox';
        }
        foreach (self::STANDARD as $tag => $names) {
            foreach ($candidates as $c) {
                if (in_array($c, $names, true)) {
                    return $tag;
                }
            }
        }
        // any other folder: its path without the INBOX level
        $name = preg_replace('#^inbox[./\\\\]#i', '', $path);

        return 'folder:'.self::slug($name);
    }

    /** "[WP] Invoices / Suppliers" -> "wp-invoices-suppliers" (tags: lowercase ASCII, digits, "-", "_", ":"). */
    public static function slug($text, $keepColon = false)
    {
        $text = (string) $text;
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($ascii !== false) {
                $text = $ascii;
            }
        }
        $text = strtolower($text);
        $text = preg_replace($keepColon ? '/[^a-z0-9_:]+/' : '/[^a-z0-9_]+/', '-', $text);

        return substr(trim($text, '-'), 0, 80);
    }

    /** Raw headers of an e-mail marked as spam by the mail server. */
    public static function isSpam($rawHeaders)
    {
        $h = (string) $rawHeaders;
        if ($h === '') {
            return false;
        }

        return (bool) (preg_match('/^X-Spam-Flag:\s*YES\b/im', $h)
            || preg_match('/^X-Spam-Status:\s*Yes\b/im', $h)
            || preg_match('/^X-Spam:\s*(yes|true|1)\b/im', $h)
            || preg_match('/^X-MS-Exchange-Organization-SCL:\s*([5-9])\b/im', $h));
    }

    /* ---------------------------------------------------------------- fetching */

    /** Filter "fetch_emails.mailbox_to_save_message" (FetchEmails.php:380): the folder of the message about to be read. */
    public static function onFolder($mailbox, $folder)
    {
        self::$folder = is_object($folder) && isset($folder->path) ? (string) $folder->path : null;
        self::$pending = null;

        return $mailbox;
    }

    /**
     * Filter "fetch_emails.should_save_thread" (FetchEmails.php:1051, 1091): a spam is left on the mail server when
     * "skip spam" is on; otherwise the tags of the message wait for its thread (onThreadSaved()).
     */
    public static function shouldSave($result, $data)
    {
        if ($result === false || !Settings::mailTags()) {
            return $result;
        }
        try {
            $raw = '';
            if (!empty($data['message']) && is_object($data['message'])) {
                $header = $data['message']->getHeader();
                $raw = is_object($header) && isset($header->raw) ? (string) $header->raw : '';
            }
            $tags = [];
            $folderTag = self::tagForFolder(self::$folder);
            if ($folderTag) {
                $tags[] = $folderTag;
            }
            if (self::isSpam($raw) || $folderTag === 'spam') {
                if (Settings::skipSpam()) {
                    \Log::info('[RefreshGlobal] [mail-tags] spam left on the mail server: '.(string) ($data['message_id'] ?? '')
                        .' ('.self::decode(self::$folder).')');
                    self::$pending = null;

                    return false;
                }
                $tags[] = self::SPAM;
            }
            self::$pending = ['message_id' => self::normalizeId($data['message_id'] ?? ''), 'tags' => array_values(array_unique($tags)),
                'folder' => self::decode(self::$folder)];
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [mail-tags] '.$e->getMessage());
        }

        return $result;
    }

    /** Actions "conversation.created_by_customer" / "conversation.customer_replied" (FetchEmails.php:1384, 1387). */
    public static function onThreadSaved($conversation, $thread)
    {
        $pending = self::$pending;
        self::$pending = null;
        if (!$pending || !$pending['tags'] || !$conversation || !$thread || !self::enabled()) {
            return;
        }
        if ($pending['message_id'] !== '' && self::normalizeId($thread->message_id) !== $pending['message_id']) {
            return;
        }
        self::attach($conversation, $thread, $pending['tags'], $pending['folder']);
    }

    public static function normalizeId($id)
    {
        return trim((string) $id, " <>\t\r\n");
    }

    /** Stores the tags of an e-mail (new ones only), copies them into the Tags module, tells the other modules. */
    public static function attach($conversation, $thread, array $tags, $folder = '')
    {
        $new = [];
        $now = date('Y-m-d H:i:s');
        foreach (array_unique($tags) as $tag) {
            $tag = mb_substr((string) $tag, 0, 100);
            if ($tag === '' || \DB::table(self::TABLE)->where('thread_id', $thread->id)->where('tag', $tag)->exists()) {
                continue;
            }
            \DB::table(self::TABLE)->insert(['conversation_id' => $conversation->id, 'thread_id' => $thread->id,
                'mailbox_id' => $conversation->mailbox_id, 'tag' => $tag, 'folder' => mb_substr((string) $folder, 0, 191), 'created_at' => $now]);
            $new[] = $tag;
        }
        if (!$new) {
            return [];
        }
        self::mirror($conversation->id, $new);
        try {
            \Eventy::action('refreshglobal.mail_tagged', (int) $conversation->id, (int) $thread->id, $new);
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [mail-tags] listener: '.$e->getMessage());
        }

        return $new;
    }

    /** Official Tags module installed: the same tags on the ticket (Workflows rules, FreeScout's search). */
    public static function mirror($conversationId, array $tags)
    {
        if (!self::tagsModule()) {
            return;
        }
        foreach ($tags as $tag) {
            try {
                \Modules\Tags\Entities\Tag::attachByName($tag, $conversationId);
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [mail-tags] Tags module: '.$e->getMessage());
            }
        }
    }

    public static function tagsModule()
    {
        try {
            return \App\Module::isActive('tags') && class_exists('\Modules\Tags\Entities\Tag') && method_exists('\Modules\Tags\Entities\Tag', 'attachByName');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* ---------------------------------------------------------------- reading */

    /** @return array [conversation id => [tag, …]] (tags of all its e-mails, sorted) */
    public static function forConversations(array $ids)
    {
        $out = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !self::tableExists()) {
            return $out;
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (\DB::table(self::TABLE)->whereIn('conversation_id', $chunk)->distinct()->get(['conversation_id', 'tag']) as $r) {
                $out[(int) $r->conversation_id][] = (string) $r->tag;
            }
        }
        foreach ($out as &$tags) {
            $tags = array_values(array_unique($tags));
            sort($tags);
        }

        return $out;
    }

    /** @return array [thread id => ['tags' => [...], 'folder' => folder of the server]] */
    public static function forThreads(array $ids)
    {
        $out = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !self::tableExists()) {
            return $out;
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (\DB::table(self::TABLE)->whereIn('thread_id', $chunk)->orderBy('tag')->get(['thread_id', 'tag', 'folder']) as $r) {
                $id = (int) $r->thread_id;
                if (!isset($out[$id])) {
                    $out[$id] = ['tags' => [], 'folder' => ''];
                }
                $out[$id]['tags'][] = (string) $r->tag;
                if ($r->folder !== '') {
                    $out[$id]['folder'] = (string) $r->folder;
                }
            }
        }

        return $out;
    }

    /** List filter: tickets with at least one e-mail tagged $tag. */
    public static function applyFilter($query, $tag)
    {
        $query->whereIn('conversations.id', function ($s) use ($tag) {
            $s->select('conversation_id')->from(self::TABLE)->where('tag', $tag);
        });
    }

    /** [tag => number of tickets of the list] (one grouped query). */
    public static function counts($base)
    {
        $out = [];
        $rows = $base->toBase()->join(self::TABLE.' as rgmt', 'rgmt.conversation_id', '=', 'conversations.id')
            ->select('rgmt.tag as k', \DB::raw('COUNT(DISTINCT conversations.id) as n'))->groupBy('rgmt.tag')->orderBy('rgmt.tag')->get();
        foreach ($rows as $r) {
            $out[(string) $r->k] = (int) $r->n;
        }

        return $out;
    }

    /** Tickets deleted for good (conversations.before_delete_forever, late priority): their rows go with them. */
    public static function forget($ids)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if (!$ids || !self::tableExists()) {
            return;
        }
        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                \DB::table(self::TABLE)->whereIn('conversation_id', $chunk)->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [mail-tags] '.$e->getMessage());
        }
    }

    /* ---------------------------------------------------------------- recalculation (e-mails already in FreeScout) */

    public static function requestRecalc()
    {
        self::saveRecalc(['state' => 'requested', 'requested_at' => date('c')]);
    }

    /** ['state' => '' | requested | running | done | failed, 'requested_at', 'finished_at', 'tagged', 'seen', 'error'] */
    public static function recalcStatus()
    {
        try {
            // arrays are serialized by Option::set() and given back by Option::get() (app/Option.php, maybeSerialize)
            $s = \App\Option::get(self::OPTION_RECALC, [], true, false);
            if (is_string($s)) {
                $s = json_decode($s, true);
            }
        } catch (\Throwable $e) {
            $s = null;
        }

        return is_array($s) ? $s + ['state' => ''] : ['state' => ''];
    }

    protected static function saveRecalc(array $status)
    {
        \App\Option::set(self::OPTION_RECALC, $status);
    }

    /**
     * Goes through every folder of every IMAP mailbox, matches each e-mail to its thread by Message-ID and adds the
     * missing tags (folder, and "classified_spam" from the headers). Only the e-mails still on the mail server can be
     * tagged. $clientFactory (tests): function (Mailbox) returns an IMAP client.
     *
     * @return array ['tagged' => new tags, 'seen' => e-mails read, 'errors' => [...]]
     */
    public static function recalc(callable $out = null, callable $clientFactory = null)
    {
        $out = $out ?: function () {
        };
        $clientFactory = $clientFactory ?: function ($mailbox) {
            return \MailHelper::getMailboxClient($mailbox);
        };
        $result = ['tagged' => 0, 'seen' => 0, 'errors' => []];
        if (!self::tableExists()) {
            $result['errors'][] = 'table '.self::TABLE.' missing';

            return $result;
        }
        $status = ['state' => 'running', 'started_at' => date('c')] + self::recalcStatus();
        self::saveRecalc($status);
        $mailboxes = \App\Mailbox::where('in_protocol', \App\Mailbox::IN_PROTOCOL_IMAP)->whereNotNull('in_server')->where('in_server', '!=', '')->get();
        foreach ($mailboxes as $mailbox) {
            try {
                $client = $clientFactory($mailbox);
                $client->connect();
                foreach ($client->getFolders(false) as $folder) {
                    if (!empty($folder->no_select)) {
                        continue;
                    }
                    $tag = self::tagForFolder($folder->path);
                    $found = [];
                    for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                        $messages = $folder->query()->all()->setFetchBody(false)->leaveUnread()->limit(self::PAGE, $page)->get();
                        $n = 0;
                        foreach ($messages as $message) {
                            $n++;
                            $id = self::normalizeId((string) $message->getMessageId());
                            if ($id === '') {
                                continue;
                            }
                            $header = $message->getHeader();
                            $found[$id] = self::isSpam(is_object($header) && isset($header->raw) ? $header->raw : '');
                        }
                        $result['seen'] += $n;
                        if ($n < self::PAGE) {
                            break;
                        }
                    }
                    $result['tagged'] += self::tagFound($mailbox, $found, $tag, self::decode($folder->path));
                }
                try {
                    $client->disconnect();
                } catch (\Throwable $e) {
                    // already closed
                }
                $out('Mailbox '.$mailbox->name.': done.');
            } catch (\Throwable $e) {
                $result['errors'][] = $mailbox->name.': '.$e->getMessage();
                $out('Mailbox '.$mailbox->name.': '.$e->getMessage());
            }
        }
        self::saveRecalc(['state' => $result['errors'] ? 'failed' : 'done', 'finished_at' => date('c'), 'tagged' => $result['tagged'],
            'seen' => $result['seen'], 'error' => implode(' | ', array_slice($result['errors'], 0, 3))] + $status);

        return $result;
    }

    /** E-mails of a folder [Message-ID => spam?] -> tags of their threads in this mailbox. */
    protected static function tagFound($mailbox, array $found, $tag, $folder)
    {
        $tagged = 0;
        foreach (array_chunk(array_keys($found), 300) as $chunk) {
            $values = [];
            foreach ($chunk as $id) {
                $values[] = $id;
                $values[] = '<'.$id.'>';
            }
            $threads = \App\Thread::join('conversations', 'conversations.id', '=', 'threads.conversation_id')
                ->where('conversations.mailbox_id', $mailbox->id)->whereIn('threads.message_id', $values)
                ->get(['threads.id', 'threads.conversation_id', 'threads.message_id', 'conversations.mailbox_id']);
            foreach ($threads as $thread) {
                $conversation = (object) ['id' => (int) $thread->conversation_id, 'mailbox_id' => (int) $thread->mailbox_id];
                $tags = $tag ? [$tag] : [];
                if (!empty($found[self::normalizeId($thread->message_id)]) || $tag === 'spam') {
                    $tags[] = self::SPAM;
                }
                $tagged += count(self::attach($conversation, $thread, $tags, $folder));
            }
        }

        return $tagged;
    }
}
