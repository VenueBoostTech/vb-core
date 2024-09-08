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
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);
            $table->timestamps();
        });

        Schema::table('cuisine_types', function (Blueprint $table) {
            $table->string('name')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('address_line1')->nullable(false);
            $table->string('address_line2')->nullable(true);
            $table->string('state')->nullable();
            $table->string('city')->nullable(false);
            $table->string('postcode')->nullable(false);
            $table->string('country')->nullable(true);
            $table->tinyInteger('active')->nullable(false)->default(1);
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['food_drinks', 'sport_entertainment', 'hotel_resort']);
            $table->string('name')->nullable(false);
            $table->text('description')->nullable(true);
            $table->float('monthly_cost');
            $table->float('yearly_cost');
            $table->string('currency',3);
            $table->tinyInteger('active')->nullable(false)->default(1);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('cuisine_type')->nullable(true)->change();
            $table->string('open_hours')->nullable(true)->change();
            $table->string('address')->nullable(true)->change();
            $table->text('amenities')->nullable(true)->change();
            $table->string('status')->nullable(false)->default('not_verified'); // not_payment_setup ,  completed

            $table->string('stripe_customer_id')->nullable(true);
            $table->string('plan_type')->nullable(true); // monthly, yearly
            $table->unsignedBigInteger('plan_id')->nullable();

            $table->unsignedBigInteger('contact_id')->nullable();

            $table->foreign('plan_id')->references('id')->on('pricing_plans')->onDelete('set null');
            $table->foreign('contact_id')->references('id')->on('contact_sales')->onDelete('set null');
        });

        Schema::create('restaurant_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('restaurants_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable(false)->default(0);
            $table->string('currency')->nullable(false)->default('usd');
            $table->string('type')->nullable();
            $table->string('category')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('restaurants_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
        });

        Schema::create('restaurant_addons', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('addons_id')->nullable();
            $table->unsignedBigInteger('restaurants_id')->nullable();
            $table->string('addon_plan_type')->nullable(true); // monthly, yearly

            $table->foreign('addons_id')
                ->references('id')
                ->on('addons')
                ->onDelete('cascade');
            $table->foreign('restaurants_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
        });

        Schema::create('restaurant_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('address_id')->nullable();
            $table->unsignedBigInteger('restaurants_id')->nullable();

            $table->foreign('address_id')
                ->references('id')
                ->on('addresses')
                ->onDelete('cascade');
            $table->foreign('restaurants_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
        });

        Schema::create('restaurant_amenities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('amenities_id')->nullable();
            $table->unsignedBigInteger('restaurants_id')->nullable();

            $table->foreign('restaurants_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
            $table->foreign('amenities_id')
                ->references('id')
                ->on('amenities')
                ->onDelete('cascade');
        });

        Schema::create('restaurant_cuisine_types', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('cuisine_types_id')->nullable();
            $table->unsignedBigInteger('restaurants_id')->nullable();

            $table->foreign('restaurants_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
            $table->foreign('cuisine_types_id')
                ->references('id')
                ->on('cuisine_types')
                ->onDelete('cascade');
        });

        Schema::create('restaurant_open_hours', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('restaurants_id')->nullable();

            $table->enum('week_day', [0, 1, 2, 3, 4, 5, 6]);
            $table->time('time_open')->nullable();
            $table->time('time_close')->nullable();
            $table->timestamps();

            $table->foreign('restaurants_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->tinyInteger('active')->nullable(false)->default(1);

            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->tinyInteger('active')->nullable(false)->default(1);
            $table->unsignedBigInteger('country_id')->nullable();

            $table->timestamps();

            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('cascade');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->tinyInteger('active')->nullable(false)->default(1);
            $table->unsignedBigInteger('states_id')->nullable();

            $table->timestamps();

            $table->foreign('states_id')
                ->references('id')
                ->on('states')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('restaurant_open_hours');
        Schema::dropIfExists('restaurant_cuisine_types');
        Schema::dropIfExists('restaurant_amenities');
        Schema::dropIfExists('restaurant_addresses');
        Schema::dropIfExists('restaurant_addons');
        Schema::dropIfExists('restaurant_transactions');

        Schema::dropIfExists('amenities');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('addons');

        Schema::table('cuisine_types', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('active');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign('restaurants_plan_id_foreign');
            $table->dropForeign('restaurants_contact_id_foreign');

            $table->dropColumn('status');
            $table->dropColumn('stripe_customer_id');
            $table->dropColumn('plan_type');
            $table->dropColumn('plan_id');
            $table->dropColumn('contact_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
        });
    }
};
