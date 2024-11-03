<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_stock_status', function (Blueprint $table) {
            $table->id();
            $table->json('status');
            $table->json('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Insert initial data
        $statuses = [
            [
                'status' => json_encode(['en' => 'Ne gjendje']),
                'description' => json_encode(['en' => null]),
                'created_at' => '2022-02-11 14:02:08',
                'updated_at' => '2022-03-30 20:59:06',
            ],
            [
                'status' => json_encode(['en' => 'Ska gjendje']),
                'description' => json_encode(['en' => null]),
                'created_at' => '2022-02-11 21:48:47',
                'updated_at' => '2022-03-30 20:59:06',
            ],
            [
                'status' => json_encode(['en' => 'Vjen se shpejti']),
                'description' => json_encode(['en' => null]),
                'created_at' => '2022-02-11 21:48:47',
                'updated_at' => '2022-03-30 20:59:06',
            ],
            [
                'status' => json_encode(['en' => 'Nuk vjen me']),
                'description' => json_encode(['en' => null]),
                'created_at' => '2022-02-11 21:48:47',
                'updated_at' => '2022-03-30 20:59:06',
            ],
        ];

        DB::table('product_stock_status')->insert($statuses);
    }

    public function down()
    {
        Schema::dropIfExists('product_stock_status');
    }
};
