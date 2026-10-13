<?php

use Illuminate\Database\Migrations\Migration;

/**
 * 1.9.0: RefreshGlobal no longer updates itself (FreeScout's Modules page or the UpdateGuard module do it): the two
 * options of its former updater (automatic update on/off, "Update now" request) are removed from FreeScout's options
 * table. Its former backups in storage/app/refreshglobal/backups/ are left in place (they can be deleted by hand).
 */
class RemoveRefreshglobalUpdaterOptions extends Migration
{
    public function up()
    {
        try {
            \DB::table('options')->whereIn('name', ['refreshglobal.auto_update', 'refreshglobal.update_requested'])->delete();
            \Cache::forget('options');
        } catch (\Throwable $e) {
            // nothing to clean
        }
    }

    public function down()
    {
        // nothing to restore: the former updater writes its options again if it is reinstalled
    }
}
