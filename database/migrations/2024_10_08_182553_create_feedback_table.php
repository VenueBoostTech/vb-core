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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('sales_associate_id')->nullable();
            $table->date('visit_date');
            $table->integer('overall_satisfaction')->comment('1-10 scale');
            $table->integer('product_quality')->comment('1-10 scale');
            $table->integer('staff_knowledge')->comment('1-10 scale');
            $table->integer('staff_friendliness')->comment('1-10 scale');
            $table->integer('store_cleanliness')->comment('1-10 scale');
            $table->integer('value_for_money')->comment('1-10 scale');
            $table->boolean('found_desired_product');
            $table->text('product_feedback')->nullable();
            $table->text('service_feedback')->nullable();
            $table->text('improvement_suggestions')->nullable();
            $table->boolean('would_recommend');
            $table->string('purchase_made')->nullable();
            $table->decimal('purchase_amount', 10, 2)->nullable();
            $table->string('preferred_communication_channel');
            $table->boolean('subscribe_to_newsletter');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('physical_stores')->onDelete('cascade');
            $table->foreign('sales_associate_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('feedback');
    }
};
