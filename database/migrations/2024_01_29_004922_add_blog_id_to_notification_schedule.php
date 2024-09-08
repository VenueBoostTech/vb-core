<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notification_schedule', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_id')->nullable()->after('notification_configuration_id');

            $table->foreign('blog_id')->references('id')->on('blogs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_schedule', function (Blueprint $table) {
            $table->dropForeign(['blog_id']);
            $table->dropColumn('blog_id');
        });
    }
};
