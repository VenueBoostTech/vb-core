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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('subscription_id')->nullable()->after('plan_type');
            $table->string('plan_id')->nullable()->after('plan_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('plan_type');
            $table->foreign('plan_id')->references('id')->on('pricing_plans')->onDelete('set null');

            $table->dropColumn('subscription_id');
        });
    }
};
