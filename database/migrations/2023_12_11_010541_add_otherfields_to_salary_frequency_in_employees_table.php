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
        Schema::table('employees', function (Blueprint $table) {
            // Add two new columns for frequency number and unit
            $table->integer('frequency_number')->nullable()->after('salary_frequency');
            $table->string('frequency_unit')->nullable()->after('frequency_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salary_frequency_in_employees', function (Blueprint $table) {
            $table->dropColumn('frequency_number');
            $table->dropColumn('frequency_unit');
        });
    }
};
