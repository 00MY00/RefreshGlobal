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

    /** Refresh's "My dashboard" over all the user's mailboxes instead of the first one only. */
    const GLOBAL_DASHBOARD = 'refreshglobal.global_dashboard';

    public static function globalDashboard()
    {
        try {
            return (bool) \App\Option::get(self::GLOBAL_DASHBOARD, (bool) config('refreshglobal.global_dashboard_default', true), true, false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setGlobalDashboard($on)
    {
        \App\Option::set(self::GLOBAL_DASHBOARD, $on ? 1 : 0);
    }

    /** After deleting a ticket: next ticket of the "All mailboxes" list (on) or back to that list (off, default). */
    const DELETE_GOES_NEXT = 'refreshglobal.delete_goes_next';

    public static function deleteGoesNext()
    {
        try {
            return (bool) \App\Option::get(self::DELETE_GOES_NEXT, config('refreshglobal.after_delete_default', 'list') === 'next', true, false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setDeleteGoesNext($on)
    {
        \App\Option::set(self::DELETE_GOES_NEXT, $on ? 1 : 0);
    }

    /** After a deletion, back on the "All mailboxes" list at the same place (no scrolling back down). On by default. */
    const KEEP_POSITION = 'refreshglobal.keep_position';

    public static function keepPosition()
    {
        try {
            return (bool) \App\Option::get(self::KEEP_POSITION, (bool) config('refreshglobal.keep_position_default', true), true, false);
        } catch (\Exception $e) {
            return true;
        }
    }

    public static function setKeepPosition($on)
    {
        \App\Option::set(self::KEEP_POSITION, $on ? 1 : 0);
    }

    /** Mailbox badge above each ticket (name and address) on the "All mailboxes" list and the dashboard. */
    const SHOW_MAILBOX = 'refreshglobal.show_mailbox';

    public static function showMailbox()
    {
        try {
            return (bool) \App\Option::get(self::SHOW_MAILBOX, (bool) config('refreshglobal.show_mailbox_default', true), true, false);
        } catch (\Exception $e) {
            return true;
        }
    }

    public static function setShowMailbox($on)
    {
        \App\Option::set(self::SHOW_MAILBOX, $on ? 1 : 0);
    }

    /** Automatic emptying of the trash: tickets in the trash for more than this number of days (0 = never). */
    const TRASH_AUTO_DAYS = 'refreshglobal.trash_auto_days';
    const TRASH_AUTO_DAYS_MAX = 3650;

    public static function trashAutoDays()
    {
        try {
            $days = (int) \App\Option::get(self::TRASH_AUTO_DAYS, (int) config('refreshglobal.trash_auto_days_default', 0), true, false);
        } catch (\Exception $e) {
            $days = 0;
        }

        return max(0, min(self::TRASH_AUTO_DAYS_MAX, $days));
    }

    public static function setTrashAutoDays($days)
    {
        \App\Option::set(self::TRASH_AUTO_DAYS, max(0, min(self::TRASH_AUTO_DAYS_MAX, (int) $days)));
    }

    /** Ticket deleted for good: its e-mails are also moved to the mail server's trash folder (Services/MailServerTrash). */
    const SERVER_TRASH = 'refreshglobal.server_trash';
    /** Trash folder of the mail server ('' = found automatically). */
    const SERVER_TRASH_FOLDER = 'refreshglobal.server_trash_folder';

    public static function serverTrash()
    {
        try {
            return (bool) \App\Option::get(self::SERVER_TRASH, (bool) config('refreshglobal.server_trash_default', true), true, false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setServerTrash($on)
    {
        \App\Option::set(self::SERVER_TRASH, $on ? 1 : 0);
    }

    public static function serverTrashFolder()
    {
        try {
            return mb_substr(trim((string) \App\Option::get(self::SERVER_TRASH_FOLDER, (string) config('refreshglobal.server_trash_folder_default', ''), true, false)), 0, 200);
        } catch (\Exception $e) {
            return '';
        }
    }

    /** Options shown in Manage › Settings › RefreshGlobal (saved by FreeScout itself) => current value. */
    public static function sectionValues()
    {
        return [
            self::REPLACE_REFRESH_TICKETS => self::replaceRefreshTickets(),
            self::GLOBAL_DASHBOARD        => self::globalDashboard(),
            self::SHOW_MAILBOX            => self::showMailbox(),
            self::DELETE_GOES_NEXT        => self::deleteGoesNext(),
            self::KEEP_POSITION           => self::keepPosition(),
            self::DELETE_PERMANENTLY      => self::deletePermanently(),
            self::TRASH_AUTO_DAYS         => self::trashAutoDays(),
            self::SERVER_TRASH            => self::serverTrash(),
            self::SERVER_TRASH_FOLDER     => self::serverTrashFolder(),
            Update\Updater::OPTION        => Update\Updater::enabled(),
        ];
    }

    /** Deleting a ticket removes it with its e-mails from FreeScout at once instead of putting it in the trash. */
    const DELETE_PERMANENTLY = 'refreshglobal.delete_permanently';

    public static function deletePermanently()
    {
        try {
            return (bool) \App\Option::get(self::DELETE_PERMANENTLY, (bool) config('refreshglobal.delete_permanently_default', false), true, false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setDeletePermanently($on)
    {
        \App\Option::set(self::DELETE_PERMANENTLY, $on ? 1 : 0);
    }
}
