<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whitelabel_banner', function (Blueprint $table) {
            $table->id();
            $table->json('text');
            $table->string('url')->nullable();
            $table->foreignId('type_id')->constrained('whitelabel_banner_type');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->boolean('status')->default(true);
            $table->timestamp('timer')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed the table with initial data for venue ID 58
        $banners = [
            [
                'text' => json_encode(['en' => 'SHOPPING WITH STYLE AND QUALITY', 'sq' => 'SHOPPING WITH STYLE AND QUALITY']),
                'url' => '#',
                'type_id' => 1,
                'status' => true,
                'timer' => null,
            ],
            [
                'text' => json_encode(['en' => 'UP TO 55% DISCOUNT, ONLY 99 HOURS "14-17 MARCH"', 'sq' => 'BEHU GATI PER TE SHPERNDARE AROMEN E LULEVE\ud83e\udd29']),
                'url' => '#',
                'type_id' => 1,
                'status' => true,
                'timer' => '2024-03-18 00:00:00',
            ],
            [
                'text' => json_encode(['en' => 'FREE SHIPPING ON ALL ORDERS', 'sq' => 'TRANSPORT FALAS NE CDO POROSI']),
                'url' => '#',
                'type_id' => 1,
                'status' => true,
                'timer' => null,
            ],
            [
                'text' => json_encode(['en' => 'Due to high demand, delivery times are delayed by a minimum of 3-5 business days.', 'sq' => 'Për shkak fluksi, koha e dërgimit të porosive zgjat minimalisht 3-5 ditë pune.']),
                'url' => '#',
                'type_id' => 1,
                'status' => true,
                'timer' => null,
            ],
            [

                'text' => json_encode(['en' => 'BYBEST SHOP Advent Calendar: 20 - 31 December | Magical holidays with exclusive offers every day.', 'sq' => '20 - 31 Dhjetor | ByBest Shop - kalendari i ofertave p\u00ebr 12 dit\u00eb rresht, 12 dit\u00eb festa.']),
                'url' => '#',
                'type_id' => 1,
                'status' => true,
                'timer' => null,
            ],
            [
                'text' => json_encode(['en' => 'FINAL SALE | \"BLUKIDS & IANA\" 60% DISCOUNT | 05-12 AUGUST', 'sq' => 'FINAL SALE | \"BLUKIDS & IANA\" 60% DISCOUNT | 05-12 AUGUST']),
                'url' => '#',
                'type_id' => 1,
                'status' => true,
                'timer' => null,
            ],
        ];

        foreach ($banners as $banner) {
            DB::table('whitelabel_banner')->insert([
                'text' => $banner['text'],
                'url' => $banner['url'],
                'type_id' => $banner['type_id'],
                'venue_id' => 58, // Assuming venue ID 58 exists
                'status' => $banner['status'],
                'timer' => $banner['timer'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('whitelabel_banner');
    }
};
