<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL");
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('wallet_transactions')) {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type VARCHAR(40) NOT NULL");
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('deposits')) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('deposits', 'depositor_account')) {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE deposits ADD COLUMN depositor_account VARCHAR(50) NULL AFTER depositor_name");
                }
                \App\Models\Deposit::query()
                    ->whereIn('channel', ['paystack', 'flutterwave'])
                    ->whereIn('status', ['pending', 'failed'])
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Already executed or harmless
        }
    }
}
