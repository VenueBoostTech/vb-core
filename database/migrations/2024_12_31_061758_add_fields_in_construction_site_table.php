<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsInConstructionSiteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // If the table doesn't exist, create it
        if (!Schema::hasTable('construction_site')) {
            Schema::create('construction_site', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->unsignedBigInteger('address_id')->nullable();
                $table->json('specifications')->nullable();
                $table->json('weather_config')->nullable();
                $table->string('site_manager')->nullable();
                $table->json('access_requirements')->nullable();
                $table->json('safety_requirements')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
                $table->foreign('address_id')->references('id')->on('addresses')->onDelete('cascade');
            });
        } else {
            // If the table exists, update it
            Schema::table('construction_site', function (Blueprint $table) {
                if (!Schema::hasColumn('construction_site', 'venue_id')) {
                    $table->unsignedBigInteger('venue_id')->nullable()->after('id');
                    $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
                }
                if (!Schema::hasColumn('construction_site', 'specifications')) {
                    $table->json('specifications')->nullable()->after('address_id');
                }
                if (!Schema::hasColumn('construction_site', 'weather_config')) {
                    $table->json('weather_config')->nullable()->after('specifications');
                }
                if (!Schema::hasColumn('construction_site', 'site_manager')) {
                    $table->string('site_manager')->nullable()->after('weather_config');
                }
                if (!Schema::hasColumn('construction_site', 'access_requirements')) {
                    $table->json('access_requirements')->nullable()->after('site_manager');
                }
                if (!Schema::hasColumn('construction_site', 'safety_requirements')) {
                    $table->json('safety_requirements')->nullable()->after('access_requirements');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('construction_site');
    }
}
