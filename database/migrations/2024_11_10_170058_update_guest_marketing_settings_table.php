<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('guest_marketing_settings');

        Schema::create('guest_marketing_settings', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->unsignedInteger('guest_id')->nullable();
            $table->bigInteger('customer_id')->unsigned()->nullable();

            // Notification preferences
            $table->boolean('promotion_sms_notify')->default(true);
            $table->boolean('promotion_email_notify')->default(true);
            $table->boolean('booking_sms_notify')->default(true);
            $table->boolean('booking_email_notify')->default(true);

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('guest_id')
                ->references('id')
                ->on('guests')
                ->onDelete('cascade');

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            // New composite unique constraint
            $table->unique(['guest_id', 'customer_id'], 'guest_marketing_unique_guest_customer');
        });
    }

    public function down()
    {
        Schema::dropIfExists('guest_marketing_settings');

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

            $table->timestamps();

            // Original foreign key constraint
            $table->foreign('guest_id')
                ->references('id')
                ->on('guests')
                ->onDelete('cascade');

            // Original unique constraint
            $table->unique(['guest_id']);
        });
    }
};
