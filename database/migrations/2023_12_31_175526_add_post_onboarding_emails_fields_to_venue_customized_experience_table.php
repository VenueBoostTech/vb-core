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
        Schema::table('venue_customized_experience', function (Blueprint $table) {
            $table->dateTime('post_onboarding_survey_email_sent_at')->nullable()->after('biggest_additional_change');
            $table->dateTime('post_onboarding_welcome_email_sent_at')->nullable()->after('post_onboarding_survey_email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venue_customized_experience', function (Blueprint $table) {
            $table->dropColumn('post_onboarding_survey_email_sent_at');
            $table->dropColumn('post_onboarding_welcome_email_sent_at');
        });
    }
};
