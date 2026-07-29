<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('channel', ['paystack', 'flutterwave', 'coupon', 'manual']);
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);

            // pending          -- created, user has not completed payment
            // awaiting_review  -- manual transfer or proof uploaded, needs an admin
            // successful       -- wallet credited
            // failed/cancelled -- terminal, nothing credited
            $table->enum('status', [
                'pending',
                'awaiting_review',
                'successful',
                'failed',
                'cancelled',
            ])->default('pending');

            // Gateway identifiers. `gateway_reference` is what Paystack or
            // Flutterwave echoes back and is what we re-verify against their API
            // before crediting -- the callback's query string is never trusted.
            $table->string('gateway_reference')->nullable()->index();
            $table->json('gateway_payload')->nullable();

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            // Manual bank transfer evidence.
            $table->string('proof_path')->nullable();
            $table->string('depositor_name')->nullable();
            $table->string('paid_to_account')->nullable();
            $table->date('paid_on')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('credited_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
