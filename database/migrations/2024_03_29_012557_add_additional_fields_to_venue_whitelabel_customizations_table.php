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
        Schema::table('venue_whitelabel_customizations', function (Blueprint $table) {
            // Add Call Us Text field
            $table->string('social_media_label_text')->default('Follow our socials');
            $table->string('subscribe_label_text')->default('Sign up for news and updates');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venue_whitelabel_customizations', function (Blueprint $table) {
            $table->dropColumn(['social_media_label_text', 'subscribe_label_text']);
        });
    }
};
