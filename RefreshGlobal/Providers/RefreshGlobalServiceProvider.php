<?php

namespace Modules\RefreshGlobal\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * RefreshGlobal: "All mailboxes" page for FreeScout.
 *
 * Nothing of FreeScout or of the Refresh module is modified, copied or overridden: the module only
 * - adds its own routes, views, translations, migration and command;
 * - listens to Eventy hooks found during the audit (INTEGRATION_MAP.md):
 *     menu.append (resources/views/layouts/app.blade.php:121)                 → "All mailboxes" menu entry
 *     refresh.rail_items (Modules/Refresh/Providers/RefreshServiceProvider.php:711) → same entry in Refresh's left bar
 *     conversations_table.col/th/td_before_conv_number
 *       (resources/views/conversations/conversations_table.blade.php:83,118,206) → "Mailbox" column, on its page only;
 *     conversations_table.before_subject (same file:191)                     → mailbox and automatic folder badges;
 *     dashboard.before (resources/views/secure/dashboard.blade.php:8)          → Refresh's dashboard for all mailboxes;
 * - adds a middleware to the "web" group for the deletion of tickets (Http/Middleware/AfterDelete.php).
 */
class RefreshGlobalServiceProvider extends ServiceProvider
{
    const ALIAS = 'refreshglobal';

    /** Folder type of the fake folder given to the native table (outside App\Folder::$types, not Refresh's 990). */
    const FOLDER_TYPE_VIRTUAL = 991;

    /** Set by GlobalTicketsController: the hooks print the "Mailbox" column only on the module's page. */
    public static $show_mailbox_column = false;

    /** Set by GlobalTicketsController: automatic folder of Workflow Packs of each ticket of the page [id => label]. */
    public static $folder_badges = [];

    /** Set by GlobalTicketsController: origin tags of the e-mails of each ticket of the page [id => [tag, …]]. */
    public static $mail_tag_badges = [];

    protected $defer = false;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'refreshglobal');
        $this->mergeConfigFrom(__DIR__.'/../Config/integration.php', 'refreshglobal_integration');
        $this->commands([
            \Modules\RefreshGlobal\Console\CheckCompatibilityCommand::class,
            \Modules\RefreshGlobal\Console\HealthCommand::class,
            \Modules\RefreshGlobal\Console\MailTagsCommand::class,
            \Modules\RefreshGlobal\Console\SelfTestCommand::class,
            \Modules\RefreshGlobal\Console\TrashCommand::class,
            \Modules\RefreshGlobal\Console\MailTrashCommand::class,
        ]);
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', self::ALIAS);
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', self::ALIAS);
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');

        $this->registerMenu();
        $this->registerMailboxColumn();
        $this->registerSchedule();
        $this->registerShell();
        $this->registerSettings();
        $this->registerDashboard();
        $this->registerMailTags();

        // Ticket deleted for good: its e-mails are queued for the mail server's trash (Services/MailServerTrash.php).
        // FreeScout fires this action before removing the tickets (app/Conversation.php:2163).
        \Eventy::addAction('conversations.before_delete_forever', function ($conversation_ids) {
            \Modules\RefreshGlobal\Services\MailServerTrash::queue($conversation_ids);
        }, 20, 1);

        // Deleting a ticket: where to go next, trash or permanent (Http\Middleware\AfterDelete). Added to the "web"
        // group like Refresh's own middlewares (RefreshServiceProvider.php:55-57); it acts on conversations.ajax only.
        $this->app['router']->pushMiddlewareToGroup('web', \Modules\RefreshGlobal\Http\Middleware\AfterDelete::class);
        // "from_alias" of a message checked against the addresses of its mailbox (Http\Middleware\CheckFromAlias)
        $this->app['router']->pushMiddlewareToGroup('web', \Modules\RefreshGlobal\Http\Middleware\CheckFromAlias::class);
    }

    /**
     * Origin tags of the fetched e-mails and spam left on the mail server (Services/MailTags.php). FreeScout's fetching
     * (app/Console/Commands/FetchEmails.php): filter "fetch_emails.mailbox_to_save_message" (:380, the folder of each
     * message), filter "fetch_emails.should_save_thread" (:1051, :1091, skip a spam), actions
     * "conversation.created_by_customer" / "conversation.customer_replied" (:1384, :1387, the saved thread). Read API for
     * other modules: filters "refreshglobal.mail_tags" and "refreshglobal.thread_mail_tags" (docs/api-tags.md).
     */
    protected function registerMailTags()
    {
        $T = '\Modules\RefreshGlobal\Services\MailTags';
        \Eventy::addFilter('fetch_emails.mailbox_to_save_message', function ($mailbox, $folder = null) use ($T) {
            return $T::onFolder($mailbox, $folder);
        }, 1000, 2);
        \Eventy::addFilter('fetch_emails.should_save_thread', function ($result, $data = []) use ($T) {
            return $T::shouldSave($result, $data);
        }, 1000, 2);
        foreach (['conversation.created_by_customer', 'conversation.customer_replied'] as $action) {
            \Eventy::addAction($action, function ($conversation, $thread = null) use ($T) {
                try {
                    $T::onThreadSaved($conversation, $thread);
                } catch (\Throwable $e) {
                    \Log::warning('[RefreshGlobal] [mail-tags] '.$e->getMessage());
                }
            }, 20, 2);
        }
        \Eventy::addAction('conversations.before_delete_forever', function ($ids) use ($T) {
            $T::forget($ids);
        }, 1000, 1);
        // ticket page: tags of its e-mails under the subject (resources/views/conversations/view.blade.php, action
        // "conversation.after_subject"), and "Details / raw text" in FreeScout's message menu (thread.blade.php:337,
        // "thread.menu.append"); with Refresh, whose menu is hidden, the same window opens from its message tools
        // (Public/js/shell.js threadDetails())
        \Eventy::addAction('conversation.after_subject', function ($conversation, $mailbox = null) use ($T) {
            try {
                if ($conversation && \Modules\RefreshGlobal\Services\Settings::showMailTags() && $T::enabled()) {
                    $tags = $T::forConversations([$conversation->id])[$conversation->id] ?? [];
                    if ($tags) {
                        echo '<span class="rg-conv-tags">'.self::mailTagBadges($tags).'</span>';
                    }
                }
            } catch (\Throwable $e) {
                // optional
            }
        }, 30, 2);
        \Eventy::addAction('thread.menu.append', function ($thread) {
            try {
                if ($thread && !$thread->isNote()) {
                    echo '<li><a href="#" class="rg-td-open" data-url="'.e(route('refreshglobal.thread_details', ['id' => $thread->id])).'" role="button">'
                        .e(__('refreshglobal::messages.td_menu')).'</a></li>';
                }
            } catch (\Throwable $e) {
                // optional
            }
        }, 20, 1);
        \Eventy::addAction('layout.head', function () {
            try {
                if (auth()->check() && \Route::is('conversations.view')) {
                    $c = ['url' => route('refreshglobal.thread_details', ['id' => 999999999]), 'label' => __('refreshglobal::messages.td_menu'),
                        'close' => __('refreshglobal::messages.td_close'), 'copied' => __('refreshglobal::messages.td_copied'), 'error' => __('refreshglobal::messages.td_error')];
                    echo '<meta name="refreshglobal-thread" content="'.e(json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'">'."
";
                }
            } catch (\Throwable $e) {
                // optional
            }
        });
        // read API (no dependency: the default value comes back when RefreshGlobal is missing)
        \Eventy::addFilter('refreshglobal.mail_tags', function ($default, $ids = []) use ($T) {
            return $T::forConversations((array) $ids) + (array) $default;
        }, 20, 2);
        \Eventy::addFilter('refreshglobal.thread_mail_tags', function ($default, $ids = []) use ($T) {
            return $T::forThreads((array) $ids) + (array) $default;
        }, 20, 2);
    }

    /** Dashboard HTML before Refresh's own filter runs (see registerDashboard()). */
    protected static $dashboardBefore = null;

    /**
     * Refresh's "My dashboard" over all mailboxes. FreeScout's filter "dashboard.before"
     * (resources/views/secure/dashboard.blade.php:8) runs its listeners by ascending priority
     * (overrides/tormjens/eventy/src/Event.php:34-35); Refresh appends its block at the default priority 20
     * (RefreshServiceProvider.php:491). At 19 the module notes the HTML before Refresh; at 21, if what Refresh added is
     * its dashboard block (class "rf-dash"), that addition is replaced by the same dashboard for all mailboxes.
     * Anything else (another module, a changed Refresh) is left untouched.
     */
    protected function registerDashboard()
    {
        \Eventy::addFilter('dashboard.before', function ($html) {
            self::$dashboardBefore = (string) $html;

            return $html;
        }, 19);
        \Eventy::addFilter('dashboard.before', function ($html) {
            $before = self::$dashboardBefore;
            self::$dashboardBefore = null;
            try {
                $html = (string) $html;
                if ($before === null || !auth()->check() || !\Modules\RefreshGlobal\Services\Settings::globalDashboard()
                    || !self::refreshViewsPanel() || !\Modules\RefreshGlobal\Services\GlobalDashboard::refreshApi()
                    || strpos($html, $before) !== 0
                    || strpos(substr($html, strlen($before)), 'class="rf-dash"') === false
                ) {
                    return $html;
                }
                $ours = \Modules\RefreshGlobal\Services\GlobalDashboard::render(auth()->user());

                return $ours === '' ? $html : $before.$ours;
            } catch (\Throwable $e) {
                \Log::warning('[RefreshGlobal] [RG-ERR-03] dashboard for all mailboxes: '.$e->getMessage());

                return $html;
            }
        }, 21);
    }

    /**
     * Manage › Settings › RefreshGlobal, the same way as Refresh (RefreshServiceProvider.php:91-116): filters
     * "settings.sections" (app/Http/Controllers/SettingsController.php:267), "settings.section_settings" (:250) and
     * "settings.view" (resources/views/settings/view.blade.php:27). FreeScout saves the posted values in its options
     * table itself (SettingsController::processSave, :288-379).
     */
    protected function registerSettings()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[self::ALIAS] = ['title' => 'RefreshGlobal', 'icon' => 'inbox', 'order' => 160];

            return $sections;
        }, 40);
        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section !== self::ALIAS) {
                return $settings;
            }

            return array_merge($settings, \Modules\RefreshGlobal\Services\Settings::sectionValues());
        }, 20, 2);
        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section === self::ALIAS ? 'refreshglobal::settings' : $view;
        }, 20, 2);
    }

    /**
     * Entry points inside Refresh's own interface (phone tab bar; optional replacement of its "Tickets" entry).
     * Public/js/shell.js is added to FreeScout's script bundle ("javascripts" filter, resources/views/layouts/app.blade.php:284),
     * like Refresh's scripts; its settings are written in <head> ("layout.head", app.blade.php:21) only when Refresh's
     * interface is there, so without Refresh the script does nothing.
     */
    protected function registerShell()
    {
        \Eventy::addFilter('javascripts', function ($scripts) {
            $scripts[] = \Module::getPublicPath(self::ALIAS).'/js/shell.js';

            return $scripts;
        });
        // the module's styles on every page (FreeScout's bundle, layouts/app.blade.php:33): ticket page (tags, details
        // window, landmark), Refresh's views, new e-mail form; its rules only apply to the module's own elements (.rg-*)
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath(self::ALIAS).'/css/refreshglobal.css';

            return $styles;
        });
        \Eventy::addAction('layout.head', function () {
            try {
                if (!auth()->check() || !self::refreshViewsPanel()) {
                    return;
                }
                $config = [
                    'url'     => route('refreshglobal.tickets'),
                    'label'   => __('refreshglobal::messages.menu'),
                    'icon'    => asset(\Module::getPublicPath(self::ALIAS).'/img/all-mailboxes.svg'),
                    'active'  => self::isModulePage(),
                    'replace' => \Modules\RefreshGlobal\Services\Settings::replaceRefreshTickets(),
                ];
                // Ticket page: name of its mailbox, for the landmark shown above Refresh's views panel when the
                // ticket was opened from "All mailboxes" (Public/js/shell.js, backToList())
                if (\Route::is('conversations.view')) {
                    $config += self::ticketLandmark(request()->route('id'), auth()->user());
                }
                // Refresh's views of ONE mailbox (and FreeScout's mailbox folders): the same landmark at the top of the views
                // panel, the way back to "All mailboxes" (Modules/Refresh/Http/routes.php:5)
                if (\Route::is('refresh.tickets') || \Route::is('mailboxes.view') || \Route::is('mailboxes.view.folder')) {
                    $config += self::mailboxLandmark(request()->route('mailbox_id') ?: request()->route('id'), auth()->user());
                }
                if (!empty($config['mailbox'])) {
                    $config['total'] = self::allMailboxesTotal(auth()->user());
                }
                echo '<meta name="refreshglobal" content="'.e(json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'">'."\n";
            } catch (\Exception $e) {
                // optional
            }
        });
        // "Send an e-mail" / "New ticket" (FreeScout's create form): addresses the user may send from, all his mailboxes
        // (Services/SendFrom.php), read by Public/js/shell.js sendFrom(); with or without Refresh
        \Eventy::addAction('layout.head', function () {
            try {
                if (!auth()->check() || !\Route::is('conversations.create')) {
                    return;
                }
                $config = \Modules\RefreshGlobal\Services\SendFrom::pageConfig(auth()->user(), (int) request()->route('mailbox_id'));
                if (!$config) {
                    return;
                }
                echo '<meta name="refreshglobal-from" content="'.e(json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'">'."
";
            } catch (\Throwable $e) {
                // optional: the form keeps FreeScout's own address
            }
        });
        // After Refresh's dictionary (its "layout.head" action, default priority 20): corrections of wrong strings
        // (Resources/lang/refresh-fixes.php), applied to the meta before any script reads it.
        \Eventy::addAction('layout.head', function () {
            try {
                $fixes = (array) ((require __DIR__.'/../Resources/lang/refresh-fixes.php')[app()->getLocale()] ?? []);
                if (!$fixes || !auth()->check() || !\App\Module::isActive('refresh')) {
                    return;
                }
                echo '<script '.\Helper::cspNonceAttr().'>(function(f){var m=document.querySelector(\'meta[name="refresh-l10n"]\');'
                    .'if(!m){return;}try{var d=JSON.parse(m.getAttribute("content")),c=0;for(var k in f){if(d[k]===f[k][0]){d[k]=f[k][1];c++;}}'
                    .'if(c){m.setAttribute("content",JSON.stringify(d));}}catch(e){}})('
                    .json_encode($fixes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).');</script>'."\n";
            } catch (\Throwable $e) {
                // optional
            }
        }, 30);
    }

    /**
     * Tasks run through FreeScout's scheduler (filter "schedule", app/Console/Kernel.php:190; FreeScout's cron runs
     * "php artisan schedule:run" every minute). Updates are not one of them since 1.9.0: FreeScout's Modules page or
     * the UpdateGuard module update RefreshGlobal (Services/Updates.php).
     */
    protected function registerSchedule()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            try {
                // automatic emptying of the trash (does nothing while the setting is 0 days)
                $trash = (string) config('refreshglobal.trash_auto_time', '03:45');
                $schedule->command('refreshglobal:trash --scheduled')
                    ->dailyAt(preg_match('/^\d{1,2}:\d{2}$/', $trash) ? $trash : '03:45')
                    ->withoutOverlapping();
                // e-mails of the tickets deleted for good -> mail server's trash (does nothing when the queue is empty)
                $schedule->command('refreshglobal:mail-trash')
                    ->everyMinute()
                    ->withoutOverlapping();
                // "Recalculate the tags" (settings page): picked up within a minute (does nothing otherwise)
                $schedule->command('refreshglobal:mail-tags --requested')
                    ->everyMinute()
                    ->withoutOverlapping();
            } catch (\Exception $e) {
                // no scheduled task rather than a broken scheduler
            }

            return $schedule;
        });
    }

    /** Fake folder for the native conversations table (same technique as Refresh, RefreshServiceProvider.php:228). */
    public static function virtualFolder()
    {
        $folder = new \App\Folder();
        $folder->id = 0;
        $folder->type = self::FOLDER_TYPE_VIRTUAL;
        $folder->mailbox_id = 0;

        return $folder;
    }

    public static function isModulePage()
    {
        try {
            return \Route::is('refreshglobal.*');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function registerMenu()
    {
        // FreeScout's top menu (stock interface). Refresh hides that menu and copies its plain links into its left
        // bar with a generic icon (RefreshServiceProvider.php:906-910) unless declared through refresh.rail_items.
        \Eventy::addAction('menu.append', function () {
            try {
                if (!auth()->check()) {
                    return;
                }
                echo '<li class="'.(self::isModulePage() ? 'active' : '').'"><a href="'.e(route('refreshglobal.tickets')).'">'
                    .e(__('refreshglobal::messages.menu')).'</a></li>';
            } catch (\Exception $e) {
                // the menu entry is optional: never break the layout
            }
        });

        // Refresh's left bar, with the module's own icon (documented extension point, Refresh README "For module developers")
        \Eventy::addFilter('refresh.rail_items', function ($items) {
            try {
                if (auth()->check()) {
                    $items[] = [
                        'url'    => route('refreshglobal.tickets'),
                        'label'  => __('refreshglobal::messages.menu'),
                        'icon'   => asset(\Module::getPublicPath(self::ALIAS).'/img/all-mailboxes.svg'),
                        'active' => self::isModulePage(),
                        'order'  => 15,
                    ];
                }
            } catch (\Exception $e) {
                // optional entry
            }

            return $items;
        });

        // Mailbox sidebar: FreeScout's (resources/views/mailboxes/sidebar_menu_view.blade.php:35) or Refresh's views
        // panel (Modules/Refresh/Resources/views/core/mailboxes/sidebar_menu_view.blade.php:81). On phones Refresh hides
        // its left bar and shows this panel as the views drawer: this entry is then the way to the page.
        \Eventy::addAction('mailbox.after_sidebar_buttons', function () {
            try {
                if (!auth()->check() || self::isModulePage()) {
                    return;
                }
                $url = e(route('refreshglobal.tickets'));
                $label = e(__('refreshglobal::messages.menu'));
                if (self::refreshViewsPanel()) {
                    echo '<div class="rf-views-list rg-views-entry"><a href="'.$url.'" class="rf-v" data-label="'.e(mb_strtolower(__('refreshglobal::messages.menu'))).'">'
                        .'<i class="rf-i rf-i-inbox"></i><span class="rf-v-label">'.$label.'</span></a></div>';
                } else {
                    echo '<ul class="sidebar-menu rg-sidebar-entry"><li><a href="'.$url.'"><i class="glyphicon glyphicon-inbox"></i> <span class="folder-name">'.$label.'</span></a></li></ul>';
                }
            } catch (\Exception $e) {
                // optional entry
            }
        });
    }

    /** True when the mailbox sidebar is Refresh's views panel (Refresh active and its view override present). */
    protected static function refreshViewsPanel()
    {
        try {
            return \App\Module::isActive('refresh') && view()->exists('refresh::tickets');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function registerMailboxColumn()
    {
        \Eventy::addAction('conversations_table.col_before_conv_number', function () {
            if (self::$show_mailbox_column) {
                echo '<col class="rg-col-mailbox">';
            }
        }, 5);
        \Eventy::addAction('conversations_table.th_before_conv_number', function () {
            if (self::$show_mailbox_column) {
                echo '<th class="rg-col-mailbox"><span>'.e(__('refreshglobal::messages.col_mailbox')).'</span></th>';
            }
        }, 5);
        \Eventy::addAction('conversations_table.td_before_conv_number', function ($conversation) {
            if (!self::$show_mailbox_column) {
                return;
            }
            if (!$conversation) {
                echo '<td class="rg-col-mailbox"></td>';

                return;
            }
            $name = self::mailboxName($conversation);
            echo '<td class="rg-col-mailbox"><span class="rg-mailbox-badge" title="'.e($name).'">'.e($name).'</span></td>';
        }, 5);
        // Card layout of Refresh: the mailbox is shown next to the SLA badges (Refresh writes them at priority -10 and
        // closes the badge line at 500, RefreshServiceProvider.php:396-411); CSS shows either this badge or the column.
        // Class rf-badge: Refresh's phone version turns the row's .rf-badge elements into pills
        // (Modules/Refresh/Public/js/mobile.js:592), so the mailbox also appears on phones.
        // Setting "show the mailbox above each ticket" (on by default): name and address. $show_mailbox_column is
        // true (column) or 'badge' (column + badge), set by the page before rendering the table.
        \Eventy::addAction('conversations_table.before_subject', function ($conversation) {
            if (self::$show_mailbox_column !== 'badge' || !$conversation) {
                return;
            }
            $name = self::mailboxName($conversation);
            $email = $conversation->relationLoaded('mailbox') && $conversation->mailbox ? (string) $conversation->mailbox->email : '';
            $text = e($name);
            if ($email !== '' && mb_strtolower($email) !== mb_strtolower($name)) {
                // address drawn by CSS (attribute): Refresh's phone pills are built from the badge TEXT
                // (Modules/Refresh/Public/js/mobile.js:592), they then show the name only and do not overflow the card
                $text .= ' <span class="rg-mailbox-email" data-email="'.e($email).'"></span>';
            }
            echo '<span class="rf-badge rg-mailbox-badge rg-subject-mailbox" title="'.e(trim($name.' '.$email)).'">'.$text.'</span>';
        }, 0);
        // Automatic folder of Workflow Packs where the ticket's e-mail was filed on the mail server (Services/AutoFolders.php),
        // right after the mailbox, in both layouts and on phones (class rf-badge)
        \Eventy::addAction('conversations_table.before_subject', function ($conversation) {
            if (!$conversation || !isset(self::$folder_badges[$conversation->id])) {
                return;
            }
            $label = self::$folder_badges[$conversation->id];
            echo '<span class="rf-badge rg-mailbox-badge rg-folder-badge" title="'.e(__('refreshglobal::messages.auto_folder').' : '.$label).'">'.\Modules\RefreshGlobal\Services\AutoFolders::icon().' '.e($label).'</span>';
        }, 1);
        // Origin tags of the ticket's e-mails (Services/MailTags.php), setting "show the tags"
        \Eventy::addAction('conversations_table.before_subject', function ($conversation) {
            if (!$conversation || empty(self::$mail_tag_badges[$conversation->id])) {
                return;
            }
            echo self::mailTagBadges(self::$mail_tag_badges[$conversation->id]);
        }, 2);
    }

    /**
     * Ticket page: its id and the name of its mailbox ("Mailbox: Support"), for the landmark above Refresh's views
     * panel (Public/js/shell.js backToList()); nothing when the user can not see that mailbox.
     *
     * @return array ['conversation' => id, 'mailbox' => text] or []
     */
    public static function ticketLandmark($conversation_id, $user)
    {
        $conversation = \App\Conversation::find((int) $conversation_id);
        if (!$conversation || !$conversation->mailbox || !$user
            || !in_array((int) $conversation->mailbox_id, (new \Modules\RefreshGlobal\Services\MailboxAccess($user))->allowedIds(), true)) {
            return [];
        }

        return [
            'conversation' => (int) $conversation->id,
            'mailbox'      => __('refreshglobal::messages.conv_mailbox', ['name' => $conversation->mailbox->name]),
        ];
    }

    /** Badges of origin tags ("classified_spam" in red). */
    public static function mailTagBadges(array $tags)
    {
        $html = '';
        foreach ($tags as $tag) {
            $html .= '<span class="rf-badge rg-mail-tag'.($tag === \Modules\RefreshGlobal\Services\MailTags::SPAM ? ' rg-mail-tag-spam' : '').'" title="'
                .e(__('refreshglobal::messages.mail_tag').' : '.$tag).'">'.e($tag).'</span>';
        }

        return $html;
    }

    /** Mailbox view: ['mailboxView' => true, 'mailbox' => "Mailbox: Support"], or [] when the user can not see it. */
    public static function mailboxLandmark($mailbox_id, $user)
    {
        $mailbox = \App\Mailbox::find((int) $mailbox_id);
        if (!$mailbox || !$user || !in_array((int) $mailbox->id, (new \Modules\RefreshGlobal\Services\MailboxAccess($user))->allowedIds(), true)) {
            return [];
        }

        return ['mailboxView' => true, 'mailbox' => __('refreshglobal::messages.conv_mailbox', ['name' => $mailbox->name])];
    }

    /** Number of tickets of "All mailboxes" (whole list, the user's rights), kept one minute. */
    public static function allMailboxesTotal($user)
    {
        try {
            return (int) \Cache::remember('refreshglobal.total.'.$user->id, 1, function () use ($user) {
                $access = new \Modules\RefreshGlobal\Services\MailboxAccess($user);

                return (new \Modules\RefreshGlobal\Services\GlobalTicketQuery($access, \Modules\RefreshGlobal\Services\GlobalTicketQuery::normalize([], $access)))->base()->count();
            });
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Value of $show_mailbox_column for the module's lists: with or without the badge above each ticket. */
    public static function mailboxColumnMode()
    {
        return \Modules\RefreshGlobal\Services\Settings::showMailbox() ? 'badge' : true;
    }

    /** Mailbox name from the relation eager-loaded by GlobalTicketQuery::paginate() (no query per row). */
    protected static function mailboxName($conversation)
    {
        return $conversation->relationLoaded('mailbox') && $conversation->mailbox ? (string) $conversation->mailbox->name : '';
    }
}
