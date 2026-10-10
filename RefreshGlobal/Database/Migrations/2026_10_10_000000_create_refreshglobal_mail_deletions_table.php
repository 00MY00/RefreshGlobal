<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-mails to move to the mail server's trash after their ticket was deleted for good (Services/MailServerTrash.php).
 * Filled when the ticket is deleted, emptied by the scheduled task refreshglobal:mail-trash (IMAP is never used
 * during the deletion itself). Only this table is created: no core table is changed.
 */
class CreateRefreshglobalMailDeletionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('refreshglobal_mail_deletions')) {
            return;
        }
        Schema::create('refreshglobal_mail_deletions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mailbox_id');
            $table->string('message_id', 998);
            $table->string('folder', 10)->default('in'); // in = fetched folders, sent = "Sent" folder of the mailbox
            $table->string('status', 20)->default('pending'); // pending | moved | not_found | failed | skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'mailbox_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('refreshglobal_mail_deletions');
    }
}
