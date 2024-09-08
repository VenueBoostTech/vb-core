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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('salary', 8, 2);
            $table->decimal('bonus', 8, 2)->nullable();
            $table->decimal('deductions', 8, 2)->nullable();
            $table->decimal('taxes', 8, 2)->nullable();
            $table->decimal('net_pay', 8, 2)->nullable();
            $table->decimal('overtime_pay', 8, 2)->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payrolls');
    }
};
