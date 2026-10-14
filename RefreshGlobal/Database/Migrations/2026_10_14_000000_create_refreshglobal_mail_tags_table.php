<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.11.0: origin tags of the fetched e-mails (Services/MailTags.php): one row per e-mail (thread) and tag — folder of
 * the mail server it was fetched from ("inbox", "spam", "folder:invoices"…) and "classified_spam". Module's own table,
 * read by other modules through the filters "refreshglobal.mail_tags" / "refreshglobal.thread_mail_tags".
 */
class CreateRefreshglobalMailTagsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('refreshglobal_mail_tags')) {
            return;
        }
        Schema::create('refreshglobal_mail_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('thread_id');
            $table->unsignedInteger('mailbox_id');
            $table->string('tag', 100);
            $table->string('folder', 191)->default(''); // folder of the mail server, as fetched (UTF-8)
            $table->timestamp('created_at')->nullable();

            $table->unique(['thread_id', 'tag']);
            $table->index('conversation_id');
            $table->index(['mailbox_id', 'tag']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('refreshglobal_mail_tags');
    }
}
