<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->unsignedBigInteger('venue_id');

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');

        });
    }

    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};
