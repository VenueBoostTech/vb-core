<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExternalIdsToAppClientsAndEmployees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('app_clients', function (Blueprint $table) {
            $table->json('external_ids')->nullable()->after('user_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->json('external_ids')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('app_clients', function (Blueprint $table) {
            $table->dropColumn('external_ids');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('external_ids');
        });
    }
}
