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
        Schema::table('af_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_center_id')->nullable()->after('company_id');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('af_expenses', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
    }
};
