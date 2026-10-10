<?php

/*
 * RefreshGlobal settings. Each value can be overridden in FreeScout's .env file
 * (then run: php artisan freescout:clear-cache).
 */
return [
    // Tickets per page on the "All mailboxes" page (Refresh shows 30 per page).
    'per_page' => (int) env('REFRESHGLOBAL_PER_PAGE', 30),

    // Maximum number of rows written in one CSV export.
    'export_max_rows' => (int) env('REFRESHGLOBAL_EXPORT_MAX_ROWS', 5000),

    // CSV separator: ";" opens directly in Excel with a French/European locale, "," elsewhere.
    'csv_delimiter' => env('REFRESHGLOBAL_CSV_DELIMITER', ';'),

    // Minutes the compatibility report is kept in cache (it is also rebuilt when a version changes).
    'compat_cache_minutes' => (int) env('REFRESHGLOBAL_COMPAT_CACHE_MINUTES', 5),

    // Maximum number of saved views per user.
    'max_saved_views' => (int) env('REFRESHGLOBAL_MAX_SAVED_VIEWS', 50),

    // Automatic update: where the releases are (module.json, RefreshGlobal.zip, SHA256SUMS), time of the daily run
    // (application time zone) and default state before anyone turns it on or off (off: an administrator decides).
    'update_url' => env('REFRESHGLOBAL_UPDATE_URL', 'https://github.com/00MY00/RefreshGlobal/releases/latest/download'),
    // When no release is published there (404): current version of the main branch (module.json + archive of the
    // branch). No SHA256SUMS for a branch: HTTPS only. Empty values = no fallback.
    'update_branch_manifest' => env('REFRESHGLOBAL_UPDATE_BRANCH_MANIFEST', 'https://raw.githubusercontent.com/00MY00/RefreshGlobal/main/RefreshGlobal/module.json'),
    'update_branch_zip' => env('REFRESHGLOBAL_UPDATE_BRANCH_ZIP', 'https://github.com/00MY00/RefreshGlobal/archive/refs/heads/main.zip'),
    'auto_update_time' => env('REFRESHGLOBAL_AUTO_UPDATE_TIME', '03:30'),
    'auto_update_default' => (bool) env('REFRESHGLOBAL_AUTO_UPDATE', false),

    // With Refresh: hide its "Tickets" entry (left bar, phone tab bar) and keep only "All mailboxes" (default before
    // an administrator changes it on the diagnostic page).
    'replace_refresh_tickets_default' => (bool) env('REFRESHGLOBAL_REPLACE_REFRESH_TICKETS', false),

    // With Refresh: its "My dashboard" shows all the user's mailboxes (Refresh alone shows the first one only).
    'global_dashboard_default' => (bool) env('REFRESHGLOBAL_GLOBAL_DASHBOARD', true),

    // After deleting a ticket: "list" = back to the "All mailboxes" page, "next" = next ticket of that list.
    'after_delete_default' => env('REFRESHGLOBAL_AFTER_DELETE', 'list'),

    // Automatic refresh of the "All mailboxes" list and of the dashboard: seconds between two checks (0 = off).
    'auto_refresh_default' => (int) env('REFRESHGLOBAL_AUTO_REFRESH', 30),

    // After a deletion, back on the "All mailboxes" list at the same place in the list (no scrolling back down).
    'keep_position_default' => (bool) env('REFRESHGLOBAL_KEEP_POSITION', true),

    // Deleting a ticket: false = to FreeScout's trash (can be restored), true = removed with its e-mails at once
    // (FreeScout only, the mail server is not touched).
    'delete_permanently_default' => (bool) env('REFRESHGLOBAL_DELETE_PERMANENTLY', false),

    // Automatic emptying of the trash: tickets in the trash for more than N days are deleted for good, every day at
    // trash_auto_time (0 = never, the default).
    'trash_auto_days_default' => (int) env('REFRESHGLOBAL_TRASH_AUTO_DAYS', 0),
    'trash_auto_time' => env('REFRESHGLOBAL_TRASH_AUTO_TIME', '03:45'),

    // Ticket deleted for good (by a user, or the automatic emptying): its e-mails are also moved to the trash folder of
    // the mail server (IMAP), found automatically unless a folder name is given.
    'server_trash_default' => (bool) env('REFRESHGLOBAL_SERVER_TRASH', true),
    'server_trash_folder_default' => env('REFRESHGLOBAL_SERVER_TRASH_FOLDER', ''),

    // Mailbox badge (name and address) above each ticket on the "All mailboxes" list and the dashboard.
    'show_mailbox_default' => (bool) env('REFRESHGLOBAL_SHOW_MAILBOX', true),
];
