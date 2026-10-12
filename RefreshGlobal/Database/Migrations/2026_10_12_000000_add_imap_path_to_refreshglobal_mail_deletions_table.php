<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.8.0: folder of the mail server where the e-mail was filed by Workflow Packs ("INBOX.[WP] Factures"), copied when
 * the ticket is deleted for good: the e-mail is looked for there too (Services/MailServerTrash.php). Module's own table.
 */
class AddImapPathToRefreshglobalMailDeletionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('refreshglobal_mail_deletions') && !Schema::hasColumn('refreshglobal_mail_deletions', 'imap_path')) {
            Schema::table('refreshglobal_mail_deletions', function (Blueprint $table) {
                $table->string('imap_path', 191)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('refreshglobal_mail_deletions') && Schema::hasColumn('refreshglobal_mail_deletions', 'imap_path')) {
            Schema::table('refreshglobal_mail_deletions', function (Blueprint $table) {
                $table->dropColumn('imap_path');
            });
        }
    }
}
