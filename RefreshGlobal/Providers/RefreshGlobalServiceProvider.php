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

    protected $defer = false;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'refreshglobal');
        $this->mergeConfigFrom(__DIR__.'/../Config/integration.php', 'refreshglobal_integration');
        $this->commands([
            \Modules\RefreshGlobal\Console\CheckCompatibilityCommand::class,
            \Modules\RefreshGlobal\Console\UpdateCommand::class,
            \Modules\RefreshGlobal\Console\SelfTestCommand::class,
            \Modules\RefreshGlobal\Console\TrashCommand::class,
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

        // Deleting a ticket: where to go next, trash or permanent (Http\Middleware\AfterDelete). Added to the "web"
        // group like Refresh's own middlewares (RefreshServiceProvider.php:55-57); it acts on conversations.ajax only.
        $this->app['router']->pushMiddlewareToGroup('web', \Modules\RefreshGlobal\Http\Middleware\AfterDelete::class);
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
                echo '<meta name="refreshglobal" content="'.e(json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'">'."\n";
            } catch (\Exception $e) {
                // optional
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
     * Daily update run through FreeScout's scheduler (filter "schedule", app/Console/Kernel.php:190; FreeScout's
     * cron runs "php artisan schedule:run" every minute). It checks for a new version every day and installs it
     * only when the automatic update is on (php artisan refreshglobal:update --enable, or the diagnostic page).
     */
    protected function registerSchedule()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            try {
                $time = (string) config('refreshglobal.auto_update_time', '03:30');
                $schedule->command('refreshglobal:update --scheduled')
                    ->dailyAt(preg_match('/^\d{1,2}:\d{2}$/', $time) ? $time : '03:30')
                    ->withoutOverlapping();
                // "Update now" button (settings / diagnostic page): picked up within a minute
                $schedule->command('refreshglobal:update --requested')
                    ->everyMinute()
                    ->withoutOverlapping();
                // automatic emptying of the trash (does nothing while the setting is 0 days)
                $trash = (string) config('refreshglobal.trash_auto_time', '03:45');
                $schedule->command('refreshglobal:trash --scheduled')
                    ->dailyAt(preg_match('/^\d{1,2}:\d{2}$/', $trash) ? $trash : '03:45')
                    ->withoutOverlapping();
            } catch (\Exception $e) {
                // no automatic update rather than a broken scheduler
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
                $text .= ' <span class="rg-mailbox-email">'.e($email).'</span>';
            }
            echo '<span class="rf-badge rg-mailbox-badge rg-subject-mailbox" title="'.e(trim($name.' '.$email)).'">'.$text.'</span>';
        }, 0);
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
