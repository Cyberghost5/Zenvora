<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Withdrawal requests, subject to the admin's window and limits.
 *
 * A request immediately moves funds into the wallet's locked bucket, so the same
 * balance cannot be requested twice while an admin is still deciding.
 */
class WithdrawalService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WithdrawalWindow $window,
        private readonly WalletService $wallet,
    ) {}

    public function minimum(): Money
    {
        return $this->settings->money('withdrawal_min', 100_000);
    }

    public function maximum(): Money
    {
        return $this->settings->money('withdrawal_max', 100_000_000);
    }

    public function feeFor(Money $amount): Money
    {
        return $amount->percentageBp($this->settings->integer('withdrawal_fee_bp', 0));
    }

    /**
     * Create a request and lock the funds.
     *
     * Every rule is re-checked here rather than trusted from the form, because
     * this method is the only path money takes on its way out.
     *
     * @throws RuntimeException|InsufficientFundsException
     */
    public function request(User $user, Money $amount, BankAccount $account): Withdrawal
    {
        if ($user->is_blocked) {
            throw new RuntimeException('Your account is suspended. Contact support.');
        }

        $requireInvestment = $this->settings->boolean('withdrawal_require_investment', true);
        if ($requireInvestment && ! $user->investments()->exists()) {
            throw new RuntimeException('You must activate the VIP Trial plan before you can make a withdrawal.');
        }

        if ($reason = $this->window->closedReason()) {
            throw new RuntimeException($reason);
        }

        if ($amount->lessThan($this->minimum())) {
            throw new RuntimeException(sprintf(
                'The minimum withdrawal is %s.',
                $this->minimum()->formatWithSymbol(),
            ));
        }

        if ($amount->greaterThan($this->maximum())) {
            throw new RuntimeException(sprintf(
                'The maximum withdrawal is %s.',
                $this->maximum()->formatWithSymbol(),
            ));
        }

        if ($account->user_id !== $user->id) {
            throw new RuntimeException('That payout account does not belong to you.');
        }

        $wallet = $user->ensureWallet();

        if ($wallet->withdrawable_balance->lessThan($amount)) {
            throw InsufficientFundsException::for('withdrawable', $amount, $wallet->withdrawable_balance);
        }

        $fee = $this->feeFor($amount);

        return DB::transaction(function () use ($user, $amount, $account, $fee) {
            $withdrawal = Withdrawal::query()->create([
                'reference' => Withdrawal::newReference(),
                'user_id' => $user->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $amount->subtract($fee),

                // Destination snapshotted, so editing the profile later does not
                // rewrite where this payment was sent.
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_name' => $account->account_name,
            ]);

            $this->wallet->lockForWithdrawal($user, $amount, $withdrawal);

            return $withdrawal;
        });
    }

    /**
     * Mark a request as paid. The held funds leave the wallet for good.
     */
    public function markPaid(Withdrawal $withdrawal, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $note) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('That request has already been settled.');
            }

            $this->wallet->settleWithdrawal($locked->user, $locked->amount, $locked);

            $locked->update([
                'status' => 'paid',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'payment_note' => $note,
            ]);
        });
    }

    /**
     * Reject a request and return the held funds to the user.
     */
    public function reject(Withdrawal $withdrawal, User $admin, string $reason): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $reason) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('That request has already been settled.');
            }

            $this->wallet->refundWithdrawal($locked->user, $locked->amount, $locked, $reason);

            $locked->update([
                'status' => 'rejected',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });
    }
}
