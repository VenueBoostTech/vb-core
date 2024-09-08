<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('returns_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_order_id')->constrained('orders');
            $table->foreignId('customer_id')->constrained('customers');  // Keep this for direct customer reference
            $table->enum('type', ['return', 'exchange']);
            $table->timestamp('date_processed')->useCurrent();
            $table->foreignId('processed_by')->constrained('users');  // Now referencing users table for staff
            $table->decimal('total_amount', 10, 2);
            $table->enum('refund_method', ['original_payment', 'store_credit', 'gift_card']);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('vendor_id')->constrained('restaurants');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('returns_exchanges');
    }
};
