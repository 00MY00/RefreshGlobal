<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.20.0: statistics of the flows of Workflow Packs 3.20+ (Services/FlowChanges.php) and the temporary flows asked by
 * RefreshGlobal (Services/FlowTemp.php). Module's own tables: no text of an e-mail or note, only numbers, the flow's
 * name and known values (status, user id, folder of the rules).
 */
class CreateRefreshglobalFlowTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('refreshglobal_flow_changes')) {
            Schema::create('refreshglobal_flow_changes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('event_id');
                $table->string('field', 40);
                $table->unsignedInteger('conversation_id');
                $table->unsignedInteger('mailbox_id')->nullable();
                $table->unsignedInteger('flow_id');
                $table->string('flow_name', 191)->default('');
                $table->string('after', 191)->nullable();
                $table->string('origin', 20)->default('new_mail');
                $table->unsignedTinyInteger('temporary')->default(0); // 0 / 1 (an integer: MAX() works on every database)
                $table->timestamp('created_at')->nullable(); // FreeScout's time zone, like its own dates

                $table->unique(['event_id', 'field'], 'rg_flow_changes_event_field');
                $table->index(['mailbox_id', 'created_at'], 'rg_flow_changes_mailbox_date');
                $table->index('flow_id', 'rg_flow_changes_flow');
                $table->index('conversation_id', 'rg_flow_changes_conversation');
            });
        }
        if (!Schema::hasTable('refreshglobal_temp_flows')) {
            Schema::create('refreshglobal_temp_flows', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('flow_id');
                $table->string('name', 191)->default('');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('tickets')->default(0);
                $table->unsignedInteger('handled')->nullable();
                $table->string('state', 20)->default('draft');
                $table->string('reason', 20)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('finished_at')->nullable();

                $table->unique('flow_id', 'rg_temp_flows_flow');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('refreshglobal_flow_changes');
        Schema::dropIfExists('refreshglobal_temp_flows');
    }
}
