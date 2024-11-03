<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_cross_sells_type', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Insert initial data
        DB::table('product_cross_sells_type')->insert([
            ['type' => 'Cross sell', 'description' => null, 'created_at' => '2022-03-21 21:18:33', 'updated_at' => '2022-03-21 21:18:33'],
            ['type' => 'Up sells', 'description' => null, 'created_at' => '2022-03-21 21:18:33', 'updated_at' => '2022-03-21 21:18:33'],
            ['type' => 'Accessories', 'description' => null, 'created_at' => '2022-03-21 21:18:33', 'updated_at' => '2022-03-21 21:18:33'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('product_cross_sells_type');
    }
};
