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
        Schema::table('accommodation_rules', function (Blueprint $table) {
            $table->boolean('guest_requirements')->default(false);
            $table->boolean('guest_phone')->default(false);
            $table->boolean('guest_identification')->default(false);
            $table->enum('guest_identification_type', ['ID', 'Passport'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accommodation_rules', function (Blueprint $table) {
            $table->dropColumn(['guest_requirements', 'guest_phone', 'guest_identification', 'guest_identification_type']);
        });
    }
};
