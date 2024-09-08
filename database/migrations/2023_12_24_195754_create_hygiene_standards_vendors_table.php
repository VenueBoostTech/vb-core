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
        Schema::create('hygiene_standards_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // e.g., food, cleaning, etc.
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('hygiene_rating')->nullable(); // Rating for their hygiene standards
            $table->boolean('compliance_certified')->default(false); // Whether they are certified for compliance with hygiene standards
            $table->text('certification_details')->nullable(); // Details about certifications
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('hygiene_standards_vendors');
    }
};
