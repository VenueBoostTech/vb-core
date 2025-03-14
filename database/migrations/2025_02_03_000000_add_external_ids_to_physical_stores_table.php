<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('physical_stores', function (Blueprint $table) {
            $table->json('external_ids')->nullable()->after('code');
        });
    }

    public function down()
    {
        Schema::table('physical_stores', function (Blueprint $table) {
            $table->dropColumn('external_ids');
        });
    }
};
