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
        Schema::create('country_payment_provider', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('payment_provider');
            $table->boolean('active')->default(false);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'payment_provider']);
        });

        // Insert records into the pivot table
        $paymentProviders = [
            ['provider' => 'Stripe', 'countries' => ['US']],
            ['provider' => 'Paddle', 'countries' => ['AU', 'IE', 'GB']],
            ['provider' => 'PayPro Global', 'countries' => ['CA', 'NZ']],
        ];

        foreach ($paymentProviders as $provider) {
            foreach ($provider['countries'] as $countryCode) {
                $countryId = DB::table('countries')->where('code', $countryCode)->value('id');

                if ($countryId) {
                    // Insert only if the record doesn't already exist
                    DB::table('country_payment_provider')->updateOrInsert([
                        'country_id' => $countryId,
                        'payment_provider' => $provider['provider'],
                    ], [
                        'active' => 1,
                        'start_time' => now(),
                        'end_time' => now()->addMonths(12),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('country_payment_provider');
    }
};
