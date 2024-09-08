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
        Schema::table('hygiene_inspections', function (Blueprint $table) {
            $table->softDeletes(); // Adds the deleted_at column
            $table->unsignedBigInteger('hygiene_check_id')->nullable()->after('venue_id');
            $table->foreign('hygiene_check_id')->references('id')->on('hygiene_checks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hygiene_inspections', function (Blueprint $table) {
            $table->dropSoftDeletes(); // Removes the deleted_at column
            $table->dropForeign(['hygiene_check_id']);
            $table->dropColumn('hygiene_check_id');
        });
    }
};
