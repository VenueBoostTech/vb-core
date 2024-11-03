<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('vt_detection_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('category');
            $table->json('default_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert initial activities
        DB::table('vt_detection_activities')->insert([
            // Customer Flow
            [
                'name' => 'Store Entry/Exit',
                'code' => 'store_entry_exit',
                'description' => 'Track customers entering and exiting the store',
                'category' => 'customer_flow',
                'default_config' => json_encode(['count_in' => true, 'count_out' => true]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Walking/Browsing',
                'code' => 'customer_walking',
                'description' => 'Track customer movement patterns',
                'category' => 'customer_flow',
                'default_config' => json_encode(['path_tracking' => true]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Queue Formation',
                'code' => 'queue_formation',
                'description' => 'Detect queue formation and length',
                'category' => 'customer_flow',
                'default_config' => json_encode(['min_people' => 2, 'queue_threshold' => 30]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Queue Wait Time',
                'code' => 'queue_wait_time',
                'description' => 'Monitor customer waiting time in queues',
                'category' => 'customer_flow',
                'default_config' => json_encode(['alert_threshold' => 300]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Dwelling',
                'code' => 'customer_dwelling',
                'description' => 'Detect customers stopping at specific areas',
                'category' => 'customer_behavior',
                'default_config' => json_encode(['min_duration' => 30]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'U-Turn',
                'code' => 'customer_uturn',
                'description' => 'Detect customers leaving without purchase',
                'category' => 'customer_behavior',
                'default_config' => json_encode(['detection_zone' => 'entrance']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Product Interaction',
                'code' => 'product_interaction',
                'description' => 'Monitor customer interaction with products',
                'category' => 'customer_behavior',
                'default_config' => json_encode(['interaction_time' => 5]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Cart/Basket Pickup',
                'code' => 'cart_pickup',
                'description' => 'Track cart and basket usage',
                'category' => 'customer_behavior',
                'default_config' => json_encode(['track_returns' => true]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Cart Abandonment',
                'code' => 'cart_abandonment',
                'description' => 'Detect abandoned shopping carts',
                'category' => 'store_operations',
                'default_config' => json_encode(['timeout' => 900]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Staff Interaction',
                'code' => 'staff_interaction',
                'description' => 'Monitor staff-customer interactions',
                'category' => 'service_areas',
                'default_config' => json_encode(['interaction_duration' => true]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Shelf Stock Level',
                'code' => 'shelf_stock',
                'description' => 'Monitor product availability on shelves',
                'category' => 'store_operations',
                'default_config' => json_encode(['alert_threshold' => 20]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Suspicious Movement',
                'code' => 'suspicious_movement',
                'description' => 'Detect unusual or suspicious behavior',
                'category' => 'security',
                'default_config' => json_encode(['sensitivity' => 'medium']),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);


        Schema::create('vt_venue_detection_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('detection_activity_id');
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('venue_id', 'vda_venue_fk')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');

            $table->foreign('detection_activity_id', 'vda_activity_fk')
                ->references('id')
                ->on('vt_detection_activities')
                ->onDelete('cascade');

            $table->unique(['venue_id', 'detection_activity_id'], 'venue_activity_unique');
        });

        Schema::create('vt_device_detection_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('venue_detection_activity_id');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('device_id', 'dda_device_fk')
                ->references('id')
                ->on('vt_devices')
                ->onDelete('cascade');

            $table->foreign('venue_detection_activity_id', 'dda_venue_activity_fk')
                ->references('id')
                ->on('vt_venue_detection_activities')
                ->onDelete('cascade');

            $table->unique(['device_id', 'venue_detection_activity_id'], 'device_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vt_device_detection_activities');
        Schema::dropIfExists('vt_venue_detection_activities');
        Schema::dropIfExists('vt_detection_activities');
    }
};
