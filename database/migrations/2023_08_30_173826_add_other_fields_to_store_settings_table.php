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
        Schema::table('store_settings', function (Blueprint $table) {
            $table->json('tags')->nullable();
            $table->string('neighborhood')->nullable();
            $table->text('description')->nullable();
            $table->json('payment_options')->nullable();
            $table->string('additional')->nullable();
            $table->string('main_tag')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('tags');
            $table->dropColumn('neighborhood');
            $table->dropColumn('description');
            $table->dropColumn('payment_options');
            $table->dropColumn('additional');
            $table->dropColumn('main_tag');
        });
    }
};
