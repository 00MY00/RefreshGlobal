<?php

namespace Modules\RefreshGlobal\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Modules\RefreshGlobal\Services\AutoFolders;

/**
 * Workflow Packs' table of the current folder of each ticket, for the tests when the module is not installed: same
 * schema as its migration (Modules/WorkflowPacks/Database/Migrations/2026_10_21_000000_create_workflowpacks_ticket_folders_table.php),
 * which itself skips an existing table. Created OUTSIDE the test transaction (a CREATE TABLE ends a MySQL transaction).
 * Its other table (workflowpacks_retention) is never created here: its migration does not skip an existing table.
 */
class WorkflowPacks
{
    protected static $checked = false;

    public static function ensureTable()
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;
        if (!\Schema::hasTable(AutoFolders::TABLE)) {
            \Schema::create(AutoFolders::TABLE, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('conversation_id')->unique();
                $table->unsignedInteger('mailbox_id');
                $table->string('folder', 191);
                $table->string('label', 191);
                $table->string('path', 191)->default('');
                $table->string('state', 10)->default('filed');
                $table->timestamp('filed_at')->nullable();
                $table->timestamp('cleaned_at')->nullable();
                $table->timestamps();
                $table->index(['mailbox_id', 'state']);
                $table->index('folder');
            });
        }
    }

    /** A ticket whose e-mail Workflow Packs filed into $folder ("Factures/Fournisseurs"). */
    public static function file($conversation, $folder, $state = 'filed', $path = null, $filed_at = null)
    {
        $now = $filed_at ?: date('Y-m-d H:i:s');
        \DB::table(AutoFolders::TABLE)->insert([
            'conversation_id' => $conversation->id,
            'mailbox_id'      => $conversation->mailbox_id,
            'folder'          => $folder,
            'label'           => '[WP] '.str_replace('/', ' › ', $folder),
            'path'            => $path !== null ? $path : 'INBOX.[WP] '.str_replace('/', '.', $folder),
            'state'           => $state,
            'filed_at'        => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
}
