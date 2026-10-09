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
 *       (resources/views/conversations/conversations_table.blade.php:83,118,206) → "Mailbox" column, on its page only.
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
        \Eventy::addAction('conversations_table.before_subject', function ($conversation) {
            if (self::$show_mailbox_column && $conversation) {
                $name = self::mailboxName($conversation);
                echo '<span class="rf-badge rg-mailbox-badge rg-subject-mailbox" title="'.e($name).'">'.e($name).'</span>';
            }
        }, 0);
    }

    /** Mailbox name from the relation eager-loaded by GlobalTicketQuery::paginate() (no query per row). */
    protected static function mailboxName($conversation)
    {
        return $conversation->relationLoaded('mailbox') && $conversation->mailbox ? (string) $conversation->mailbox->name : '';
    }
}
