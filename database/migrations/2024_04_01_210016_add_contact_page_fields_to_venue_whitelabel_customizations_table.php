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
            $table->string('contact_page_address_value')->nullable();
            $table->string('contact_page_email_value')->nullable();
            $table->string('contact_page_phone_value')->nullable();
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
            $table->dropColumn('contact_page_address_value');
            $table->dropColumn('contact_page_email_value');
            $table->dropColumn('contact_page_phone_value');
        });
    }
};
