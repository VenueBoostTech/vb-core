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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_for_self')->default(true)->after('status'); // Defaulting to true implies that by default the order is for the customer themselves

            // other person name
            $table->string('other_person_name')->nullable()->after('is_for_self');
            $table->decimal('delivery_fee', 8, 2)->default(0.00)->after('other_person_name'); // Defaulting to 0 implies that there's no delivery fee by default

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
                    $table->dropColumn('is_for_self');
                    $table->dropColumn('other_person_name');
                    $table->dropColumn('delivery_fee');
        });
    }
};
