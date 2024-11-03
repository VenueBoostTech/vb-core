<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bb_menu_children_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bybest_id');
            $table->unsignedBigInteger('venue_id');
            $table->json('type');
            $table->json('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        $types = [
            [1, '{"en":"Kategori"}', '{"en":null}', '2022-02-14 20:14:05', '2022-03-30 20:42:22', null],
            [2, '{"en":"Brand"}', '{"en":null}', '2022-02-14 20:14:05', '2022-03-30 20:42:22', null],
            [3, '{"en":"Koleksion"}', '{"en":null}', '2022-02-14 20:14:05', '2022-03-30 20:42:22', null],
        ];

        foreach ($types as $type) {
            DB::table('bb_menu_children_type')->insert([
                'bybest_id' => $type[0],
                'venue_id' => 58, // Assuming all types belong to venue_id 58
                'type' => $type[1],
                'description' => $type[2],
                'created_at' => $type[3],
                'updated_at' => $type[4],
                'deleted_at' => $type[5],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bb_menu_children_type');
    }
};
