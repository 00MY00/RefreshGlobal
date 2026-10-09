<?php

namespace Modules\RefreshGlobal\Tests\Support;

use App\Conversation;
use App\Customer;
use App\Folder;
use App\Mailbox;
use App\User;

/**
 * Test data built with FreeScout's own models (mailbox folders are created by FreeScout's MailboxObserver).
 * Every e-mail address uses the reserved ".test" domain.
 */
class Fixtures
{
    public static function uid()
    {
        return substr(md5(uniqid('', true)), 0, 8);
    }

    public static function mailbox($name, $archived = false)
    {
        $mailbox = new Mailbox();
        $mailbox->name = mb_substr($name, 0, 40);
        $mailbox->email = 'mb-'.self::uid().'@example.test';
        $mailbox->save();
        if ($archived) {
            $mailbox->state = Mailbox::STATE_ARCHIVED;
            $mailbox->save();
        }

        return $mailbox;
    }

    public static function user($first_name, array $mailboxes = [], $admin = false, $only_assigned = false)
    {
        $user = new User();
        $user->first_name = $first_name;
        $user->last_name = 'Test';
        $user->email = strtolower($first_name).'-'.self::uid().'@example.test';
        $user->password = bcrypt('test-'.self::uid());
        $user->role = $admin ? User::ROLE_ADMIN : User::ROLE_USER;
        $user->save();
        $ids = [];
        foreach ($mailboxes as $mailbox) {
            $ids[] = $mailbox->id;
        }
        $user->mailboxes()->sync($ids);
        if ($only_assigned) {
            $user->permissions = [User::PERM_ONLY_ASSIGNED_TICKETS => true];
            $user->save();
        }

        return $user->fresh();
    }

    public static function customer($first_name, $last_name)
    {
        $customer = new Customer();
        $customer->first_name = $first_name;
        $customer->last_name = $last_name;
        $customer->save();

        return $customer;
    }

    public static function conversation(Mailbox $mailbox, $subject, array $attributes = [])
    {
        $customer = $attributes['customer'] ?? null;
        unset($attributes['customer']);

        $conversation = new Conversation();
        $conversation->type = Conversation::TYPE_EMAIL;
        $conversation->folder_id = (int) Folder::where('mailbox_id', $mailbox->id)->where('type', Folder::TYPE_UNASSIGNED)->value('id');
        $conversation->mailbox_id = $mailbox->id;
        $conversation->status = Conversation::STATUS_ACTIVE;
        $conversation->state = Conversation::STATE_PUBLISHED;
        $conversation->subject = $subject;
        $conversation->customer_email = 'customer-'.self::uid().'@example.test';
        $conversation->customer_id = $customer ? $customer->id : null;
        $conversation->preview = 'Preview of '.$subject;
        $conversation->threads_count = 1;
        $conversation->source_via = Conversation::PERSON_CUSTOMER;
        $conversation->source_type = Conversation::SOURCE_TYPE_EMAIL;
        $conversation->last_reply_at = date('Y-m-d H:i:s');
        $conversation->last_reply_from = Conversation::PERSON_CUSTOMER;
        foreach ($attributes as $key => $value) {
            $conversation->$key = $value;
        }
        $conversation->save();

        return $conversation;
    }

    /**
     * Data set used by the tests and the demo:
     *   support, sales, billing, archived mailboxes; bob (support + sales), carol (billing, only assigned tickets),
     *   dave (support + archived mailbox), an admin.
     */
    public static function scenario($prefix = 'RG')
    {
        $s = [];
        $s['support'] = self::mailbox($prefix.' Support');
        $s['sales'] = self::mailbox($prefix.' Sales');
        $s['billing'] = self::mailbox($prefix.' Billing');
        $s['archived'] = self::mailbox($prefix.' Old', true);

        $s['admin'] = self::user('Ada', [], true);
        $s['bob'] = self::user('Bob', [$s['support'], $s['sales']]);
        $s['carol'] = self::user('Carol', [$s['billing']], false, true);
        $s['dave'] = self::user('Dave', [$s['support'], $s['archived']]);

        $acme = self::customer('Alice', 'Martin');
        $s['t_support_open'] = self::conversation($s['support'], $prefix.'-SUPPORT-OPEN printer broken', ['customer' => $acme]);
        $s['t_support_pending'] = self::conversation($s['support'], $prefix.'-SUPPORT-PENDING waiting', ['status' => Conversation::STATUS_PENDING, 'user_id' => $s['bob']->id]);
        $s['t_support_closed'] = self::conversation($s['support'], $prefix.'-SUPPORT-CLOSED done', ['status' => Conversation::STATUS_CLOSED, 'closed_at' => date('Y-m-d H:i:s')]);
        $s['t_support_spam'] = self::conversation($s['support'], $prefix.'-SUPPORT-SPAM offer', ['status' => Conversation::STATUS_SPAM]);
        $s['t_sales_open'] = self::conversation($s['sales'], $prefix.'-SALES-OPEN quote request');
        $s['t_sales_draft'] = self::conversation($s['sales'], $prefix.'-SALES-DRAFT not sent', ['state' => Conversation::STATE_DRAFT]);
        $s['t_billing_carol'] = self::conversation($s['billing'], $prefix.'-BILLING-CAROL invoice', ['user_id' => $s['carol']->id]);
        $s['t_billing_other'] = self::conversation($s['billing'], $prefix.'-BILLING-OTHER refund');
        $s['t_billing_deleted'] = self::conversation($s['billing'], $prefix.'-BILLING-DELETED old', ['state' => Conversation::STATE_DELETED]);
        $s['t_archived'] = self::conversation($s['archived'], $prefix.'-ARCHIVED legacy');
        $s['t_formula'] = self::conversation($s['sales'], '=HYPERLINK("http://example.test","x") '.$prefix.'-FORMULA');

        return $s;
    }
}
