<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {

        Schema::create('postals', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['normal', 'fast']);
            $table->boolean('status')->default(true);
            $table->string('title');
            $table->string('name');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed initial data
        $venueId = 58; // Assuming the venue ID is 58 based on the error message

        DB::table('postals')->insert([
            [
                'type' => 'normal',
                'status' => true,
                'title' => 'Unspecified',
                'name' => 'Unspecified',
                'logo' => null,
                'description' => null,
                'venue_id' => $venueId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'normal',
                'status' => true,
                'title' => 'Ged Normale',
                'name' => 'Global Express Delivery',
                'logo' => 'postals_1655937771.png',
                'description' => null,
                'venue_id' => $venueId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'fast',
                'status' => true,
                'title' => 'Ged Express',
                'name' => 'Global Express Delivery',
                'logo' => 'postals_1655938603.png',
                'description' => null,
                'venue_id' => $venueId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('postals');
    }
};
