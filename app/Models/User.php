<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/*
 * `is_admin`, `is_blocked` and `email_verified_at` are deliberately absent from
 * Fillable. They are privilege and trust flags, and profile updates pass
 * request data straight into update() -- listing them would let a user grant
 * themselves admin access or mark their own email verified by adding a field to
 * the form. Use the intent-named methods further down instead.
 */
#[Fillable(['name', 'email', 'phone', 'password', 'referral_code', 'referred_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class)->where('is_primary', true);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** The upline who referred this user. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    /** Direct (tier 1) downline. */
    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    /** Commissions this user has earned. */
    public function referralCommissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    // -----------------------------------------------------------------
    // Wallet access
    // -----------------------------------------------------------------

    /**
     * The wallet, created on first access.
     *
     * Registration creates one, but this keeps hand-inserted or legacy rows
     * from blowing up the dashboard with a null wallet.
     */
    public function ensureWallet(): Wallet
    {
        return $this->wallet ?? $this->wallet()->create();
    }

    // -----------------------------------------------------------------
    // Referrals
    // -----------------------------------------------------------------

    /**
     * Generate a code that is not already taken.
     *
     * Ambiguous glyphs (0/O, 1/I) are excluded because these get read aloud and
     * retyped from screenshots.
     */
    public static function generateReferralCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'ZV';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }

    public function referralLink(): string
    {
        return route('register', ['ref' => $this->referral_code]);
    }

    /**
     * Walk up the referral chain, nearest upline first.
     *
     * @return array<int, self> Index 0 is tier 1, index 1 is tier 2, and so on.
     */
    public function uplineChain(int $depth = 3): array
    {
        $chain = [];
        $current = $this;
        $seen = [$this->id => true];

        for ($i = 0; $i < $depth; $i++) {
            $parent = $current->referrer;

            // A self-referential or looping chain would otherwise spin forever.
            if (! $parent || isset($seen[$parent->id])) {
                break;
            }

            $seen[$parent->id] = true;
            $chain[] = $parent;
            $current = $parent;
        }

        return $chain;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    // -----------------------------------------------------------------
    // Privilege and trust flags
    //
    // These bypass mass assignment on purpose: the fields are not fillable, so
    // they can only be changed by calling one of these explicitly.
    // -----------------------------------------------------------------

    public function grantAdmin(): void
    {
        $this->forceFill(['is_admin' => true])->save();
    }

    public function revokeAdmin(): void
    {
        $this->forceFill(['is_admin' => false])->save();
    }

    public function block(string $reason): void
    {
        $this->forceFill([
            'is_blocked' => true,
            'blocked_reason' => $reason,
        ])->save();
    }

    public function unblock(): void
    {
        $this->forceFill([
            'is_blocked' => false,
            'blocked_reason' => null,
        ])->save();
    }

    public function markEmailAsVerified(): bool
    {
        $this->forceFill(['email_verified_at' => now()])->save();

        return true;
    }

    /** Called when the address changes and has to be re-proven. */
    public function markEmailAsUnverified(): void
    {
        $this->forceFill(['email_verified_at' => null])->save();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
