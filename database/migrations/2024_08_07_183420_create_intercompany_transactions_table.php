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
        Schema::create('intercompany_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_company_id')->constrained('companies');
            $table->foreignId('to_company_id')->constrained('companies');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('intercompany_transactions');
    }
};
