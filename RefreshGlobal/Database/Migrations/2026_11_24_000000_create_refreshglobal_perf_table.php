<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.23.0: performance sensors (Services/Perf/Recorder.php): one row per request, only while an administrator records.
 */
class CreateRefreshglobalPerfTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('refreshglobal_perf')) {
            Schema::create('refreshglobal_perf', function (Blueprint $table) {
                $table->increments('id');
                $table->string('uid', 16)->index();
                $table->string('route', 120);
                $table->string('method', 10)->default('');
                $table->unsignedSmallInteger('status')->default(0);
                $table->unsignedInteger('total')->default(0);
                $table->unsignedInteger('boot')->default(0);
                $table->unsignedInteger('sql_n')->default(0);
                $table->unsignedInteger('sql_ms')->default(0);
                $table->unsignedInteger('repeats')->default(0);
                $table->unsignedInteger('memory')->default(0);
                $table->decimal('load_avg', 8, 2)->nullable();
                $table->unsignedInteger('b_ttfb')->nullable();
                $table->unsignedInteger('b_ready')->nullable();
                $table->unsignedInteger('b_load')->nullable();
                $table->text('details')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('refreshglobal_perf');
    }
}
