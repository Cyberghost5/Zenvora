<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('reference', 40)->unique();

            // What happened, in business terms.
            $table->string('type', 40);

            $table->enum('direction', ['credit', 'debit']);
            $table->enum('bucket', ['deposit', 'withdrawable', 'locked']);

            $table->bigInteger('amount');

            // Snapshot of the affected bucket either side of the write. Lets you
            // replay the ledger and prove a balance was never silently altered.
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');

            $table->string('description')->nullable();
            $table->nullableMorphs('related');
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
