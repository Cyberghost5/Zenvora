<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\User;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', LandingController::class)->name('home');
// Route::redirect('/', '/login')->name('home');

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

/*
|--------------------------------------------------------------------------
| Gateway webhooks
|--------------------------------------------------------------------------
| CSRF-exempt (see bootstrap/app.php) and authenticated by signature instead.
*/

Route::post('webhooks/paystack', [WebhookController::class, 'paystack'])->name('webhooks.paystack');
Route::post('webhooks/flutterwave', [WebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');

/*
|--------------------------------------------------------------------------
| Guest authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| User module
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('dashboard', User\DashboardController::class)->name('dashboard');

    // Deposits
    Route::get('deposits', [User\DepositController::class, 'index'])->name('deposits.index');
    Route::get('deposits/fund', [User\DepositController::class, 'create'])->name('deposits.create');
    Route::post('deposits', [User\DepositController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('deposits.store');

    // The gateway sends the user back here. Not throttled as aggressively,
    // because a legitimate user may retry a slow verification.
    Route::get('deposits/callback/{channel}', [User\DepositController::class, 'callback'])
        ->whereIn('channel', ['paystack', 'flutterwave'])
        ->name('deposits.callback');

    Route::get('deposits/{deposit}', [User\DepositController::class, 'show'])->name('deposits.show');

    // Plans & Investments
    Route::get('plans', [User\InvestmentController::class, 'plans'])->name('plans.index');
    Route::get('investments', [User\InvestmentController::class, 'index'])->name('investments.index');
    Route::post('investments', [User\InvestmentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('investments.store');
    Route::get('investments/{investment}', [User\InvestmentController::class, 'show'])->name('investments.show');

    // Withdrawals
    Route::get('withdrawals', [User\WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('withdrawals', [User\WithdrawalController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('withdrawals.store');

    Route::get('referrals', User\ReferralController::class)->name('referrals');
    Route::get('transactions', User\TransactionController::class)->name('transactions');

    // Profile, including the payout account details
    Route::get('profile', [User\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [User\ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [User\ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('profile', [User\ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('profile/bank-accounts', [User\ProfileController::class, 'storeBankAccount'])
        ->name('profile.bank-accounts.store');
    Route::patch('profile/bank-accounts/{account}/primary', [User\ProfileController::class, 'makePrimary'])
        ->name('profile.bank-accounts.primary');
    Route::delete('profile/bank-accounts/{account}', [User\ProfileController::class, 'destroyBankAccount'])
        ->name('profile.bank-accounts.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin module
|--------------------------------------------------------------------------
| The `admin` alias 404s for anyone who is not an administrator, rather than
| confirming the panel exists.
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // Platform rules: deposit/withdrawal limits, the withdrawal window,
    // referral rates, manual bank details.
    Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');

    // Plans
    Route::get('plans', [Admin\PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [Admin\PlanController::class, 'create'])->name('plans.create');
    Route::post('plans', [Admin\PlanController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}/edit', [Admin\PlanController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [Admin\PlanController::class, 'destroy'])->name('plans.destroy');

    // Deposits
    Route::get('deposits', [Admin\DepositController::class, 'index'])->name('deposits.index');
    Route::get('deposits/{deposit}', [Admin\DepositController::class, 'show'])->name('deposits.show');
    Route::post('deposits/{deposit}/approve', [Admin\DepositController::class, 'approve'])->name('deposits.approve');
    Route::post('deposits/{deposit}/reject', [Admin\DepositController::class, 'reject'])->name('deposits.reject');
    Route::post('deposits/{deposit}/reverify', [Admin\DepositController::class, 'reverify'])->name('deposits.reverify');

    // Withdrawals
    Route::get('withdrawals', [Admin\WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('withdrawals/{withdrawal}', [Admin\WithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::post('withdrawals/{withdrawal}/processing', [Admin\WithdrawalController::class, 'markProcessing'])->name('withdrawals.processing');
    Route::post('withdrawals/{withdrawal}/paid', [Admin\WithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
    Route::post('withdrawals/{withdrawal}/reject', [Admin\WithdrawalController::class, 'reject'])->name('withdrawals.reject');

    // Investments
    Route::get('investments', [Admin\InvestmentController::class, 'index'])->name('investments.index');
    Route::get('investments/{investment}', [Admin\InvestmentController::class, 'show'])->name('investments.show');
    Route::post('investments/{investment}/cancel', [Admin\InvestmentController::class, 'cancel'])->name('investments.cancel');

    // Users
    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/block', [Admin\UserController::class, 'block'])->name('users.block');
    Route::post('users/{user}/unblock', [Admin\UserController::class, 'unblock'])->name('users.unblock');
    Route::post('users/{user}/adjust-wallet', [Admin\UserController::class, 'adjustWallet'])->name('users.adjust-wallet');
    Route::post('users/{user}/toggle-admin', [Admin\UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::post('users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

    // Coupons
    Route::get('coupons', [Admin\CouponController::class, 'index'])->name('coupons.index');
    Route::post('coupons', [Admin\CouponController::class, 'store'])->name('coupons.store');
    Route::post('coupons/{coupon}/toggle', [Admin\CouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('coupons/{coupon}', [Admin\CouponController::class, 'destroy'])->name('coupons.destroy');

    Route::get('audit', Admin\AuditLogController::class)->name('audit');
});
