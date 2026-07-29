<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Every amount on this table is an integer count of minor units
            // (kobo). Never store money in a float -- 0.1 + 0.2 != 0.3 and
            // rounding drift on a ledger is unrecoverable.

            // Funded money. Can be invested, cannot be withdrawn.
            $table->bigInteger('deposit_balance')->default(0);

            // ROI payouts, referral commissions and returned capital land here.
            // This is the only bucket a withdrawal can draw from.
            $table->bigInteger('withdrawable_balance')->default(0);

            // Held against a pending withdrawal so the same naira cannot be
            // requested twice while an admin is still deciding.
            $table->bigInteger('locked_balance')->default(0);

            // Lifetime running totals, for display only. The ledger in
            // wallet_transactions remains the source of truth.
            $table->bigInteger('total_deposited')->default(0);
            $table->bigInteger('total_withdrawn')->default(0);
            $table->bigInteger('total_invested')->default(0);
            $table->bigInteger('total_roi_earned')->default(0);
            $table->bigInteger('total_referral_earned')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
