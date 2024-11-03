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
        Schema::create('app_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('vb_app_id')->constrained('vb_apps')->onDelete('cascade');
            $table->string('status');
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->decimal('price_per_user', 8, 2);
            $table->decimal('initial_fee_paid', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_subscriptions');
    }
};
