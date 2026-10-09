<?php

namespace Modules\RefreshGlobal\Services;

/**
 * The only place where RefreshGlobal decides which mailboxes and tickets a user may see.
 *
 * Rules taken from FreeScout itself (never re-implemented differently):
 * - mailboxes: User::mailboxesCanView() (app/User.php:251): every mailbox for an admin, the user's own mailboxes
 *   minus the archived ones for a regular user — same rule as ConversationPolicy::view() (app/Policies/ConversationPolicy.php:22).
 * - "User can see only assigned conversations" permission: User::canSeeOnlyAssignedConversations() (app/User.php:1353).
 *   The list is then limited to conversations assigned to the user, like FreeScout's own search
 *   (ConversationsController::search, app/Http/Controllers/ConversationsController.php:2989) and Refresh's views.
 *
 * A mailbox id coming from a request is never trusted: it is intersected with allowedIds().
 */
class MailboxAccess
{
    /** @var \App\User */
    protected $user;

    /** @var \Illuminate\Support\Collection|null */
    protected $mailboxes;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function user()
    {
        return $this->user;
    }

    /** Mailboxes the user can view, sorted by name (Collection of App\Mailbox). */
    public function mailboxes()
    {
        if ($this->mailboxes === null) {
            $mailboxes = $this->user ? $this->user->mailboxesCanView() : collect([]);
            $this->mailboxes = collect($mailboxes)->values();
        }

        return $this->mailboxes;
    }

    /** @return int[] */
    public function allowedIds()
    {
        return $this->mailboxes()->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
    }

    public function isAllowed($mailbox_id)
    {
        return in_array((int) $mailbox_id, $this->allowedIds(), true);
    }

    /**
     * Keeps only the requested mailbox ids the user may see.
     *
     * @param array $requested raw values (strings, ints, garbage)
     * @return int[]
     */
    public function filterAllowed(array $requested)
    {
        $allowed = $this->allowedIds();
        $out = [];
        foreach ($requested as $id) {
            if (is_scalar($id) && preg_match('/^\d+$/', (string) $id) && in_array((int) $id, $allowed, true)) {
                $out[(int) $id] = (int) $id;
            }
        }

        return array_values($out);
    }

    /** True when the user may only see the conversations assigned to them. Never true for an admin. */
    public function onlyAssigned()
    {
        return $this->user && !$this->user->isAdmin() && $this->user->canSeeOnlyAssignedConversations();
    }

    /** Users that can be chosen in the "Assigned to" filter: the users of the allowed mailboxes. */
    public function assignableUsers()
    {
        if (!$this->user) {
            return collect([]);
        }
        if ($this->onlyAssigned()) {
            return collect([$this->user]);
        }
        try {
            // app/User.php:1189 — FreeScout's own list for its search filters
            if (method_exists($this->user, 'whichUsersCanView')) {
                return collect($this->user->whichUsersCanView($this->mailboxes()));
            }
        } catch (\Exception $e) {
            \Log::warning('[RefreshGlobal] whichUsersCanView failed: '.$e->getMessage());
        }

        return collect([$this->user]);
    }

    /** Native address of a mailbox (Refresh sends it to its own view through the "mailbox.url" filter). */
    public static function mailboxUrl($mailbox)
    {
        $url = route('mailboxes.view', ['id' => $mailbox->id]);
        try {
            // resources/views/layouts/app.blade.php:77,85 — same filter as FreeScout's mailbox menu
            $url = \Eventy::filter('mailbox.url', $url, $mailbox);
        } catch (\Exception $e) {
            // native address
        }

        return $url;
    }
}
