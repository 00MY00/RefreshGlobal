<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal saved views of the "All mailboxes" page. Only this table is created: no core table is changed.
 * Reversible: php artisan module:migrate-rollback RefreshGlobal (or the installer's --uninstall).
 */
class CreateRefreshglobalSavedViewsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('refreshglobal_saved_views')) {
            return;
        }
        Schema::create('refreshglobal_saved_views', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name', 100);
            $table->text('filters'); // JSON
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refreshglobal_saved_views');
    }
}
