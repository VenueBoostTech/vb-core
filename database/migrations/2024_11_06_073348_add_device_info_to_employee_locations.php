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
        Schema::table('employee_locations', function (Blueprint $table) {
            // Add new columns
            $table->enum('device_platform', ['ios', 'android'])->nullable()->after('provider');
            $table->string('device_os_version')->nullable()->after('device_platform');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_locations', function (Blueprint $table) {

            // Remove new columns
            $table->dropColumn('device_platform');
            $table->dropColumn('device_os_version');

        });
    }
};
