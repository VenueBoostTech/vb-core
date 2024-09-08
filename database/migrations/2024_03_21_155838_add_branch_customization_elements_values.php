<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('industry_brand_customization_elements', function (Blueprint $table) {
            $table->unsignedBigInteger('industry_id')->nullable()->after('industry');
            $table->string('default_name')->nullable();
            $table->string('description')->nullable();

            $table->foreign('industry_id')->references('id')->on('venue_industries')->onDelete('set null');
        });

        $elements = [
            ['industry_id' => 1, 'industry' => 'food', 'element_name' => 'BookNowButton', 'default_name' => 'Book Now', 'description' => "The 'Book Now' button is used for booking."],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'BookNowButton', 'default_name' => 'Book Now', 'description' => "The 'Book Now' button is used for booking."],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'Footer', 'default_name' => 'Footer', 'description' => 'Update Footer background color'],
            ['industry_id' => 1, 'industry' => 'food', 'element_name' => 'Footer', 'default_name' => 'Footer', 'description' => 'Update Footer background color'],
            ['industry_id' => 2, 'industry' => 'sport_entertainment', 'element_name' => 'Footer', 'default_name' => 'Footer', 'description' => 'Update Footer background color'],
            ['industry_id' => 3, 'industry' => 'accommodation', 'element_name' => 'Footer', 'default_name' => 'Footer', 'description' => 'Update Footer background color'],
        ];

        DB::table('industry_brand_customization_elements')->insert($elements);
 
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'retail')->where('element_name', '=', 'CartContinueButton')->update(['industry_id' => 4, 'default_name' => 'Continue', 'description' => 'Update Cart Continue button']);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'retail')->where('element_name', '=', 'CartPlusButton')->update(['industry_id' => 4, 'default_name' => 'Continue', 'description' => 'Update Cart Plus button']);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'retail')->where('element_name', '=', 'CartOrderButton')->update(['industry_id' => 4, 'default_name' => 'Continue', 'description' => 'Update Cart Order button']);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'retail')->where('element_name', '=', 'AllButtons')->update(['industry_id' => 4, 'default_name' => 'All Buttons', 'description' => "The 'AllButtons' option, when activated, ensures uniformity in your platform's visual presentation. It applies a consistent text color and button color across all buttons, overriding any previous individual button customizations."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'food')->where('element_name', '=', 'FindATimeButton')->update(['industry_id' => 1, 'default_name' => 'Find a time', 'description' => "The 'Find A Time' button streamlines the reservation process for your guests. When they look to reserve a table, clicking this button shows them the available time slots on their chosen date, making it easy and quick to reserve a table at your venue."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'food')->where('element_name', '=', 'AllButtons')->update(['industry_id' => 1, 'default_name' => 'All Buttons', 'description' => "The 'AllButtons' option, when activated, ensures uniformity in your platform's visual presentation. It applies a consistent text color and button color across all buttons, overriding any previous individual button customizations."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'sport_entertainment')->where('element_name', '=', 'BookNowButton')->update(['industry_id' => 2, 'default_name' => 'Book Now', 'description' => "The 'Book Now' button is used for booking."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'sport_entertainment')->where('element_name', '=', 'AllButtons')->update(['industry_id' => 2, 'default_name' => 'All Buttons', 'description' => "The 'AllButtons' option, when activated, ensures uniformity in your platform's visual presentation. It applies a consistent text color and button color across all buttons, overriding any previous individual button customizations."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'accommodation')->where('element_name', '=', 'AllButtons')->update(['industry_id' => 3, 'default_name' => 'All Buttons', 'description' => "The 'AllButtons' option, when activated, ensures uniformity in your platform's visual presentation. It applies a consistent text color and button color across all buttons, overriding any previous individual button customizations."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'accommodation')->where('element_name', '=', 'BookNowButton')->update(['industry_id' => 3, 'default_name' => 'Book Now', 'description' => "The 'Book Now' button is used for booking."]);
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'accommodation')->where('element_name', '=', 'CheckAvailabilityButton')->update(['industry_id' => 3, 'default_name' => 'Check Availability', 'description' => "The 'Check Availability' button is used for check availability."]);

        DB::statement("ALTER TABLE venue_brand_profile_customizations MODIFY COLUMN element_type ENUM('button', 'paragraph', 'h1', 'h2', 'h3', 'div') NOT NULL DEFAULT 'button'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('industry_brand_customization_elements', function (Blueprint $table) {
            $table->dropForeign(['industry_id']);

            $table->dropColumn('industry_id');
            $table->dropColumn('default_name');
            $table->dropColumn('description');
        });

        DB::table('industry_brand_customization_elements')->where('industry', '=', 'food')->where('element_name', '=', 'BookNowButton')->delete();
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'retail')->where('element_name', '=', 'BookNowButton')->delete();
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'retail')->where('element_name', '=', 'Footer')->delete();
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'food')->where('element_name', '=', 'Footer')->delete();
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'sport_entertainment')->where('element_name', '=', 'Footer')->delete();
        DB::table('industry_brand_customization_elements')->where('industry', '=', 'accommodation')->where('element_name', '=', 'Footer')->delete();
        
        DB::statement("ALTER TABLE venue_brand_profile_customizations MODIFY COLUMN element_type ENUM('button', 'paragraph', 'h1', 'h2', 'h3') NOT NULL DEFAULT 'button'");
    }
};
