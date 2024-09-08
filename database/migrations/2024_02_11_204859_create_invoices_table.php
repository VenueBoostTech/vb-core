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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('address_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->dateTime('date_issued');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['Pending', 'Paid', 'Cancelled']);
            $table->string('payment_method');
            $table->timestamps();


            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('venue_id')->references('id')->on('restaurants');
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
