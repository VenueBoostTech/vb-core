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
            $table->enum('for', ['web_general', 'web_pre_onboarding', 'admin'])->nullable(false);
            $table->enum('industry', ['Food', 'Sport & Entertainment', 'Accommodation', 'Retail'])->nullable();
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
            $table->dropColumn('for');
            $table->dropColumn('industry');
        });
    }
};
