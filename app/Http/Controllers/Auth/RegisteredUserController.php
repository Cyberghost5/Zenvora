<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly WalletService $wallet,
        private readonly SettingsService $settings,
    ) {}

    public function create(Request $request): View
    {
        $code = $request->query('ref');

        return view('auth.register', [
            // Only pre-fill the field if the code is real, so a mistyped link
            // does not silently drop the referral at submit time.
            'referrer' => $code ? User::query()->where('referral_code', $code)->first() : null,
            'referralCode' => $code,
            'tiers' => $this->referrals->tierTable(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:7', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
            'terms' => ['accepted'],
        ], [
            'referral_code.exists' => 'That referral code does not match any account.',
            'terms.accepted' => 'Please accept the terms to continue.',
        ]);

        $referrer = ! empty($validated['referral_code'] ?? null)
            ? User::query()->where('referral_code', $validated['referral_code'])->first()
            : null;

        $user = DB::transaction(function () use ($validated, $referrer) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'referral_code' => User::generateReferralCode(),
                'referred_by' => $referrer?->id,
            ]);

            // Every user needs a wallet from the moment they exist
            $user->wallet()->create();

            $bonusKobo = $this->settings->integer('welcome_bonus', 250_000);
            if ($bonusKobo > 0) {
                $this->wallet->creditWelcomeBonus($user, Money::fromMinor($bonusKobo));
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Welcome to '.config('app.name').'. Fund your wallet to start investing.');
    }
}
