<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('members')) {
            Schema::create('members', function (Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone_number');
                $table->date('birthday')->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();

                // Foreign key to the brands table
                $table->unsignedBigInteger('preferred_brand_id')->nullable();
                $table->foreign('preferred_brand_id')->references('id')->on('brands')->onDelete('set null');

                // Accepting terms and conditions field
                $table->boolean('accept_terms')->default(false);

                // Foreign key to the restaurants table
                $table->unsignedBigInteger('venue_id');
                $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');

                // Foreign key to the users table (nullable)
                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

                // New field: source of member registration
                $table->enum('registration_source', ['landing_page', 'manually_registered', 'from_my_club'])->default('landing_page');

                // New field: utm code for marketing purposes
                $table->string('utm_code')->nullable();

                // New field: accepted date for membership
                $table->date('accepted_at')->nullable();

                $table->boolean('is_rejected')->default(false); // New column to track rejection status
                $table->text('rejection_reason')->nullable(); // Optional column to store rejection reason
                $table->timestamp('rejected_at')->nullable(); // Timestamp for when the request was rejected

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('members');
    }
}
