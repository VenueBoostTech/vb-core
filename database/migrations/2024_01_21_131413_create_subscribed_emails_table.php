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
        Schema::create('subscribed_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->boolean('unsubscribed')->default(false); // Add unsubscribed flag
            // Nullable foreign keys
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('contact_form_id')->nullable();
            $table->unsignedBigInteger('contact_sales_id')->nullable();
            $table->timestamps();

            // Foreign keys

            // Leads
            $table->foreign('lead_id')
                ->references('id')
                ->on('potential_venue_leads')
                ->onDelete('cascade');

            // Contact forms
            $table->foreign('contact_form_id')
                ->references('id')
                ->on('contact_form_submissions')
                ->onDelete('cascade');

            // Contact sales
            $table->foreign('contact_sales_id')
                ->references('id')
                ->on('contact_sales')
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
        Schema::dropIfExists('subscribed_emails');
    }
};
