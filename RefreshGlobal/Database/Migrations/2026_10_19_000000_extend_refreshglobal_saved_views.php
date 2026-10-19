<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.15.0: saved views can be shared (Services/SavedViews.php). Additions only, nothing removed, so that an older
 * RefreshGlobal still reads the table (UpdateGuard: "migrations": "backward-compatible"):
 *  - refreshglobal_saved_views: visibility (private | users | global), schema_version of the stored definition,
 *    display order, "users may duplicate it", default set by an administrator for everyone it is shown to, last editor;
 *  - refreshglobal_view_users: the users a view is shared with (and whether they may edit it);
 *  - refreshglobal_view_prefs: per user and view, pinned and "my default view" (a personal choice never changes the
 *    shared view); the users' current default views are copied there;
 *  - refreshglobal_view_audit: who created, changed, shared or deleted a shared view.
 * Users are referenced by id only (FreeScout's users table), nothing of theirs is copied. No foreign keys: FreeScout's
 * own tables do not declare them either, the module removes its rows itself.
 */
class ExtendRefreshglobalSavedViews extends Migration
{
    public function up()
    {
        if (Schema::hasTable('refreshglobal_saved_views')) {
            Schema::table('refreshglobal_saved_views', function (Blueprint $table) {
                if (!Schema::hasColumn('refreshglobal_saved_views', 'visibility')) {
                    $table->string('visibility', 20)->default('private');
                    $table->index('visibility', 'rg_views_visibility');
                }
                if (!Schema::hasColumn('refreshglobal_saved_views', 'schema_version')) {
                    // 1 = the flat filters of 1.0 to 1.14, read and upgraded by SavedViews::definition()
                    $table->unsignedSmallInteger('schema_version')->default(1);
                }
                if (!Schema::hasColumn('refreshglobal_saved_views', 'sort_order')) {
                    $table->integer('sort_order')->default(0);
                }
                if (!Schema::hasColumn('refreshglobal_saved_views', 'allow_duplicate')) {
                    $table->boolean('allow_duplicate')->default(true);
                }
                if (!Schema::hasColumn('refreshglobal_saved_views', 'default_scope')) {
                    // '' | 'all' (global view) | 'shared' (users it is shared with): set by an administrator
                    $table->string('default_scope', 10)->default('');
                }
                if (!Schema::hasColumn('refreshglobal_saved_views', 'updated_by')) {
                    $table->unsignedInteger('updated_by')->nullable();
                }
            });
        }

        if (!Schema::hasTable('refreshglobal_view_users')) {
            Schema::create('refreshglobal_view_users', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('view_id');
                $table->unsignedInteger('user_id');
                $table->boolean('can_edit')->default(false);
                $table->timestamps();

                $table->unique(['view_id', 'user_id'], 'rg_view_users_unique');
                $table->index('user_id', 'rg_view_users_user');
            });
        }

        if (!Schema::hasTable('refreshglobal_view_prefs')) {
            Schema::create('refreshglobal_view_prefs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('view_id');
                $table->boolean('pinned')->default(false);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'view_id'], 'rg_view_prefs_unique');
                $table->index('view_id', 'rg_view_prefs_view');
            });
            // the default view each user had chosen (refreshglobal_saved_views.is_default, still kept up to date)
            if (Schema::hasTable('refreshglobal_saved_views')) {
                $now = date('Y-m-d H:i:s');
                $rows = [];
                foreach (\DB::table('refreshglobal_saved_views')->where('is_default', true)->get(['id', 'user_id']) as $view) {
                    $rows[] = ['user_id' => $view->user_id, 'view_id' => $view->id, 'pinned' => false, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now];
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    \DB::table('refreshglobal_view_prefs')->insert($chunk);
                }
            }
        }

        if (!Schema::hasTable('refreshglobal_view_audit')) {
            Schema::create('refreshglobal_view_audit', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('view_id');
                $table->string('view_name', 100);
                $table->unsignedInteger('user_id')->nullable();
                $table->string('action', 20);
                $table->string('details', 255)->default('');
                $table->timestamp('created_at')->nullable();

                $table->index('view_id', 'rg_view_audit_view');
            });
        }
        // lists of visible views cached by Services/SavedViews.php: built again with the new tables
        try {
            \Cache::forever('refreshglobal.views_version', uniqid('', true));
        } catch (\Throwable $e) {
            // they expire after one minute anyway
        }
    }

    public function down()
    {
        Schema::dropIfExists('refreshglobal_view_audit');
        Schema::dropIfExists('refreshglobal_view_prefs');
        Schema::dropIfExists('refreshglobal_view_users');
        if (Schema::hasTable('refreshglobal_saved_views')) {
            if (Schema::hasColumn('refreshglobal_saved_views', 'visibility')) {
                Schema::table('refreshglobal_saved_views', function (Blueprint $table) {
                    $table->dropIndex('rg_views_visibility');
                });
            }
            foreach (['visibility', 'schema_version', 'sort_order', 'allow_duplicate', 'default_scope', 'updated_by'] as $column) {
                if (Schema::hasColumn('refreshglobal_saved_views', $column)) {
                    Schema::table('refreshglobal_saved_views', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
}
