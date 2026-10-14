<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Address used to send a new e-mail or to create a new ticket (FreeScout's create form, conversations.create, rebuilt
 * by Refresh as "Send an e-mail" / "New ticket"). FreeScout ties that form to ONE mailbox (the one of the address) and
 * shows a "From" field only when that mailbox has aliases and "aliases_reply" is on (ConversationsController.php:477,
 * resources/views/conversations/create.blade.php:138-150). Here the user picks among ALL the addresses allowed to him:
 * the e-mail address of every mailbox he can see (archived ones excepted) and the aliases of these mailboxes
 * (Mailbox::getAliases(), app/Mailbox.php:793). An alias of the same mailbox is sent as "from_alias", which FreeScout
 * applies to the message (ConversationsController.php:1195-1197, app/Mail/ReplyToCustomer.php:82); the address of
 * another mailbox opens the form of that mailbox (Public/js/shell.js sendFrom()).
 */
class SendFrom
{
    /**
     * @return array[] [['mailbox' => id, 'name' => mailbox name, 'email' => address, 'alias' => bool, 'label' => alias name], …]
     *                 mailbox address first, then its aliases; mailboxes sorted by name
     */
    public static function addresses($user)
    {
        $out = [];
        if (!$user) {
            return $out;
        }
        $mailboxes = (new MailboxAccess($user))->mailboxes()->sortBy(function ($m) {
            return mb_strtolower((string) $m->name);
        });
        foreach ($mailboxes as $mailbox) {
            if (method_exists($mailbox, 'isArchived') && $mailbox->isArchived()) {
                continue;
            }
            foreach (self::mailboxAddresses($mailbox) as $email => $label) {
                $out[] = [
                    'mailbox' => (int) $mailbox->id,
                    'name'    => (string) $mailbox->name,
                    'email'   => $email,
                    'alias'   => mb_strtolower($email) !== mb_strtolower((string) $mailbox->email),
                    'label'   => (string) $label,
                ];
            }
        }

        return $out;
    }

    /**
     * Data of the create form (<meta name="refreshglobal-from">, read by Public/js/shell.js sendFrom()): the addresses,
     * the form of each mailbox, the texts. Null when there is nothing to choose (a single address).
     */
    public static function pageConfig($user, $mailbox_id)
    {
        $addresses = self::addresses($user);
        if (count($addresses) < 2) {
            return null;
        }
        $urls = [];
        foreach ($addresses as $a) {
            $urls[$a['mailbox']] = route('conversations.create', ['mailbox_id' => $a['mailbox']]);
        }

        return [
            'mailbox'   => (int) $mailbox_id,
            'addresses' => $addresses,
            'urls'      => $urls,
            'labels'    => [
                'from'      => __('refreshglobal::messages.send_from'),
                'mailbox'   => __('refreshglobal::messages.send_from_mailbox'),
                'help'      => __('refreshglobal::messages.send_from_help'),
                'switching' => __('refreshglobal::messages.send_from_switching'),
            ],
        ];
    }

    /** Address of the mailbox, then its aliases (whatever its "aliases_reply" option): [email => name]. */
    public static function mailboxAddresses($mailbox)
    {
        try {
            $list = $mailbox->getAliases(true, false);
        } catch (\Throwable $e) {
            $list = [];
        }
        $out = [];
        foreach ((array) $list as $email => $name) {
            $email = trim((string) $email);
            if ($email !== '') {
                $out[$email] = (string) $name;
            }
        }
        if (!$out && $mailbox->email) {
            $out[(string) $mailbox->email] = (string) $mailbox->name;
        }

        return $out;
    }

    /** Is $email the address or an alias of this mailbox? */
    public static function belongsTo($email, $mailbox)
    {
        $email = mb_strtolower(trim((string) $email));
        foreach (array_keys(self::mailboxAddresses($mailbox)) as $address) {
            if (mb_strtolower($address) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Security (conversations.ajax, Http/Middleware/CheckFromAlias.php): FreeScout applies any "from_alias" posted with
     * a message without checking it (ConversationsController.php:1195-1197), a forged request could send from any
     * address. An alias that is neither the address nor an alias of the ticket's mailbox is removed (the mailbox's own
     * address is used). Returns true when the request was changed.
     */
    public static function sanitize($request)
    {
        $alias = trim((string) $request->input('from_alias', ''));
        if ($alias === '') {
            return false;
        }
        $mailbox = null;
        if ($request->filled('conversation_id')) {
            $conversation = \App\Conversation::find((int) $request->input('conversation_id'));
            $mailbox = $conversation ? $conversation->mailbox : null;
        }
        if (!$mailbox && $request->filled('mailbox_id')) {
            $mailbox = \App\Mailbox::find((int) $request->input('mailbox_id'));
        }
        if ($mailbox && self::belongsTo($alias, $mailbox)) {
            return false;
        }
        \Log::warning('[RefreshGlobal] [send-from] alias "'.$alias.'" refused: not an address of mailbox '.($mailbox ? $mailbox->id : '?'));
        $request->merge(['from_alias' => '']);

        return true;
    }
}
