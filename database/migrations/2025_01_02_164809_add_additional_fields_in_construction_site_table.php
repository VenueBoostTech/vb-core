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
            $table->string('type')->nullable();
            $table->string('site_manager_email')->nullable();
            $table->string('site_manager_phone')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('no_of_workers')->nullable();
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
            //
        });
    }
};
