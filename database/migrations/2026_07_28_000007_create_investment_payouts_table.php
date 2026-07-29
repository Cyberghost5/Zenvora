<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('day_index');
            $table->bigInteger('amount');
            $table->date('accrual_date');

            $table->enum('kind', ['roi', 'capital_return'])->default('roi');

            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->timestamps();

            // The idempotency guard. Running the accrual command twice in one day
            // hits this unique index and the second write is discarded rather
            // than paying the user twice.
            $table->unique(['investment_id', 'accrual_date', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_payouts');
    }
};
