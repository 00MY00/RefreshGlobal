<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Text a reply starts with (1.24.0): "Hello $first_name," put in FreeScout's reply editor when it opens empty
 * (Public/js/shell.js). One text for every mailbox, and another one per mailbox; plain text with variables.
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
    const MAX = 2000;
    /** text of a mailbox that wants nothing */
    const NONE = '-';

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

    public static function text()
    {
        return self::clean(Settings::option(self::TEXT, ''));
    }

    /** @return array mailbox id => text (only the mailboxes that have their own) */
    public static function mailboxTexts()
    {
        $out = [];
        $stored = Settings::option(self::MAILBOXES, []);
        foreach (is_array($stored) ? $stored : [] as $id => $text) {
            $text = self::clean($text);
            if ((int) $id > 0 && $text !== '') {
                $out[(int) $id] = $text;
            }
        }

        return $out;
    }

    /** Text of a mailbox: its own, else the common one; '' for none. */
    public static function textFor($mailbox_id)
    {
        $own = self::mailboxTexts()[(int) $mailbox_id] ?? '';
        if ($own === self::NONE) {
            return '';
        }

        return $own !== '' ? $own : self::text();
    }

    protected static function clean($text)
    {
        return is_scalar($text) ? mb_substr(trim(str_replace("\r\n", "\n", (string) $text)), 0, self::MAX) : '';
    }

    /** Values of the variables for a ticket and the agent who answers. */
    public static function values($conversation, $user)
    {
        $customer = $conversation->customer;
        $mailbox = $conversation->mailbox;
        $name = function ($v) {
            return trim((string) $v);
        };

        return [
            'first_name'       => $customer ? $name($customer->first_name) : '',
            'last_name'        => $customer ? $name($customer->last_name) : '',
            'full_name'        => $customer ? trim($name($customer->first_name).' '.$name($customer->last_name)) : '',
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

    /** What the editor of this ticket starts with, for this agent ('' for nothing: off, chat, no text). */
    public static function forTicket($conversation, $user)
    {
        if (!self::enabled() || !$conversation || !$user || $conversation->isChat()) {
            return '';
        }
        $text = self::textFor($conversation->mailbox_id);

        return $text === '' ? '' : self::html(self::fill($text, self::values($conversation, $user)));
    }
}
