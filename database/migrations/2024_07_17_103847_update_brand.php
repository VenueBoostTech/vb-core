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
        Schema::table('brands', function (Blueprint $table) {
            $table->string('url')->nullable()->after('description');
            $table->integer('total_stock')->default(0)->after('url');
            $table->string('white_logo_path')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('url');
            $table->dropColumn('total_stock');
            $table->dropColumn('white_logo_path');
        });
    }
};
