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
        DB::table('blogs')
            ->where('id', 191)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-34.png']); // Ungerboeck

        DB::table('blogs')
            ->where('id', 192)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-1-1.png']); // EventBooking

        DB::table('blogs')
            ->where('id', 193)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-2-1.png']);// MINDBODY

        DB::table('blogs')
            ->where('id', 194)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-3-1.png']); // Rezdy

        DB::table('blogs')
            ->where('id', 195)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-4-1.png']); // Oracle

        DB::table('blogs')
            ->where('id', 196)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-5-1.png']); // PerfectVenue

        DB::table('blogs')
            ->where('id', 197)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-6-1.png']); // HoneyBook

        DB::table('blogs')
            ->where('id', 198)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-7-1.png']); // Eventbrite

        DB::table('blogs')
            ->where('id', 199)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-8-2.png']); //Gather

        DB::table('blogs')
            ->where('id', 200)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-9-1.png']); // Priava

        DB::table('blogs')
            ->where('id', 201)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-10-2.png']); // Aventri

        DB::table('blogs')
            ->where('id', 202)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/Untitled-design-11-1.png']);// Ivvy

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
};
