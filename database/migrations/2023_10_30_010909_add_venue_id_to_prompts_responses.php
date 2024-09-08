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

        Schema::table('prompts_responses', function (Blueprint $table) {
            // Check if the 'venue_id' column doesn't exist before adding it
            if (!Schema::hasColumn('prompts_responses', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable();
                // Add the foreign key constraint for venue_id
                $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prompts_responses', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};
