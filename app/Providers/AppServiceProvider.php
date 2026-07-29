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
            if (\Illuminate\Support\Facades\Schema::hasTable('wallet_transactions')) {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type VARCHAR(40) NOT NULL");
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('deposits')) {
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
