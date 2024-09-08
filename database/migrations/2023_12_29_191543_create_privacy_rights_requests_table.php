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
        Schema::create('privacy_rights_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('privacy_request');
            $table->string('request_contact_email');
            $table->string('request_contact_phone');
            $table->string('request_contact_name');
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
        Schema::dropIfExists('privacy_rights_requests');
    }
};
