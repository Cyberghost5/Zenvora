<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();

            // Who got paid.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Whose activity generated it.
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('investment_id')->nullable()->constrained()->nullOnDelete();

            // 1 = direct referral, 2 = their referral, 3 = one further down.
            $table->unsignedTinyInteger('tier');

            // Rate snapshotted in basis points, so changing the admin setting
            // later does not rewrite history.
            $table->unsignedInteger('rate_bp');
            $table->bigInteger('amount');

            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->timestamps();

            // One commission per earner per investment per tier.
            $table->unique(['investment_id', 'user_id', 'tier'], 'ref_comm_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
