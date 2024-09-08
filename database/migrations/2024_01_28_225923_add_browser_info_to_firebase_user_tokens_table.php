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
        Schema::table('firebase_user_tokens', function (Blueprint $table) {
            $table->string('browser_name')->nullable();
            $table->string('browser_os')->nullable();
            $table->string('browser_type')->nullable();
            $table->string('browser_version')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('firebase_user_tokens', function (Blueprint $table) {
            $table->dropColumn('browser_name');
            $table->dropColumn('browser_os');
            $table->dropColumn('browser_type');
            $table->dropColumn('browser_version');
        });
    }
};
