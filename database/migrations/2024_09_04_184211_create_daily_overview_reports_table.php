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

        Schema::create('daily_overview_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('report_date');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('current_year_sales', 10, 2);
            $table->decimal('last_year_sales', 10, 2);
            $table->decimal('index', 8, 4)->nullable();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('physical_stores')->onDelete('cascade');

            $table->unique(['brand_id', 'store_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('daily_overview_reports');
    }
};
