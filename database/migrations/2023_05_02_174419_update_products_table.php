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
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('name');

            $table->dropForeign('products_category_id_foreign');
            $table->dropColumn('category_id');

            $table->string('title')->nullable(false)->after('id');
            $table->string('description')->nullable()->after('title');

            $table->string('image_path')->nullable()->after('title');
            $table->string('image_thumbnail_path')->nullable()->after('image_path');

            $table->tinyInteger('available')->nullable(false)->default(1);
            $table->string('order_method')->nullable()->after('title');

            $table->tinyInteger('option_selected_type')->nullable(false)->default(1);
            $table->tinyInteger('addition_selected_type')->nullable(false)->default(1);
            $table->tinyInteger('option_selected_required')->nullable(false)->default(1);
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->decimal('price', 10, 2);
            $table->tinyInteger('available')->nullable(false)->default(1);
            $table->enum('type', ['option', 'addition']);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name');

            $table->string('title')->nullable(false)->after('id');
            $table->text('description')->nullable()->after('title');
            $table->tinyInteger('available')->nullable(false)->default(1)->after('description');
        });

        Schema::create('product_category', function (Blueprint $table) {
            // add values
            $table->unsignedBigInteger('product_id')->nullable(false);
            $table->unsignedBigInteger('category_id')->nullable(false);
            $table->integer('order_product')->nullable(false)->default(0);

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');

            // add primary key
            $table->primary(['product_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories');

            $table->dropColumn('title');
            $table->dropColumn('description');
            $table->dropColumn('image_path');
            $table->dropColumn('image_thumbnail_path');

            $table->dropColumn('available');
            $table->dropColumn('order_method');

            $table->dropColumn('option_selected_type');
            $table->dropColumn('addition_selected_type');
            $table->dropColumn('option_selected_required');
        });

        Schema::dropIfExists('product_options');

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->nullable(false)->after('id');

            $table->dropColumn('title');
            $table->dropColumn('description');
            $table->dropColumn('available');
        });

        Schema::dropIfExists('product_category');
    }
};
