<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateQualityInspectionsProjectForeignKey extends Migration
{
    public function up()
    {
//        // Drop all foreign keys on this column via raw SQL
//        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
//
//        Schema::table('quality_inspections', function (Blueprint $table) {
//            $table->foreign('app_project_id')
//                ->references('id')
//                ->on('app_projects')
//                ->onDelete('cascade');
//        });
//
//        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->foreign('app_project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
