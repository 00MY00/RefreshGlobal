<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Module settings stored in FreeScout's options table (App\Option, app/Option.php:35,75).
 * Read without Option's in-memory cache: Option::set() does not refresh it.
 */
class Settings
{
    /** Hide Refresh's "Tickets" entry (left bar, phone tab bar) and put "All mailboxes" in its place. */
    const REPLACE_REFRESH_TICKETS = 'refreshglobal.replace_refresh_tickets';

    public static function replaceRefreshTickets()
    {
        try {
            return (bool) \App\Option::get(self::REPLACE_REFRESH_TICKETS, (bool) config('refreshglobal.replace_refresh_tickets_default', false), true, false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setReplaceRefreshTickets($on)
    {
        \App\Option::set(self::REPLACE_REFRESH_TICKETS, $on ? 1 : 0);
    }
}
