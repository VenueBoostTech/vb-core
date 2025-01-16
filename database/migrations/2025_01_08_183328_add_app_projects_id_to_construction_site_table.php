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
        Schema::table('construction_site', function (Blueprint $table) {
            $table->foreignId('app_project_id')->nullable();
            $table->foreign('app_project_id')->references('id')->on('app_projects');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('construction_site', function (Blueprint $table) {
            $table->dropForeign(['app_project_id']);
            $table->dropColumn('app_project_id');
        });
    }
};
