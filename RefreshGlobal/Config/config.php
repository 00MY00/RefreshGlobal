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
    'auto_update_time' => env('REFRESHGLOBAL_AUTO_UPDATE_TIME', '03:30'),
    'auto_update_default' => (bool) env('REFRESHGLOBAL_AUTO_UPDATE', false),

    // With Refresh: hide its "Tickets" entry (left bar, phone tab bar) and keep only "All mailboxes" (default before
    // an administrator changes it on the diagnostic page).
    'replace_refresh_tickets_default' => (bool) env('REFRESHGLOBAL_REPLACE_REFRESH_TICKETS', false),
];
