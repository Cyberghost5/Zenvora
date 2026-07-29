<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Kept on delete-restrict: a plan that has been invested in must not
            // vanish, or historical contracts lose their provenance.
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            $table->bigInteger('principal');

            // The plan's terms are copied onto the contract at subscription time.
            // If an admin later edits the plan, live investments keep the terms
            // the user actually agreed to.
            $table->unsignedInteger('daily_roi_bp');
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('return_capital');
            $table->bigInteger('daily_payout');
            $table->bigInteger('total_expected_roi');

            $table->unsignedSmallInteger('days_paid')->default(0);
            $table->bigInteger('total_roi_paid')->default(0);

            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');

            $table->date('started_on');
            $table->date('matures_on');

            // Guards the accrual command: a day is only ever paid once.
            $table->date('last_accrued_on')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'matures_on']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
