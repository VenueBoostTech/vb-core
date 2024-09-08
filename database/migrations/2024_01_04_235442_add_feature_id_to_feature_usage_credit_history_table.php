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
        Schema::table('feature_usage_credits_history', function (Blueprint $table) {
            // Add the feature_id column
            $table->unsignedBigInteger('feature_id')->after('feature_usage_credit_id')->nullable();
            // Add the credited_by_discovery_plan_monthly column
            $table->boolean('credited_by_discovery_plan_monthly')->default(false);

            // Add the foreign key constraint
            $table->foreign('feature_id')->references('id')->on('features');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('feature_usage_credits_history', function (Blueprint $table) {
            // Drop the foreign key before dropping the column
            $table->dropForeign(['feature_id']);
            $table->dropColumn('feature_id');
            $table->dropColumn('credited_by_discovery_plan_monthly');
        });
    }
};
