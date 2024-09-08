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
        Schema::table('earn_points_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('end_user_card_id')->nullable();

            // Add foreign key constraint for end_user_card_id
            $table->foreign('end_user_card_id')->references('id')->on('end_user_cards')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('earn_points_histories', function (Blueprint $table) {
            $table->dropForeign(['end_user_card_id']);
            $table->dropColumn('end_user_card_id');
        });
    }
};
