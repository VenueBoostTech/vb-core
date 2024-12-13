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
        // Add index to bb_menu_type table before creating bb_main_menu table
        Schema::table('bb_menu_type', function (Blueprint $table) {
            $table->index('bybest_id');
        });

        Schema::dropIfExists('bb_main_menu');

        Schema::create('bb_main_menu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bybest_id');
            $table->unsignedBigInteger('venue_id');
            $table->index('bybest_id');
            $table->index('type_id');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->json('title');
            $table->string('photo')->nullable();
            $table->integer('order');
            $table->string('link')->nullable();
            $table->boolean('focused')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('type_id')->references('bybest_id')->on('bb_menu_type');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
        });

        // $menuItems = [
        //     [2, 2, 2, '{"en":"MEN","sq":"MESHKUJ"}', 'menues_1700147538.jpg', 1, '#', 0, '2022-02-14 20:09:06', '2023-11-16 15:12:18', null],
        //     [3, 1, null, '{"en":"BLOG"}', null, 5, 'blog', 0, '2022-02-14 20:12:01', '2022-03-30 20:40:31', '2022-02-16 09:39:18'],
        //     [4, 2, 1, '{"en":"Women","sq":"Vajza & Gra"}', 'menues_1700147559.jpg', 2, '#', 0, '2022-02-14 21:06:46', '2023-11-16 15:12:39', null],
        //     [5, 1, null, '{"en":"Women"}', null, 2, 'safsafs', 0, '2022-02-14 21:07:27', '2022-03-30 20:40:31', '2022-02-16 09:37:46'],
        //     [6, 2, null, '{"en":"Kids"}', null, 3, '/kids', 0, '2022-02-16 10:38:41', '2022-03-30 20:40:31', '2022-02-16 09:44:15'],
        //     [7, 2, 4, '{"en":"Home","sq":"Shtëpia"}', 'menues_1704968581.jpg', 4, '/home', 0, '2022-02-16 10:39:11', '2024-01-11 10:23:01', null],
        //     [8, 1, null, '{"en":"Blog"}', 'menues_1648503035.jpg', 5, 'blog/category/Blog', 0, '2022-02-16 10:39:51', '2022-04-10 15:19:39', '2022-04-10 15:19:39'],
        //     [9, 2, 5, '{"en":"Sale","sq":"Ulje"}', 'menues_1645025325.png', 7, '#', 1, '2022-02-16 10:40:10', '2022-07-14 14:38:07', null],
        //     [10, 2, 3, '{"en":"Kids","sq":"Fëmijë"}', 'menues_1700147597.jpg', 3, '#', 0, '2022-02-16 10:44:52', '2023-11-16 15:13:17', null],
        //     [11, 1, null, '{"en":"Events","sq":"Evente"}', 'menues_1648476263.jpg', 6, 'blog/category/Events', 0, '2022-03-28 14:04:23', '2022-04-10 15:19:45', '2022-04-10 15:19:45'],
        //     [12, 2, 6, '{"en":"Gifts","sq":"Dhurata"}', 'menues_1675851326.jpg', 5, '#', 0, '2022-06-16 09:13:09', '2023-11-15 14:53:57', '2023-11-15 14:53:57'],
        //     [15, 1, 7, '{"en":"Christmas"}', 'menues_1699913273.jpg', 8, 'group/7', 0, '2023-11-13 22:07:53', '2024-01-14 20:13:23', '2024-01-14 20:13:23'],
        //     [18, 1, null, '{"en":"Room Event", "sq":"Eventet"}', 'menues_1699913273.jpg', 8, 'live', 0, '2024-02-01 08:57:04', '2024-02-26 08:56:00', '2024-02-26 08:56:00'],
        //     [20, 1, 8, '{"en":"7-8 March Gifts", "sq":"Dhuratat 7-8 Mars"}', 'menues_1699913273.jpg', 8, 'group/8', 0, '2023-11-13 22:07:53', '2024-03-11 09:06:06', '2024-03-11 10:05:58'],
        //     [21, 1, 10, '{"en":"Explore Offers","sq":"Eksploroni Ofertat"}', 'menues_1699913273.jpg', 9, 'group/10', 0, '2023-11-13 22:07:53', '2024-05-24 10:10:53', null],
        // ];

        // foreach ($menuItems as $item) {
        //     $groupId = $item[2] ? DB::table('groups')->where('bybest_id', $item[2])->value('id') : null;
        //     $typeId = $item[1] ? DB::table('bb_menu_type')->where('bybest_id', $item[1])->value('id') : null;

        //     DB::table('bb_main_menu')->insert([
        //         'bybest_id' => $item[0],
        //         'venue_id' => 58, // Assuming all menu items belong to venue_id 58
        //         'type_id' => $typeId,
        //         'group_id' => $groupId,
        //         'title' => $item[3],
        //         'photo' => $item[4],
        //         'order' => $item[5],
        //         'link' => $item[6],
        //         'focused' => $item[7],
        //         'created_at' => $item[8],
        //         'updated_at' => $item[9],
        //         'deleted_at' => $item[10],
        //     ]);
        // }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bb_main_menu');
    }
};
