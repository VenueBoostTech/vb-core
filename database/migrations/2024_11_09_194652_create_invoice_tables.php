<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('client_id')->constrained('app_clients');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('service_request_id')->constrained('service_requests');
            $table->enum('status', ['draft', 'pending', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->timestamp('issue_date');
            $table->timestamp('due_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['card', 'bank_transfer', 'cash'])->nullable();
            $table->timestamp('payment_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('currency')->default('USD');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('bank_transfer_proof')->nullable();
            $table->timestamp('bank_transfer_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['venue_id', 'status']);
        });

        Schema::create('app_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_invoice_id')->constrained('app_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('app_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_invoice_id')->constrained('app_invoices')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['app_invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_invoice_payments');
        Schema::dropIfExists('app_invoice_items');
        Schema::dropIfExists('app_invoices');
    }
};
