<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {

        Schema::create('guest_marketing_settings', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->unsignedInteger('guest_id');

            // Notification preferences
            $table->boolean('promotion_sms_notify')->default(true);
            $table->boolean('promotion_email_notify')->default(true);
            $table->boolean('booking_sms_notify')->default(true);
            $table->boolean('booking_email_notify')->default(true);

            // Add foreign key constraint for guest_id
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');


            $table->timestamps();

            // Ensure each guest only has one marketing settings record
            $table->unique(['guest_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('guest_marketing_settings');
    }
};
