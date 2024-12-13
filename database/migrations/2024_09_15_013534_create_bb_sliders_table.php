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
        Schema::create('bb_sliders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('photo')->nullable();
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('button')->default(false);
            $table->string('text_button')->nullable();
            $table->integer('slider_order')->default(0);
            $table->boolean('status')->default(true);
            $table->integer('bybest_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        // $sliders = [
        //     [2, 'Swarowski', '/brand/swarovski?search=5', 'swarowski', 1, 'Swarowski', 5, 1, '2022-02-13 17:03:40', '2022-12-02 09:42:09', '2022-12-02 09:42:09'],
        //     [3, 'Swatch', '/brand/swatch', 'Swatch', 1, 'Swatch', 5, 1, '2022-02-13 17:22:33', '2024-08-01 13:15:13', null],
        //     [4, 'Flik flak', '/brand/flik-flak', 'Flik Flak', 0, 'dsg', 6, 1, '2022-02-13 21:17:50', '2024-09-03 09:40:29', null],
        //     [5, 'Blukids', '/brand/blukids', 'Blukids', 1, null, 92, 1, '2022-02-15 09:50:00', '2024-09-11 09:40:28', null],
        //     [6, 'Iana', '/brand/iana', 'Iana', 0, null, 7, 1, '2022-02-15 09:53:21', '2022-08-10 12:15:19', '2022-08-10 12:15:19'],
        //     [7, 'Klin', '/brand/klin', 'Klin', 0, null, 93, 1, '2022-02-15 09:54:25', '2024-09-11 09:41:02', null],
        //     [8, 'Mopita', '/brand/mopita', 'Mopita', 0, null, 91, 1, '2022-02-15 09:58:10', '2024-01-05 20:36:04', null],
        //     [9, 'Sander', '/brand/sander', 'Sander', 0, null, 9, 1, '2022-02-15 09:59:53', '2024-01-05 20:37:19', null],
        //     [10, 'Villeroy-Boch', '/brand/villeroy-boch', 'Villeroy & Boch', 0, null, 7, 1, '2022-02-15 11:57:03', '2024-04-12 16:28:47', null],
        //     [11, 'Discount', '/brand/blukids', null, 0, null, 2, 0, '2022-07-08 15:16:08', '2022-09-23 07:21:53', '2022-09-23 07:21:53'],
        //     [12, 'Discount', '/brand/swarovski', null, 0, null, 1, 0, '2022-07-08 15:16:50', '2022-09-17 07:24:01', '2022-09-17 07:24:01'],
        //     [13, 'Like by Villeroy & Boch', '/brand/like-by-villeroy-boch', 'Like by Villeroy & Boch', 0, null, 8, 0, '2022-08-10 15:56:18', '2024-01-05 20:35:41', null],
        //     [14, 'Offer', '/', null, 0, null, 1, 0, '2022-09-17 08:05:10', '2022-11-01 09:13:53', '2022-11-01 09:13:53'],
        //     [15, 'Klin Sale', '/brand/klin?search=5', null, 0, null, 94, 0, '2022-10-21 23:37:29', '2022-12-02 10:12:36', '2022-12-02 10:12:36'],
        //     [16, 'VJESHTE15', '/', null, 0, null, 2, 0, '2022-11-01 10:59:30', '2022-11-04 23:13:19', '2022-11-04 23:13:19'],
        //     [17, 'DISCOUNT', '#footer', null, 0, null, 1, 0, '2022-11-04 23:12:37', '2022-11-18 22:54:25', '2022-11-18 22:54:25'],
        //     [18, 'Villewoy', '/brand/villeroy-boch', null, 0, null, 2, 0, '2022-11-12 11:09:02', '2022-11-14 10:32:08', '2022-11-14 10:32:08'],
        //     [19, 'Villeroy', '/brand/villeroy-boch', null, 0, null, 2, 0, '2022-11-14 14:50:05', '2022-11-18 22:57:37', '2022-11-18 22:57:37'],
        //     [20, 'OFFER', '/', null, 0, null, 1, 0, '2022-11-18 16:03:52', '2022-11-21 07:50:21', '2022-11-21 07:50:21'],
        //     [21, 'Swatch', '#', null, 0, null, 2, 0, '2022-11-21 09:54:03', '2022-11-25 15:27:48', '2022-11-25 15:27:48'],
        //     [22, 'Black Friday', '/', null, 0, null, 1, 0, '2022-11-21 22:27:32', '2022-12-02 09:42:15', '2022-12-02 09:42:15'],
        //     [23, 'Villeroy & Boch Offer', '/', null, 0, null, 4, 0, '2022-11-22 23:45:34', '2022-11-29 15:15:18', '2022-11-29 15:15:18'],
        //     [24, 'Klin Discount', '/brand/klin?search=5', null, 0, null, 2, 0, '2022-12-02 10:12:12', '2022-12-02 16:21:23', '2022-12-02 16:21:23'],
        //     [25, 'Swarovski', '/brand/swarovski', null, 0, null, 2, 0, '2022-12-02 16:21:57', '2024-08-30 12:59:06', null],
        //     [26, 'Villeroy & Boch Discount', '/brand/villeroy-boch', null, 0, null, 2, 0, '2022-12-13 14:35:32', '2023-01-04 15:27:49', '2023-01-04 15:27:49'],
        // ];

        // foreach ($sliders as $slider) {
        //     DB::table('bb_sliders')->insert([
        //         'venue_id' => 36,
        //         'bybest_id' => $slider[0],
        //         'photo' => null,
        //         'title' => $slider[1],
        //         'url' => $slider[2],
        //         'description' => $slider[3],
        //         'button' => $slider[4],
        //         'text_button' => $slider[5],
        //         'slider_order' => $slider[6],
        //         'status' => $slider[7],
        //         'created_at' => $slider[8],
        //         'updated_at' => $slider[9],
        //         'deleted_at' => $slider[10],
        //     ]);
        // }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bb_sliders');
    }
};
