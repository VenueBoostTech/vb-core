<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {

        Schema::create('postal_pricing', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2);
            $table->decimal('price_without_tax', 10, 2);
            $table->foreignId('city_id')->constrained('cities');
            $table->foreignId('postal_id')->constrained('postals');
            $table->enum('type', ['normal', 'fast']);
            $table->string('alpha_id');
            $table->string('alpha_description');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // $oldCities = [
        //     1 => ['en' => 'Elbasan', 'sq' => 'Elbasan'],
        //     2 => ['en' => 'Prishtinë', 'sq' => 'Prishtinë'],
        //     4 => ['en' => 'Tiranë', 'sq' => 'Tiranë'],
        //     5 => ['en' => 'Durrës', 'sq' => 'Durrës'],
        //     6 => ['en' => 'Vlorë', 'sq' => 'Vlorë'],
        //     7 => ['en' => 'Fier', 'sq' => 'Fier'],
        //     8 => ['en' => 'Gjirokastër', 'sq' => 'Gjirokastër'],
        //     9 => ['en' => 'Tepelenë', 'sq' => 'Tepelenë'],
        //     10 => ['en' => 'Kukës', 'sq' => 'Kukës'],
        //     11 => ['en' => 'Shkodër', 'sq' => 'Shkodër'],
        //     12 => ['en' => 'Berat', 'sq' => 'Berat'],
        //     13 => ['en' => 'Diber', 'sq' => 'Dibër'],
        //     14 => ['en' => 'Korçë', 'sq' => 'Korçë'],
        //     15 => ['en' => 'Lezhë', 'sq' => 'Lezhë'],
        //     16 => ['en' => 'Librazhd', 'sq' => 'Librazhd'],
        //     17 => ['en' => 'Pogradec', 'sq' => 'Pogradec'],
        //     18 => ['en' => 'Sarandë', 'sq' => 'Sarandë'],
        //     19 => ['en' => 'Përmet', 'sq' => 'Përmet'],
        //     20 => ['en' => 'Lushnjë', 'sq' => 'Lushnjë'],
        //     21 => ['en' => 'Mat', 'sq' => 'Mat'],
        //     22 => ['en' => 'Mirditë', 'sq' => 'Mirditë'],
        //     23 => ['en' => 'Tropojë', 'sq' => 'Tropojë'],
        //     24 => ['en' => 'Pukë', 'sq' => 'Pukë'],
        //     25 => ['en' => 'Skrapar', 'sq' => 'Skrapar'],
        //     26 => ['en' => 'Delvinë', 'sq' => 'Delvinë'],
        // ];

        // $getCityId = function($oldCityName) {
        //     $city = DB::table('cities')->where('name', $oldCityName)->first();
        //     return $city ? $city->id : null;
        // };

        // $pricingData = [
        //     ['price' => 150, 'price_without_tax' => 120.00, 'old_city_id' => 4, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0006', 'alpha_description' => 'Transport Tirane~24'],
        //     ['price' => 650, 'price_without_tax' => 541.67, 'old_city_id' => 2, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 3, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 1, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 5, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 6, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 7, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 8, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 9, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 10, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 11, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 12, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 13, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 14, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 15, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 16, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 17, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 18, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 19, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 20, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 21, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 22, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 23, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 24, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        //     ['price' => 330, 'price_without_tax' => 264.00, 'old_city_id' => 25, 'postal_id' => 2, 'type' => 'normal', 'alpha_id' => '0007', 'alpha_description' => 'Transport Rrethe~48'],
        // ];

        // foreach ($pricingData as $data) {
        //     $oldCityId = $data['old_city_id'];
        //     if (isset($oldCities[$oldCityId])) {
        //         $cityName = $oldCities[$oldCityId]['en'];
        //         $newCityId = $getCityId($cityName);

        //         if ($newCityId) {
        //             unset($data['old_city_id']);
        //             $data['city_id'] = $newCityId;
        //             DB::table('postal_pricing')->insert($data);
        //         } else {
        //             \Log::warning("Failed to map city: $cityName");
        //         }
        //     } else {
        //         \Log::warning("Old city ID not found: $oldCityId");
        //     }
        // }
    }

    public function down()
    {
        Schema::dropIfExists('postal_pricing');
    }
};
