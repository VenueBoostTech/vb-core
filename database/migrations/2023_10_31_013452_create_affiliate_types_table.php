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
        Schema::create('affiliate_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->unsignedBigInteger('affiliate_program_id');
            $table->foreign('affiliate_program_id')->references('id')->on('affiliate_programs');
            $table->timestamps();
        });

        // Get the affiliate programs by their unique codes
        $apCF = DB::table('affiliate_programs')->where('ap_unique', 'AP-CF')->first();
        $apII = DB::table('affiliate_programs')->where('ap_unique', 'AP-II')->first();
        $apBN1 = DB::table('affiliate_programs')->where('ap_unique', 'AP-BN-1')->first();
        $apBN2 = DB::table('affiliate_programs')->where('ap_unique', 'AP-BN-2')->first();

        // Insert affiliate types with their respective affiliate_program_id
        DB::table('affiliate_types')->insert([
            [
                'name' => 'Vloggers',
                'description' => 'Vloggers',
                'affiliate_program_id' => $apCF->id,
            ],
            [
                'name' => 'Bloggers',
                'description' => 'Bloggers',
                'affiliate_program_id' => $apCF->id,
            ],
            [
                'name' => 'Writers',
                'description' => 'Writers',
                'affiliate_program_id' => $apCF->id,
            ],
            [
                'name' => 'Podcasters',
                'description' => 'Podcasters',
                'affiliate_program_id' => $apCF->id,
            ],
            [
                'name' => 'Influencers',
                'description' => 'Influencers',
                'affiliate_program_id' => $apII->id,
            ],
            [
                'name' => 'Content Creators',
                'description' => 'Content Creators',
                'affiliate_program_id' => $apII->id,
            ],
            [
                'name' => 'Media Networks',
                'description' => 'Media Networks',
                'affiliate_program_id' => $apBN1->id,
            ],
            [
                'name' => 'Web Developers',
                'description' => 'Web Developers',
                'affiliate_program_id' => $apBN1->id,
            ],
            [
                'name' => 'Business Experts/Coaches',
                'description' => 'Business Experts/Coaches',
                'affiliate_program_id' => $apBN1->id,
            ],
            [
                'name' => 'Media Networks',
                'description' => 'Media Networks',
                'affiliate_program_id' => $apBN2->id,
            ],
            [
                'name' => 'Web Developers',
                'description' => 'Web Developers',
                'affiliate_program_id' => $apBN2->id,
            ],
            [
                'name' => 'Business Experts/Coaches',
                'description' => 'Business Experts/Coaches',
                'affiliate_program_id' => $apBN2->id,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('affiliate_types');
    }
};
