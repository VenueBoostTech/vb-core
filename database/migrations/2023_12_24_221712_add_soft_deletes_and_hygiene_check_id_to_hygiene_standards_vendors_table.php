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
        Schema::table('hygiene_standards_vendors', function (Blueprint $table) {
            $table->softDeletes(); // Adds the deleted_at column
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hygiene_standards_vendors', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
