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
            $table->dropColumn('site_manager');
            $table->dropColumn('site_manager_email');
            $table->dropColumn('site_manager_phone');
            $table->foreignId('manager')->nullable()->constrained('employees');

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
            $table->string('site_manager')->nullable();
            $table->string('site_manager_email')->nullable();
            $table->string('site_manager_phone')->nullable();
        });
    }
};
