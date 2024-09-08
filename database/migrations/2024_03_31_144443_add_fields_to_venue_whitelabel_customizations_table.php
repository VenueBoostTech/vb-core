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
            $table->string('contact_page_main_title_string')->nullable();
            $table->string('contact_page_toplabel_string')->default('Send us a message, and one of our representatives will get back to you soon to help with any inquiries or support needs.')->nullable();
            $table->string('contact_page_address_string')->default('Address')->nullable();
            $table->string('contact_page_phone_string')->default('Phone')->nullable();
            $table->string('contact_page_email_string')->default('Email')->nullable();
            $table->string('contact_page_open_hours_string')->default('Opening Hours')->nullable();
            $table->string('contact_page_form_subtitle_string')->nullable();
            $table->string('contact_page_form_submit_btn_txt')->default('Submit')->nullable();
            $table->string('contact_page_fullname_label_string')->default('Full Name')->nullable();
            $table->string('contact_page_phone_number_label_string')->default('Phone Number')->nullable();
            $table->boolean('contact_page_phone_number_show')->default(true);
            $table->string('contact_page_email_label_string')->default('Email')->nullable();
            $table->string('contact_page_subject_label_string')->default('Subject')->nullable();
            $table->boolean('contact_page_subject_show')->default(true);
            $table->string('contact_page_content_label_string')->default('Content')->nullable();
            $table->boolean('contact_page_content_show')->default(true);
            $table->boolean('contact_page_enable')->default(false);
            $table->boolean('contact_page_opening_hours_enable')->default(false);
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
            // drop columns
            $table->dropColumn('contact_page_main_title_string');
            $table->dropColumn('contact_page_toplabel_string');
            $table->dropColumn('contact_page_address_string');
            $table->dropColumn('contact_page_phone_string');
            $table->dropColumn('contact_page_email_string');
            $table->dropColumn('contact_page_open_hours_string');
            $table->dropColumn('contact_page_form_subtitle_string');
            $table->dropColumn('contact_page_form_submit_btn_txt');
            $table->dropColumn('contact_page_fullname_label_string');
            $table->dropColumn('contact_page_phone_number_label_string');
            $table->dropColumn('contact_page_phone_number_show');
            $table->dropColumn('contact_page_email_label_string');
            $table->dropColumn('contact_page_subject_label_string');
            $table->dropColumn('contact_page_subject_show');
            $table->dropColumn('contact_page_content_label_string');
            $table->dropColumn('contact_page_content_show');
            $table->dropColumn('contact_page_enable');
            $table->dropColumn('contact_page_opening_hours_enable');
        });
    }
};
