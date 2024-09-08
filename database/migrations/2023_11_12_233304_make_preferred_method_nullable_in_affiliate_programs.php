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
        Schema::table('affiliate_programs', function (Blueprint $table) {
            // Change the 'preferred_method' column to be nullable
            $table->string('preferred_method')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            // Revert back to not nullable
            $table->string('preferred_method')->nullable(false)->change();
        });
    }
};
