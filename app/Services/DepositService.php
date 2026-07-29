<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Deposit;
use App\Models\User;
use App\Services\Gateways\FlutterwaveGateway;
use App\Services\Gateways\GatewayResult;
use App\Services\Gateways\PaymentGateway;
use App\Services\Gateways\PaystackGateway;
use App\Support\DatabaseErrors;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Funding a wallet, across all four channels.
 *
 * Whatever the channel, exactly one method credits the wallet -- creditDeposit()
 * below -- and it refuses to run twice for the same deposit row.
 */
class DepositService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WalletService $wallet,
        private readonly PaystackGateway $paystack,
        private readonly FlutterwaveGateway $flutterwave,
    ) {}

    // -----------------------------------------------------------------
    // Bounds and channels
    // -----------------------------------------------------------------

    public function minimum(): Money
    {
        return $this->settings->money('deposit_min', 100_000);
    }

    public function maximum(): Money
    {
        return $this->settings->money('deposit_max', 500_000_000);
    }

    public function assertWithinBounds(Money $amount): void
    {
        if ($amount->lessThan($this->minimum())) {
            throw new RuntimeException(sprintf(
                'The minimum deposit is %s.',
                $this->minimum()->formatWithSymbol(),
            ));
        }

        if ($amount->greaterThan($this->maximum())) {
            throw new RuntimeException(sprintf(
                'The maximum deposit is %s.',
                $this->maximum()->formatWithSymbol(),
            ));
        }
    }

    public function gateway(string $channel): PaymentGateway
    {
        return match ($channel) {
            'paystack' => $this->paystack,
            'flutterwave' => $this->flutterwave,
            default => throw new RuntimeException("No gateway for channel: {$channel}"),
        };
    }

    /**
     * Channels the admin has enabled and which are actually usable.
     *
     * @return array<int, array{key: string, label: string, note: string}>
     */
    public function availableChannels(): array
    {
        $enabled = $this->settings->array('deposit_channels', ['paystack', 'flutterwave', 'coupon', 'manual']);

        $all = [
            'paystack' => [
                'label' => 'Card / bank via Paystack',
                'note' => 'Instant. Pay with a card, transfer or USSD.',
                'usable' => fn () => $this->paystack->isConfigured(),
            ],
            'flutterwave' => [
                'label' => 'Card / bank via Flutterwave',
                'note' => 'Instant. Pay with a card, transfer or mobile money.',
                'usable' => fn () => $this->flutterwave->isConfigured(),
            ],
            'coupon' => [
                'label' => 'Redeem a coupon',
                'note' => 'Credit your wallet with a code issued by support.',
                'usable' => fn () => true,
            ],
            'manual' => [
                'label' => 'Manual bank transfer',
                'note' => 'Transfer, upload your receipt, and we confirm it.',
                'usable' => fn () => filled($this->settings->string('manual_account_number')),
            ],
        ];

        $channels = [];

        foreach ($all as $key => $meta) {
            if (in_array($key, $enabled, true) && ($meta['usable'])()) {
                $channels[] = ['key' => $key, 'label' => $meta['label'], 'note' => $meta['note']];
            }
        }

        return $channels;
    }

    // -----------------------------------------------------------------
    // Gateway deposits
    // -----------------------------------------------------------------

    /**
     * Create a pending deposit and hand back the gateway's checkout URL.
     */
    public function startGatewayDeposit(User $user, Money $amount, string $channel, string $callbackUrl): string
    {
        $this->assertWithinBounds($amount);

        $gateway = $this->gateway($channel);

        if (! $gateway->isConfigured()) {
            throw new RuntimeException($gateway->label().' is not configured yet. Please choose another method.');
        }

        $deposit = Deposit::query()->create([
            'reference' => Deposit::newReference(),
            'user_id' => $user->id,
            'channel' => $channel,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        try {
            return $gateway->initiate($deposit->load('user'), $callbackUrl);
        } catch (\Throwable $e) {
            $deposit->delete();

            throw $e;
        }
    }

    /**
     * Confirm a gateway payment and credit the wallet.
     *
     * The gateway is re-queried and the amount it reports is compared against
     * what we asked for. A short payment is never credited at face value.
     */
    public function confirmGatewayDeposit(Deposit $deposit, ?string $gatewayReference = null): bool
    {
        if ($deposit->isSuccessful()) {
            return true;
        }

        $gateway = $this->gateway($deposit->channel);
        $result = $gateway->verify($gatewayReference ?: $deposit->reference);

        $deposit->update([
            'gateway_reference' => $result->reference,
            'gateway_payload' => $result->payload,
        ]);

        if (! $result->successful) {
            $deposit->delete();

            return false;
        }

        // Currency mismatch means the gateway is misconfigured; do not guess an
        // exchange rate, park it for a human.
        if (strtoupper($result->currency) !== strtoupper((string) config('zenvora.currency', 'NGN'))) {
            $deposit->update([
                'status' => 'awaiting_review',
                'rejection_reason' => "Paid in {$result->currency}, expected ".config('zenvora.currency'),
            ]);

            Log::warning('Deposit currency mismatch', ['deposit' => $deposit->reference]);

            return false;
        }

        // Underpayment: hold for review rather than crediting the lower figure
        // against a deposit the user thinks was for more.
        if ($result->amount->lessThan($deposit->amount)) {
            $deposit->update([
                'status' => 'awaiting_review',
                'rejection_reason' => sprintf(
                    'Paid %s against an expected %s.',
                    $result->amount->formatWithSymbol(),
                    $deposit->amount->formatWithSymbol(),
                ),
            ]);

            return false;
        }

        // Overpayment is credited in full -- the money genuinely arrived.
        $this->creditDeposit($deposit, $result->amount);

        return true;
    }

    // -----------------------------------------------------------------
    // Coupons
    // -----------------------------------------------------------------

    /**
     * Redeem a coupon straight into the deposit balance.
     */
    public function redeemCoupon(User $user, string $code): Deposit
    {
        $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();

        if (! $coupon) {
            throw new RuntimeException('That coupon code was not recognised.');
        }

        if (! $coupon->is_active) {
            throw new RuntimeException('That coupon has been disabled.');
        }

        if ($coupon->isExpired()) {
            throw new RuntimeException('That coupon expired on '.$coupon->expires_at->format('j M Y').'.');
        }

        if ($coupon->isExhausted()) {
            throw new RuntimeException('That coupon has already been fully used.');
        }

        try {
            return DB::transaction(function () use ($user, $coupon) {
                // Lock the coupon so two simultaneous redemptions cannot both
                // read the same used_count.
                /** @var Coupon $locked */
                $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();

                if ($locked->used_count >= $locked->max_uses) {
                    throw new RuntimeException('That coupon has already been fully used.');
                }

                // The unique index on (coupon_id, user_id) is the real guard
                // against one user redeeming the same coupon twice.
                CouponRedemption::query()->create([
                    'coupon_id' => $locked->id,
                    'user_id' => $user->id,
                    'amount' => $locked->amount,
                ]);

                $locked->increment('used_count');

                $deposit = Deposit::query()->create([
                    'reference' => Deposit::newReference(),
                    'user_id' => $user->id,
                    'channel' => 'coupon',
                    'amount' => $locked->amount,
                    'coupon_id' => $locked->id,
                    'status' => 'pending',
                ]);

                $this->creditDeposit($deposit, $locked->amount);

                return $deposit;
            });
        } catch (QueryException $e) {
            if (DatabaseErrors::isUniqueViolation($e)) {
                throw new RuntimeException('You have already redeemed that coupon.');
            }

            throw $e;
        }
    }

    // -----------------------------------------------------------------
    // Manual bank transfer
    // -----------------------------------------------------------------

    /**
     * Log a claimed bank transfer for an admin to confirm. Credits nothing.
     */
    public function submitManualDeposit(
        User $user,
        Money $amount,
        ?UploadedFile $proof,
        ?string $depositorName = null,
        ?string $paidOn = null,
    ): Deposit {
        $this->assertWithinBounds($amount);

        $path = $proof?->store('deposit-proofs', 'public');

        return Deposit::query()->create([
            'reference' => Deposit::newReference(),
            'user_id' => $user->id,
            'channel' => 'manual',
            'amount' => $amount,
            'status' => 'awaiting_review',
            'proof_path' => $path,
            'depositor_name' => $depositorName,
            'paid_on' => $paidOn,
            'paid_to_account' => trim(sprintf(
                '%s / %s',
                $this->settings->string('manual_bank_name'),
                $this->settings->string('manual_account_number'),
            ), ' /'),
        ]);
    }

    // -----------------------------------------------------------------
    // Admin review
    // -----------------------------------------------------------------

    public function approve(Deposit $deposit, User $admin, ?Money $amount = null): void
    {
        if ($deposit->isSuccessful()) {
            throw new RuntimeException('That deposit has already been credited.');
        }

        $deposit->update([
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->creditDeposit($deposit, $amount ?? $deposit->amount);
    }

    public function reject(Deposit $deposit, User $admin, string $reason): void
    {
        if ($deposit->isSuccessful()) {
            throw new RuntimeException('That deposit has already been credited and cannot be rejected.');
        }

        $deposit->update([
            'status' => 'failed',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    // -----------------------------------------------------------------
    // The one place a deposit becomes money
    // -----------------------------------------------------------------

    /**
     * Credit the wallet and mark the deposit successful, atomically.
     *
     * Re-reads the row under a lock and bails if another request got there
     * first, so a gateway callback racing its own webhook credits once.
     */
    private function creditDeposit(Deposit $deposit, Money $amount): void
    {
        DB::transaction(function () use ($deposit, $amount) {
            /** @var Deposit $locked */
            $locked = Deposit::query()
                ->whereKey($deposit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'successful') {
                return;
            }

            $this->wallet->creditDeposit(
                user: $locked->user,
                amount: $amount,
                source: $locked,
                description: 'Wallet funded via '.$locked->channelLabel(),
            );

            $locked->update([
                'amount' => $amount,
                'status' => 'successful',
                'credited_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        $deposit->refresh();
    }

    /**
     * Reconcile a webhook payload against a deposit we already know about.
     */
    public function handleWebhook(string $channel, GatewayResult $result): void
    {
        $deposit = Deposit::query()
            ->where('channel', $channel)
            ->where(function ($query) use ($result) {
                $query->where('reference', $result->reference)
                    ->orWhere('gateway_reference', $result->reference);
            })
            ->first();

        if (! $deposit) {
            Log::warning('Webhook for unknown deposit', [
                'channel' => $channel,
                'reference' => $result->reference,
            ]);

            return;
        }

        $this->confirmGatewayDeposit($deposit, $result->reference);
    }
}
