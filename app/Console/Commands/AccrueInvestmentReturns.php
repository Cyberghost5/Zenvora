<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\InvestmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pays one day of ROI to every active investment.
 *
 * Safe to run more than once a day: each investment records the date it was last
 * accrued, and a unique index on (investment_id, accrual_date, kind) rejects a
 * duplicate even if two copies run at the same moment. Missing a day is not
 * recoverable automatically -- use --date to backfill deliberately.
 */
class AccrueInvestmentReturns extends Command
{
    protected $signature = 'investments:accrue
                            {--date= : Accrue for a specific date (Y-m-d), for backfilling a missed run}
                            {--dry-run : Report what would be paid without writing anything}';

    protected $description = 'Credit the daily return on every active investment';

    public function handle(InvestmentService $investments): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        if ($date->isFuture()) {
            $this->error('Refusing to accrue for a future date.');

            return self::FAILURE;
        }

        $this->info("Accruing investment returns for {$date->toDateString()}");

        $due = Investment::query()
            ->active()
            ->where('started_on', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('last_accrued_on')
                ->orWhere('last_accrued_on', '<', $date->toDateString()))
            ->count();

        if ($due === 0) {
            $this->line('Nothing due. Every active investment is already accrued for this date.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$due} investment(s) would be accrued. No changes made.");

            return self::SUCCESS;
        }

        $stats = $investments->accrueAllDue($date);

        $this->newLine();
        $this->table(
            ['Examined', 'Paid', 'Matured'],
            [[$stats['processed'], $stats['paid'], $stats['completed']]],
        );

        // Anything examined but not paid was skipped by an idempotency guard,
        // which is worth surfacing rather than hiding in a success message.
        $skipped = $stats['processed'] - $stats['paid'];

        if ($skipped > 0) {
            $this->warn("{$skipped} investment(s) were skipped (already accrued, or matured mid-run).");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
