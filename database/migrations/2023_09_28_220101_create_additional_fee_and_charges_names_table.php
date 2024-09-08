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
        Schema::create('additional_fee_and_charges_names', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default fee names
        DB::table('additional_fee_and_charges_names')->insert([
            ['name' => 'Linen fees', 'description' => 'For linens and towels'],
            ['name' => 'Management fees', 'description' => 'For general admin and business expenses'],
            ['name' => 'Community fees', 'description' => 'For building, community, and related fees'],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('additional_fee_and_charges_names');
    }
};
