<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\Investment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single writer for wallet balances.
 *
 * Nothing else in the application may touch `wallets.*_balance`. Every movement
 * goes through post() so that it is (a) inside a transaction, (b) against a
 * row-locked wallet, and (c) accompanied by a ledger row recording the balance
 * either side of the write.
 */
class WalletService
{
    /** Columns backing each logical bucket. */
    private const BUCKETS = [
        'deposit' => 'deposit_balance',
        'withdrawable' => 'withdrawable_balance',
        'locked' => 'locked_balance',
    ];

    // -----------------------------------------------------------------
    // Deposits
    // -----------------------------------------------------------------

    /**
     * Credit funded money. Lands in the deposit bucket, which can be invested
     * but not withdrawn.
     */
    public function creditDeposit(User $user, Money $amount, Model $source, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $source, $description) {
            $transaction = $this->post(
                user: $user,
                bucket: 'deposit',
                direction: 'credit',
                amount: $amount,
                type: 'deposit',
                description: $description ?? 'Wallet funded',
                related: $source,
            );

            $this->bumpTotal($user, 'total_deposited', $amount);

            return $transaction;
        });
    }

    /**
     * Credit welcome bonus to new user deposit balance.
     */
    public function creditWelcomeBonus(User $user, Money $amount): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount) {
            return $this->post(
                user: $user,
                bucket: 'deposit',
                direction: 'credit',
                amount: $amount,
                type: 'welcome_bonus',
                description: 'Welcome Bonus credited',
            );
        });
    }

    // -----------------------------------------------------------------
    // Investments
    // -----------------------------------------------------------------

    /**
     * Move principal out of the deposit bucket to fund a plan subscription.
     */
    public function debitForInvestment(User $user, Money $amount, Model $investment): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $investment) {
            $transaction = $this->post(
                user: $user,
                bucket: 'deposit',
                direction: 'debit',
                amount: $amount,
                type: 'investment',
                description: 'Investment placed',
                related: $investment,
            );

            $this->bumpTotal($user, 'total_invested', $amount);

            return $transaction;
        });
    }

    /** A daily ROI accrual. Credited straight to withdrawable. */
    public function creditRoi(User $user, Money $amount, Investment $investment, string $description): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $investment, $description) {
            $transaction = $this->post(
                user: $user,
                bucket: 'withdrawable',
                direction: 'credit',
                amount: $amount,
                type: 'roi',
                description: $description,
                related: $investment,
            );

            $this->bumpTotal($user, 'total_roi_earned', $amount);

            return $transaction;
        });
    }

    /** Principal returned at maturity, for plans with return_capital set. */
    public function creditCapitalReturn(User $user, Money $amount, Investment $investment): WalletTransaction
    {
        return DB::transaction(fn () => $this->post(
            user: $user,
            bucket: 'withdrawable',
            direction: 'credit',
            amount: $amount,
            type: 'capital_return',
            description: 'Capital returned on maturity',
            related: $investment,
        ));
    }

    // -----------------------------------------------------------------
    // Referrals
    // -----------------------------------------------------------------

    public function creditReferralCommission(User $user, Money $amount, Model $source, string $description): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $source, $description) {
            $transaction = $this->post(
                user: $user,
                bucket: 'withdrawable',
                direction: 'credit',
                amount: $amount,
                type: 'referral_commission',
                description: $description,
                related: $source,
            );

            $this->bumpTotal($user, 'total_referral_earned', $amount);

            return $transaction;
        });
    }

    // -----------------------------------------------------------------
    // Withdrawals
    // -----------------------------------------------------------------

    /**
     * Move the requested amount from withdrawable into locked while an admin
     * decides.
     *
     * Holding the funds is what stops a user from queueing five requests
     * against the same balance and being paid five times.
     */
    public function lockForWithdrawal(User $user, Money $amount, Model $withdrawal): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $withdrawal) {
            $wallet = $this->lockWallet($user);

            if ($wallet->withdrawable_balance->lessThan($amount)) {
                throw InsufficientFundsException::for('withdrawable', $amount, $wallet->withdrawable_balance);
            }

            $transaction = $this->write(
                wallet: $wallet,
                bucket: 'withdrawable',
                direction: 'debit',
                amount: $amount,
                type: 'withdrawal_lock',
                description: 'Withdrawal requested',
                related: $withdrawal,
            );

            // The matching credit into `locked`. Not a separate ledger row: the
            // user-visible event is one request, and the lock bucket is an
            // internal holding area.
            $wallet->locked_balance = $wallet->locked_balance->add($amount);
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * An admin marked the transfer as sent: drop the held funds for good.
     */
    public function settleWithdrawal(User $user, Money $amount, Model $withdrawal): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $withdrawal) {
            $wallet = $this->lockWallet($user);

            if ($wallet->locked_balance->lessThan($amount)) {
                throw InsufficientFundsException::for('locked', $amount, $wallet->locked_balance);
            }

            $before = $wallet->locked_balance;
            $after = $before->subtract($amount);

            $wallet->locked_balance = $after;
            $wallet->total_withdrawn = $wallet->total_withdrawn->add($amount);
            $wallet->save();

            return $this->record(
                wallet: $wallet,
                bucket: 'locked',
                direction: 'debit',
                amount: $amount,
                type: 'withdrawal',
                balanceBefore: $before,
                balanceAfter: $after,
                description: 'Withdrawal paid out',
                related: $withdrawal,
            );
        });
    }

    /**
     * An admin rejected the request: return the held funds to withdrawable.
     */
    public function refundWithdrawal(User $user, Money $amount, Model $withdrawal, string $reason): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $withdrawal, $reason) {
            $wallet = $this->lockWallet($user);

            // Guard against a double refund of the same request.
            if ($wallet->locked_balance->lessThan($amount)) {
                throw InsufficientFundsException::for('locked', $amount, $wallet->locked_balance);
            }

            $wallet->locked_balance = $wallet->locked_balance->subtract($amount);
            $wallet->save();

            return $this->write(
                wallet: $wallet,
                bucket: 'withdrawable',
                direction: 'credit',
                amount: $amount,
                type: 'withdrawal_refund',
                description: 'Withdrawal reversed: '.$reason,
                related: $withdrawal,
            );
        });
    }

    // -----------------------------------------------------------------
    // Manual adjustments
    // -----------------------------------------------------------------

    public function adminAdjust(
        User $user,
        Money $amount,
        string $bucket,
        string $direction,
        string $description,
        ?Model $related = null,
    ): WalletTransaction {
        return DB::transaction(fn () => $this->post(
            user: $user,
            bucket: $bucket,
            direction: $direction,
            amount: $amount,
            type: $direction === 'credit' ? 'admin_credit' : 'admin_debit',
            description: $description,
            related: $related,
        ));
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Lock the wallet row, apply the movement, write the ledger entry.
     *
     * Must be called inside a transaction.
     */
    private function post(
        User $user,
        string $bucket,
        string $direction,
        Money $amount,
        string $type,
        string $description,
        ?Model $related = null,
    ): WalletTransaction {
        return $this->write(
            wallet: $this->lockWallet($user),
            bucket: $bucket,
            direction: $direction,
            amount: $amount,
            type: $type,
            description: $description,
            related: $related,
        );
    }

    private function write(
        Wallet $wallet,
        string $bucket,
        string $direction,
        Money $amount,
        string $type,
        string $description,
        ?Model $related = null,
    ): WalletTransaction {
        $column = self::BUCKETS[$bucket] ?? throw new InvalidArgumentException("Unknown wallet bucket: {$bucket}");

        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A wallet movement must be a positive amount.');
        }

        $before = $wallet->{$column};

        $after = $direction === 'credit'
            ? $before->add($amount)
            : $before->subtract($amount);

        // A balance must never go negative. If this fires, the caller skipped
        // its own affordability check and the surrounding transaction is rolled
        // back rather than leaving a broken wallet behind.
        if ($after->isNegative()) {
            throw InsufficientFundsException::for($bucket, $amount, $before);
        }

        $wallet->{$column} = $after;
        $wallet->save();

        return $this->record(
            wallet: $wallet,
            bucket: $bucket,
            direction: $direction,
            amount: $amount,
            type: $type,
            balanceBefore: $before,
            balanceAfter: $after,
            description: $description,
            related: $related,
        );
    }

    private function record(
        Wallet $wallet,
        string $bucket,
        string $direction,
        Money $amount,
        string $type,
        Money $balanceBefore,
        Money $balanceAfter,
        string $description,
        ?Model $related = null,
    ): WalletTransaction {
        return WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'reference' => $this->newReference(),
            'type' => $type,
            'direction' => $direction,
            'bucket' => $bucket,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
        ]);
    }

    /**
     * Fetch the wallet with a SELECT ... FOR UPDATE so two concurrent requests
     * cannot both read the same starting balance and each write their own total.
     */
    private function lockWallet(User $user): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            Wallet::query()->create(['user_id' => $user->id]);

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $wallet;
    }

    private function bumpTotal(User $user, string $column, Money $amount): void
    {
        // Running totals are display-only, so an atomic increment is enough --
        // no need to re-read under lock.
        Wallet::query()
            ->where('user_id', $user->id)
            ->increment($column, $amount->minor);
    }

    private function newReference(): string
    {
        return 'TXN-'.strtoupper(bin2hex(random_bytes(7)));
    }
}
