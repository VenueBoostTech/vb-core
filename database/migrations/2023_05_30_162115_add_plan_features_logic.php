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
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('link')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);
        });

        Schema::create('sub_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feature_id')->nullable(false);
            $table->string('name')->nullable(false);
            $table->string('link')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);

            $table->foreign('feature_id')->references('id')->on('features')->onDelete('cascade');
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('feature_id')->nullable();

            $table->foreign('plan_id')
                ->references('id')
                ->on('pricing_plans')
                ->onDelete('cascade');
            $table->foreign('feature_id')
                ->references('id')
                ->on('features')
                ->onDelete('cascade');
        });

        Schema::create('plan_sub_features', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('sub_feature_id')->nullable();

            $table->foreign('plan_id')
                ->references('id')
                ->on('pricing_plans')
                ->onDelete('cascade');
            $table->foreign('sub_feature_id')
                ->references('id')
                ->on('sub_features')
                ->onDelete('cascade');
        });

        Schema::create('addon_features', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('link')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);
        });

        Schema::create('addon_sub_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('addon_feature_id')->nullable(false);
            $table->string('name')->nullable(false);
            $table->string('link')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);

            $table->foreign('addon_feature_id')->references('id')->on('addon_features')->onDelete('cascade');
        });

        Schema::create('addon_feature_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('addon_id')->nullable();
            $table->unsignedBigInteger('addon_feature_id')->nullable();

            $table->foreign('addon_id')
                ->references('id')
                ->on('addons')
                ->onDelete('cascade');
            $table->foreign('addon_feature_id')
                ->references('id')
                ->on('addon_features')
                ->onDelete('cascade');
        });

        Schema::create('addon_sub_feature_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('addon_id')->nullable();
            $table->unsignedBigInteger('addon_sub_feature_id')->nullable();

            $table->foreign('addon_id')
                ->references('id')
                ->on('addons')
                ->onDelete('cascade');
            $table->foreign('addon_sub_feature_id')
                ->references('id')
                ->on('addon_sub_features')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_sub_features');
        Schema::dropIfExists('sub_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('addon_features');
        Schema::dropIfExists('addon_sub_features');
        Schema::dropIfExists('addon_sub_feature_connections');
        Schema::dropIfExists('addon_feature_connections');
    }
};
