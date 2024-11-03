<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('app_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['company', 'homeowner']);
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('address_id')->nullable()->constrained('addresses');
            $table->foreignId('venue_id')->nullable()->constrained('restaurants'); // Assuming in the future they can be venueboost client
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_clients');
    }
};
