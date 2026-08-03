<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            $table->bigInteger('min_amount');
            $table->bigInteger('max_amount');

            // Percentages are stored in basis points: 1% == 100bp. Integer maths
            // all the way down, so a 2.75% daily rate is 275 and never 0.0275.
            $table->unsignedInteger('daily_roi_bp');

            $table->unsignedSmallInteger('duration_days');

            // When true the principal is returned to the withdrawable balance on
            // the maturity date, on top of the accrued daily ROI.
            $table->boolean('return_capital')->default(true);

            // Whether subscribing to this plan pays upline referral commission.
            $table->boolean('referral_eligible')->default(true);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
