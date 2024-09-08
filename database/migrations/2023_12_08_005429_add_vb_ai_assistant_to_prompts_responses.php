<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            DB::statement("ALTER TABLE prompts_responses MODIFY COLUMN `for` ENUM('web_general', 'web_pre_onboarding', 'admin', 'vb-ai-assistant') NOT NULL");
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
            DB::statement("ALTER TABLE prompts_responses MODIFY COLUMN `for` ENUM('web_general', 'web_pre_onboarding', 'admin') NOT NULL");
        });
    }
};
