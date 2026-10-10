<?php

/*
 * Integration points of RefreshGlobal: every element of FreeScout or of the Refresh module the code relies on.
 * This list is the single source read by the compatibility checker (php artisan refreshglobal:check). The
 * "source" values are the references found during the audit (see INTEGRATION_MAP.md); they are documentation only.
 *
 * Severities: warning (works, but outside the tested range), degraded (works with FreeScout's standard look or
 * without a secondary feature), blocking (the ticket list is not shown at all).
 */
return [
    // Version ranges that were tested (COMPATIBILITY.md). Outside them: warning only.
    'versions' => [
        'freescout' => ['min' => '1.8.0', 'max_exclusive' => '1.9.0', 'tested' => '1.8.245'],
        'refresh'   => ['min' => '1.4.0', 'max_exclusive' => '1.5.0', 'tested' => '1.4.3'],
    ],

    // Refresh module (third party). Only read, never modified.
    'refresh' => [
        'alias' => 'refresh',
        'css'   => [
            // Loaded by Refresh itself through the "stylesheets" filter (Providers/RefreshServiceProvider.php:123-129)
            'Public/css/icons.css',
            'Public/css/refresh.css',
            'Public/css/mobile.css',
        ],
        'js'    => [
            // Loaded by Refresh itself through the "javascripts" filter (Providers/RefreshServiceProvider.php:131-138)
            'Public/js/editor.js',
            'Public/js/new.js',
            'Public/js/mobile.js',
        ],
    ],

    // Views included or extended by the module's pages.
    'views' => [
        'RG-VIEW-01' => ['view' => 'layouts.app', 'severity' => 'blocking', 'source' => 'resources/views/layouts/app.blade.php'],
        'RG-VIEW-02' => ['view' => 'conversations/conversations_table', 'severity' => 'blocking', 'source' => 'resources/views/conversations/conversations_table.blade.php'],
        'RG-VIEW-03' => ['view' => 'partials/flash_messages', 'severity' => 'degraded', 'source' => 'resources/views/partials/flash_messages.blade.php'],
        'RG-VIEW-04' => ['view' => 'partials/empty', 'severity' => 'degraded', 'source' => 'resources/views/partials/empty.blade.php'],
        // Refresh's list page: the HTML structure of the module's page is copied from it (classes rf-*).
        // Language switch: FreeScout's list of languages (the profile page uses it, users/profile.blade.php:156)
        'RG-VIEW-06' => ['view' => 'partials/locale_options', 'family' => 'view_lang', 'severity' => 'degraded', 'source' => 'resources/views/partials/locale_options.blade.php'],
        'RG-VIEW-05' => ['view' => 'refresh::tickets', 'severity' => 'degraded', 'refresh' => true, 'source' => 'Modules/Refresh/Resources/views/tickets.blade.php'],
    ],

    // Eventy hooks used, and the file where FreeScout / Refresh fires them.
    'hooks' => [
        'RG-HOOK-01' => ['hook' => 'menu.append', 'file' => 'resources/views/layouts/app.blade.php', 'needle' => "@action('menu.append')", 'severity' => 'degraded', 'source' => 'resources/views/layouts/app.blade.php:121'],
        'RG-HOOK-02' => ['hook' => 'conversations_table.col_before_conv_number', 'file' => 'resources/views/conversations/conversations_table.blade.php', 'needle' => "@action('conversations_table.col_before_conv_number')", 'severity' => 'degraded', 'source' => 'resources/views/conversations/conversations_table.blade.php:83'],
        'RG-HOOK-03' => ['hook' => 'conversations_table.th_before_conv_number', 'file' => 'resources/views/conversations/conversations_table.blade.php', 'needle' => "@action('conversations_table.th_before_conv_number')", 'severity' => 'degraded', 'source' => 'resources/views/conversations/conversations_table.blade.php:118'],
        'RG-HOOK-04' => ['hook' => 'conversations_table.td_before_conv_number', 'file' => 'resources/views/conversations/conversations_table.blade.php', 'needle' => "@action('conversations_table.td_before_conv_number', \$conversation)", 'severity' => 'degraded', 'source' => 'resources/views/conversations/conversations_table.blade.php:206'],
        'RG-HOOK-05' => ['hook' => 'conversations_table.before_subject', 'file' => 'resources/views/conversations/conversations_table.blade.php', 'needle' => "@action('conversations_table.before_subject', \$conversation)", 'severity' => 'degraded', 'source' => 'resources/views/conversations/conversations_table.blade.php:191'],
        'RG-HOOK-06' => ['hook' => 'mailbox.url', 'file' => 'resources/views/layouts/app.blade.php', 'needle' => "'mailbox.url'", 'severity' => 'degraded', 'source' => 'resources/views/layouts/app.blade.php:77,85'],
        'RG-HOOK-08' => ['hook' => 'mailbox.after_sidebar_buttons', 'file' => 'resources/views/mailboxes/sidebar_menu_view.blade.php', 'needle' => "@action('mailbox.after_sidebar_buttons')", 'severity' => 'degraded', 'source' => 'resources/views/mailboxes/sidebar_menu_view.blade.php:35'],
        'RG-HOOK-09' => ['hook' => 'mailbox.after_sidebar_buttons', 'refresh' => true, 'file' => 'Resources/views/core/mailboxes/sidebar_menu_view.blade.php', 'needle' => "@action('mailbox.after_sidebar_buttons')", 'severity' => 'degraded', 'source' => 'Modules/Refresh/Resources/views/core/mailboxes/sidebar_menu_view.blade.php:81'],
        'RG-HOOK-10' => ['hook' => 'schedule', 'file' => 'app/Console/Kernel.php', 'needle' => "\\Eventy::filter('schedule', \$schedule)", 'severity' => 'degraded', 'source' => 'app/Console/Kernel.php:190'],
        'RG-HOOK-11' => ['hook' => 'javascripts', 'file' => 'resources/views/layouts/app.blade.php', 'needle' => "\\Eventy::filter('javascripts'", 'severity' => 'degraded', 'source' => 'resources/views/layouts/app.blade.php:284'],
        'RG-HOOK-12' => ['hook' => 'layout.head', 'file' => 'resources/views/layouts/app.blade.php', 'needle' => "@action('layout.head')", 'severity' => 'degraded', 'source' => 'resources/views/layouts/app.blade.php:21'],
        'RG-HOOK-13' => ['hook' => '.rf-m-tab-tickets', 'family' => 'marker', 'refresh' => true, 'file' => 'Public/js/mobile.js', 'needle' => "'rf-m-tab-tickets'", 'severity' => 'degraded', 'source' => 'Modules/Refresh/Public/js/mobile.js:133'],
        'RG-HOOK-14' => ['hook' => '.rf-i-fd-all-tickets', 'family' => 'marker', 'refresh' => true, 'file' => 'Providers/RefreshServiceProvider.php', 'needle' => "'icon' => 'fd-all-tickets'", 'severity' => 'degraded', 'source' => 'Modules/Refresh/Providers/RefreshServiceProvider.php:698'],
        'RG-HOOK-15' => ['hook' => 'settings.sections', 'file' => 'app/Http/Controllers/SettingsController.php', 'needle' => "\\Eventy::filter('settings.sections'", 'severity' => 'degraded', 'source' => 'app/Http/Controllers/SettingsController.php:267'],
        'RG-HOOK-16' => ['hook' => 'settings.section_settings', 'file' => 'app/Http/Controllers/SettingsController.php', 'needle' => "\\Eventy::filter('settings.section_settings'", 'severity' => 'degraded', 'source' => 'app/Http/Controllers/SettingsController.php:250'],
        'RG-HOOK-17' => ['hook' => 'settings.view', 'file' => 'resources/views/settings/view.blade.php', 'needle' => "'settings.view'", 'severity' => 'degraded', 'source' => 'resources/views/settings/view.blade.php:27'],
        // Dashboard for all mailboxes: FreeScout's filter, and Refresh's block that it replaces (class rf-dash)
        'RG-HOOK-18' => ['hook' => 'dashboard.before', 'family' => 'hook_dash', 'file' => 'resources/views/secure/dashboard.blade.php', 'needle' => "@filter('dashboard.before'", 'severity' => 'degraded', 'source' => 'resources/views/secure/dashboard.blade.php:8'],
        'RG-HOOK-19' => ['hook' => 'dashboard.before (Refresh)', 'family' => 'marker_dash', 'refresh' => true, 'file' => 'Providers/RefreshServiceProvider.php', 'needle' => "\\Eventy::addFilter('dashboard.before'", 'severity' => 'degraded', 'source' => 'Modules/Refresh/Providers/RefreshServiceProvider.php:491'],
        'RG-HOOK-20' => ['hook' => '.rf-dash', 'family' => 'marker_dash', 'refresh' => true, 'file' => 'Resources/views/dashboard.blade.php', 'needle' => 'class="rf-dash"', 'severity' => 'degraded', 'source' => 'Modules/Refresh/Resources/views/dashboard.blade.php:13'],
        // Deleting a ticket (Http/Middleware/AfterDelete.php): actions of FreeScout's conversations.ajax
        'RG-HOOK-21' => ['hook' => 'conversations.ajax delete_conversation(_forever)', 'family' => 'hook_delete', 'file' => 'app/Http/Controllers/ConversationsController.php', 'needle' => "case 'delete_conversation_forever':", 'severity' => 'degraded', 'source' => 'app/Http/Controllers/ConversationsController.php:1944,1966,2164'],
        // Refresh's dictionary of its phone strings (corrections of Resources/lang/refresh-fixes.php)
        'RG-HOOK-22' => ['hook' => 'meta refresh-l10n', 'family' => 'marker_l10n', 'refresh' => true, 'file' => 'Providers/RefreshServiceProvider.php', 'needle' => 'meta name="refresh-l10n"', 'severity' => 'warning', 'source' => 'Modules/Refresh/Providers/RefreshServiceProvider.php:77-86'],
        // Phone "Ticket actions" sheet of Refresh, completed with "Delete Forever" for tickets in the trash (Public/js/shell.js)
        'RG-HOOK-23' => ['hook' => '.rf-m-sheet-actions', 'family' => 'marker_mobile', 'refresh' => true, 'file' => 'Public/js/mobile.js', 'needle' => "'rf-m-sheet-actions'", 'severity' => 'warning', 'source' => 'Modules/Refresh/Public/js/mobile.js:86, 814-846'],
        'RG-HOOK-07' => ['hook' => 'refresh.rail_items', 'refresh' => true, 'file' => 'Providers/RefreshServiceProvider.php', 'needle' => "'refresh.rail_items'", 'severity' => 'degraded', 'source' => 'Modules/Refresh/Providers/RefreshServiceProvider.php:711'],
    ],

    // Core classes / methods (class_exists, method_exists).
    'core' => [
        'RG-CORE-01' => ['class' => 'App\User', 'methods' => ['isAdmin', 'getFullName', 'mailboxesCanView', 'canSeeOnlyAssignedConversations'], 'source' => 'app/User.php:223,233,251,1353'],
        'RG-CORE-02' => ['class' => 'App\Conversation', 'methods' => ['url', 'getSubject', 'getStatusName', 'statusCodeToName', 'mailbox', 'customer', 'user'], 'source' => 'app/Conversation.php:1109,1749,606,618,294,312,270'],
        'RG-CORE-03' => ['class' => 'App\Mailbox', 'methods' => ['isArchived'], 'source' => 'app/Mailbox.php:434'],
        'RG-CORE-04' => ['class' => 'App\Customer', 'methods' => ['getFullName'], 'source' => 'app/Customer.php:526'],
        'RG-CORE-05' => ['class' => 'App\Misc\Helper', 'methods' => ['getSubdirectory', 'isPgSql'], 'source' => 'app/Misc/Helper.php:1398,1978'],
        'RG-CORE-06' => ['constants' => [
            'App\Conversation::STATUS_ACTIVE', 'App\Conversation::STATUS_PENDING', 'App\Conversation::STATUS_CLOSED',
            'App\Conversation::STATUS_SPAM', 'App\Conversation::STATE_PUBLISHED',
        ], 'source' => 'app/Conversation.php:78-81,131'],
        // Automatic update only (degraded: the list works without them)
        'RG-CORE-07' => ['class' => 'App\Option', 'methods' => ['get', 'set'], 'severity' => 'degraded', 'source' => 'app/Option.php:35,75'],
        // Dashboard for all mailboxes (degraded: Refresh's own dashboard is shown instead)
        'RG-CORE-09' => ['class' => 'Modules\Refresh\Services\Views', 'methods' => ['query', 'labels'], 'refresh' => true, 'family' => 'core_refresh', 'severity' => 'degraded', 'source' => 'Modules/Refresh/Services/Views.php:89,99'],
        'RG-CORE-10' => ['class' => 'Modules\Refresh\Services\Dashboard', 'methods' => ['chart', 'delta', 'duration'], 'refresh' => true, 'family' => 'core_refresh', 'severity' => 'degraded', 'source' => 'Modules/Refresh/Services/Dashboard.php:121,130,147'],
        'RG-CORE-11' => ['class' => 'Modules\Refresh\Services\Settings', 'methods' => ['resolutionHours'], 'refresh' => true, 'family' => 'core_refresh', 'severity' => 'degraded', 'source' => 'Modules/Refresh/Services/Settings.php:26'],
        // Deleting a ticket permanently in a bulk delete (degraded: the tickets stay in the trash)
        'RG-CORE-12' => ['class' => 'App\Conversation', 'methods' => ['deleteForever'], 'constants' => ['App\Conversation::STATE_DELETED'], 'family' => 'core_delete', 'severity' => 'degraded', 'source' => 'app/Conversation.php:2156'],
        // "Empty the trash" (button and automatic emptying): FreeScout's own permanent deletion, its permission
        'RG-CORE-13' => ['class' => 'App\Conversation', 'methods' => ['deleteConversationsForever'], 'constants' => ['App\Conversation::STATE_DELETED', 'App\User::PERM_DELETE_CONVERSATIONS'], 'family' => 'core_trash', 'severity' => 'degraded', 'source' => 'app/Conversation.php:2161, app/User.php:89'],
        'RG-CORE-08' => ['classes' => ['ZipArchive', 'GuzzleHttp\Client', 'Symfony\Component\Process\Process', 'Symfony\Component\Process\PhpExecutableFinder'], 'severity' => 'degraded', 'source' => 'PHP zip extension (config/installer.php), vendor/guzzlehttp, vendor/symfony/process'],
    ],

    // Named routes the module links to.
    'routes' => [
        'RG-ROUTE-01' => ['route' => 'conversations.view', 'severity' => 'blocking', 'source' => 'routes/web.php:63'],
        'RG-ROUTE-02' => ['route' => 'mailboxes.view', 'severity' => 'blocking', 'source' => 'routes/web.php:82'],
        'RG-ROUTE-03' => ['route' => 'refreshglobal.tickets', 'severity' => 'blocking', 'source' => 'Modules/RefreshGlobal/Http/routes.php'],
        'RG-ROUTE-04' => ['route' => 'conversations.ajax', 'severity' => 'degraded', 'source' => 'routes/web.php:64'],
    ],

    // Tables / columns read. RG-DB-05 is the module's own table (saved views only).
    'database' => [
        'RG-DB-01' => ['table' => 'conversations', 'columns' => ['id', 'number', 'mailbox_id', 'status', 'state', 'user_id', 'customer_id', 'customer_email', 'subject', 'created_at', 'last_reply_at', 'closed_at'], 'severity' => 'blocking', 'source' => 'database/migrations/2018_07_11_010333_create_conversations_table.php'],
        'RG-DB-02' => ['table' => 'mailboxes', 'columns' => ['id', 'name'], 'severity' => 'blocking', 'source' => 'database/migrations/2018_06_25_065719_create_mailboxes_table.php'],
        'RG-DB-03' => ['table' => 'customers', 'columns' => ['id', 'first_name', 'last_name'], 'severity' => 'blocking', 'source' => 'database/migrations/2018_07_09_053559_create_customers_table.php'],
        'RG-DB-04' => ['table' => 'mailbox_user', 'columns' => ['mailbox_id', 'user_id'], 'severity' => 'blocking', 'source' => 'database/migrations/2018_06_29_041002_create_mailbox_user_table.php'],
        // "in the trash since" for the automatic emptying (set by Conversation::deleteToFolder(), app/Conversation.php:2125)
        'RG-DB-06' => ['table' => 'conversations', 'columns' => ['user_updated_at', 'updated_at'], 'family' => 'db_trash', 'severity' => 'degraded', 'source' => 'database/migrations/2018_07_11_010333_create_conversations_table.php:67'],
        'RG-DB-05' => ['table' => 'refreshglobal_saved_views', 'columns' => ['id', 'user_id', 'name', 'filters', 'is_default'], 'severity' => 'degraded', 'source' => 'Modules/RefreshGlobal/Database/Migrations'],
    ],
];
