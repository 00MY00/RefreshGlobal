<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Text a reply starts with (1.24.0): "Hello $first_name," put in FreeScout's reply editor when it opens empty
 * (Public/js/shell.js). Several texts (1.25.0), each one with a name and its mailboxes: the first one that suits the
 * mailbox and the language of the customer's message (1.26.0, Services/LanguageGuess.php) is put in, the agent may choose
 * another one in the reply. Plain text with variables.
 *
 *   $first_name            the value, or nothing: the spaces before it go too ("Hello $first_name," -> "Hello,")
 *   $first_name|customer|  the value, or "customer" when it is empty
 *
 * Unknown variables are left as they are. Everything is escaped: values come from customers.
 */
class ReplyStart
{
    const ENABLED = 'refreshglobal.reply_start';
    const TEXT = 'refreshglobal.reply_start_text';
    const MAILBOXES = 'refreshglobal.reply_start_mailboxes';
    /** 1.25.0: list of ['name', 'text', 'where', 'mailboxes', 'lang']; TEXT and MAILBOXES (1.24.0) are read until it is saved */
    const TEXTS = 'refreshglobal.reply_start_texts';
    /** name of the customer looked for in the signature too (Services/CustomerName.php), on by default */
    const DETECT = 'refreshglobal.reply_start_detect';
    const MAX = 2000;
    const MAX_NAME = 60;
    const MAX_TEXTS = 30;
    /** 1.24.0: text of a mailbox that wants nothing */
    const NONE = '-';
    /** where a text is used: every mailbox, only these, every mailbox except these */
    const ALL = 'all';
    const ONLY = 'only';
    const EXCEPT = 'except';

    const VARIABLES = ['first_name', 'last_name', 'full_name', 'email', 'company', 'subject', 'ticket_number', 'mailbox',
        'agent_first_name', 'agent_last_name', 'agent_full_name', 'date'];

    /** Values of the preview of the settings page. */
    const SAMPLE = ['first_name' => 'Jane', 'last_name' => 'Doe', 'full_name' => 'Jane Doe', 'email' => 'jane@example.test', 'company' => 'Example Ltd',
        'subject' => 'Invoice', 'ticket_number' => '1234', 'mailbox' => 'Support', 'agent_first_name' => 'Sam', 'agent_last_name' => 'Agent',
        'agent_full_name' => 'Sam Agent', 'date' => '2026-01-31'];

    public static function enabled()
    {
        try {
            return (bool) Settings::option(self::ENABLED, false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function detect()
    {
        try {
            return (bool) Settings::option(self::DETECT, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array the texts in their order: ['name' => , 'text' => , 'where' => all|only|except, 'mailboxes' => int[], 'lang' => code or '' for any] */
    public static function texts()
    {
        $stored = Settings::option(self::TEXTS, null);
        if ($stored === null) {
            return self::texts124();
        }
        $out = [];
        foreach (is_array($stored) ? $stored : [] as $row) {
            $text = is_array($row) ? self::clean($row['text'] ?? '') : '';
            if ($text === '' || count($out) >= self::MAX_TEXTS) {
                continue;
            }
            $where = $row['where'] ?? '';
            $ids = [];
            foreach (is_array($row['mailboxes'] ?? null) ? $row['mailboxes'] : [] as $id) {
                if (is_scalar($id) && (int) $id > 0) {
                    $ids[(int) $id] = (int) $id;
                }
            }
            $name = is_scalar($row['name'] ?? '') ? trim(preg_replace('/\s+/u', ' ', (string) ($row['name'] ?? ''))) : '';
            $out[] = [
                'name'      => mb_substr($name, 0, self::MAX_NAME),
                'text'      => $text,
                // anything unknown is used nowhere rather than everywhere
                'where'     => in_array($where, [self::ALL, self::EXCEPT], true) ? $where : self::ONLY,
                'mailboxes' => array_values($ids),
                'lang'      => is_string($row['lang'] ?? null) && isset(LanguageGuess::LANGUAGES[$row['lang']]) ? $row['lang'] : '',
            ];
        }

        return $out;
    }

    /** Settings of 1.24.0 (a common text, another one per mailbox, "-" for none) as a list: same result for every mailbox. */
    protected static function texts124()
    {
        $out = [];
        $none = [];
        $stored = Settings::option(self::MAILBOXES, []);
        foreach (is_array($stored) ? $stored : [] as $id => $text) {
            $text = self::clean($text);
            if ((int) $id <= 0 || $text === '') {
                continue;
            }
            if ($text === self::NONE) {
                $none[] = (int) $id;
            } else {
                $out[] = ['name' => '', 'text' => $text, 'where' => self::ONLY, 'mailboxes' => [(int) $id], 'lang' => ''];
            }
        }
        $common = self::clean(Settings::option(self::TEXT, ''));
        if ($common !== '') {
            $out[] = ['name' => '', 'text' => $common, 'where' => $none ? self::EXCEPT : self::ALL, 'mailboxes' => $none, 'lang' => ''];
        }

        return $out;
    }

    /** Texts of a mailbox: those made for it first, then the general ones, each in the order of the settings. */
    public static function textsFor($mailbox_id)
    {
        $own = [];
        $general = [];
        foreach (self::texts() as $row) {
            $in = in_array((int) $mailbox_id, $row['mailboxes'], true);
            if ($row['where'] === self::ONLY) {
                if ($in) {
                    $own[] = $row;
                }
            } elseif ($row['where'] === self::ALL || !$in) {
                $general[] = $row;
            }
        }

        return array_merge($own, $general);
    }

    protected static function clean($text)
    {
        return is_scalar($text) ? mb_substr(trim(str_replace("\r\n", "\n", (string) $text)), 0, self::MAX) : '';
    }

    /** Values of the variables for a ticket and the agent who answers. */
    public static function values($conversation, $user, array $bodies = [])
    {
        $customer = $conversation->customer;
        $mailbox = $conversation->mailbox;
        $name = function ($v) {
            return trim((string) $v);
        };

        if (self::detect()) {
            $person = CustomerName::forTicket($conversation, $bodies);
        } else {
            $person = ['first' => $customer ? $name($customer->first_name) : '', 'last' => $customer ? $name($customer->last_name) : ''];
            $person['full'] = trim($person['first'].' '.$person['last']);
        }

        return [
            'first_name'       => $person['first'],
            'last_name'        => $person['last'],
            'full_name'        => $person['full'],
            'email'            => $name($conversation->customer_email),
            'company'          => $customer ? $name($customer->company) : '',
            'subject'          => $name($conversation->subject),
            'ticket_number'    => (string) $conversation->number,
            'mailbox'          => $mailbox ? $name($mailbox->name) : '',
            'agent_first_name' => $user ? $name($user->first_name) : '',
            'agent_last_name'  => $user ? $name($user->last_name) : '',
            'agent_full_name'  => $user ? trim($name($user->first_name).' '.$name($user->last_name)) : '',
            'date'             => date('Y-m-d'),
        ];
    }

    /** Plain text with its variables replaced (same rules in Public/js/shell.js for the preview). */
    public static function fill($text, array $values)
    {
        return preg_replace_callback('/([ \t]*)\$([a-z_]+)(?:\|([^|\n]*)\|)?/', function ($m) use ($values) {
            if (!in_array($m[2], self::VARIABLES, true)) {
                return $m[0];
            }
            // one line: a value never adds lines to the text
            $value = trim(preg_replace('/\s+/u', ' ', (string) ($values[$m[2]] ?? '')));
            if ($value === '') {
                $value = trim($m[3] ?? '');
            }

            return $value === '' ? '' : $m[1].$value;
        }, (string) $text);
    }

    /** HTML for the editor: one block per line, everything escaped, then an empty line to write on. '' for nothing. */
    public static function html($text)
    {
        $text = rtrim((string) $text);
        if (trim($text) === '') {
            return '';
        }
        $html = '';
        foreach (explode("\n", $text) as $line) {
            $html .= '<div>'.(trim($line) === '' ? '<br>' : htmlspecialchars($line, ENT_QUOTES, 'UTF-8')).'</div>';
        }

        return $html.'<div><br></div>';
    }

    /**
     * In the order of textsFor(): those in the language of the message, those for any language, then the others. "auto": may
     * be put in by itself. A text in another language than the message never is; language not known: the first one is.
     */
    public static function rank(array $rows, $lang)
    {
        $groups = [[], [], []];
        foreach ($rows as $row) {
            $row['auto'] = $lang === '' || $row['lang'] === '' || $row['lang'] === $lang;
            $groups[$row['lang'] === '' ? 1 : ($row['lang'] === $lang ? 0 : 2)][] = $row;
        }

        return array_merge($groups[0], $groups[1], $groups[2]);
    }

    /**
     * The texts for the editor of this ticket and this agent, the one put in first when it is "auto" ([] for nothing: off,
     * chat, no text).
     *
     * @return array of ['name' => plain text, 'html' => escaped html, 'lang' => code or '', 'auto' => bool]
     */
    public static function forTicket($conversation, $user, &$lang = '')
    {
        $lang = '';
        if (!self::enabled() || !$conversation || !$user || $conversation->isChat()) {
            return [];
        }
        $rows = self::textsFor($conversation->mailbox_id);
        if (!$rows) {
            return [];
        }
        $bodies = CustomerName::bodies($conversation);
        try {
            $lang = LanguageGuess::find($bodies, $conversation->subject);
        } catch (\Throwable $e) {
            $lang = '';
        }
        $values = self::values($conversation, $user, $bodies);
        $out = [];
        foreach (self::rank($rows, $lang) as $row) {
            $text = self::fill($row['text'], $values);
            $html = self::html($text);
            if ($html !== '') {
                // without a name: the text itself, on one line
                $name = $row['name'] !== '' ? $row['name'] : mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, self::MAX_NAME);
                $out[] = ['name' => $name, 'html' => $html, 'lang' => $row['lang'], 'auto' => $row['auto']];
            }
        }

        return $out;
    }
}
