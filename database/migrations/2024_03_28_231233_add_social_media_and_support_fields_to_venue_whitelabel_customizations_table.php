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
            // Add LinkedIn link field
            $table->string('linkedin_link')->nullable();

            // Add Call Us Text field
            $table->string('call_us_text')->nullable();

            // Add TikTok link field
            $table->string('tiktok_link')->nullable();

            // Add Pinterest link field
            $table->string('pinterest_link')->nullable();

            // Add Support Phone field
            $table->string('support_phone')->nullable();

            // show newsletter boolean
            $table->boolean('show_newsletter')->nullable()->default(false);
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
            $table->dropColumn(['linkedin_link', 'call_us_text', 'tiktok_link', 'pinterest_link', 'support_phone', 'show_newsletter']);
        });
    }
};
