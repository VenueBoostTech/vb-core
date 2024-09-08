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
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->decimal('reward_value', 8, 2)->after('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn('reward_value');
        });
    }
};
