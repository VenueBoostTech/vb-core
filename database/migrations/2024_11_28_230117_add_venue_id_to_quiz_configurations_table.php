<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {

        // Schema::table('quiz_configurations', function (Blueprint $table) {
        //     $table->dropForeign(['venue_id']);
        //     $table->dropColumn('venue_id');
        // });

        Schema::table('quiz_configurations', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->constrained('restaurants')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::table('quiz_configurations', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};
