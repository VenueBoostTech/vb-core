<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        Schema::create('venue_user_supabase_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('user_id');
            $table->string('supabase_id');
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Use a shorter name for the unique index
            $table->unique(['venue_id', 'user_id', 'supabase_id'], 'venue_user_supabase_unique');
        });

        // DB::table('venue_user_supabase_connections')->insert([
        //     'venue_id' => 85,
        //     'user_id' => 213,  // Changed to integer
        //     'supabase_id' => '7b3629fd-de91-467c-9e88-7585613393db',
        //     'created_at' => now(),
        //     'updated_at' => now()
        // ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_user_supabase_connections');
    }
};
