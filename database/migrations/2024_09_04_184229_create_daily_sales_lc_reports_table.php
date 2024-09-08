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
        Schema::create('daily_sales_lc_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->date('report_date');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('daily_sales', 10, 2);
            $table->integer('tickets');
            $table->integer('quantity');
            $table->decimal('ppt', 8, 2);
            $table->decimal('vpt', 10, 2);
            $table->decimal('ppp', 10, 2);
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');

            $table->unique(['brand_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('daily_sales_lc_reports');
    }
};
