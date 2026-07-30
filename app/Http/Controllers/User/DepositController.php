<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\DepositService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        return view('user.deposits.index', [
            'deposits' => $request->user()->deposits()
                ->whereIn('status', ['successful', 'awaiting_review'])
                ->latest()
                ->paginate(15),
            'channels' => $this->deposits->availableChannels(),
            'min' => $this->deposits->minimum(),
            'max' => $this->deposits->maximum(),
        ]);
    }

    public function create(): View
    {
        return view('user.deposits.create', [
            'channels' => $this->deposits->availableChannels(),
            'min' => $this->deposits->minimum(),
            'max' => $this->deposits->maximum(),
            'bank' => [
                'name' => $this->settings->string('manual_bank_name'),
                'number' => $this->settings->string('manual_account_number'),
                'account' => $this->settings->string('manual_account_name'),
                'name_2' => $this->settings->string('manual_bank_name_2'),
                'number_2' => $this->settings->string('manual_account_number_2'),
                'account_2' => $this->settings->string('manual_account_name_2'),
                'instructions' => $this->settings->string('manual_instructions'),
            ],
        ]);
    }

    /**
     * Fan out to the right channel. Each branch either redirects offsite or
     * records something for review -- none of them credit a wallet directly.
     */
    public function store(Request $request): RedirectResponse
    {
        $channel = $request->input('channel');

        $enabled = collect($this->deposits->availableChannels())->pluck('key')->all();

        if (! in_array($channel, $enabled, true)) {
            return back()->withErrors(['channel' => 'That funding method is not available right now.']);
        }

        return match ($channel) {
            'coupon' => $this->redeemCoupon($request),
            'manual' => $this->submitManual($request),
            default => $this->startGateway($request, $channel),
        };
    }

    private function startGateway(Request $request, string $channel): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $url = $this->deposits->startGatewayDeposit(
                user: $request->user(),
                amount: Money::fromMajor($validated['amount']),
                channel: $channel,
                callbackUrl: route('deposits.callback', ['channel' => $channel]),
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        // Away to the gateway's hosted checkout.
        return redirect()->away($url);
    }

    private function redeemCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:32'],
        ]);

        try {
            $deposit = $this->deposits->redeemCoupon($request->user(), $validated['coupon_code']);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['coupon_code' => $e->getMessage()]);
        }

        return redirect()->route('deposits.index')->with(
            'status',
            $deposit->amount->formatWithSymbol().' has been credited to your deposit balance.',
        );
    }

    private function submitManual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'depositor_name' => ['required', 'string', 'max:120'],
            'depositor_account' => ['required', 'string', 'max:50'],
        ], [
            'depositor_name.required' => 'Please enter the name of the sender.',
            'depositor_account.required' => 'Please enter the account number of the sender.',
        ]);

        try {
            $deposit = $this->deposits->submitManualDeposit(
                user: $request->user(),
                amount: Money::fromMajor($validated['amount']),
                depositorName: $validated['depositor_name'],
                depositorAccount: $validated['depositor_account'],
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('deposits.index')->with(
            'status',
            "Transfer of {$deposit->amount->formatWithSymbol()} logged as {$deposit->reference}. "
            .'Your wallet is credited once an administrator confirms it.',
        );
    }

    /**
     * Where the gateway sends the user back to.
     *
     * The status in this request is treated as a hint only -- the deposit is
     * confirmed by asking the gateway's API directly.
     */
    public function callback(Request $request, string $channel): RedirectResponse
    {
        $reference = $request->query('reference')
            ?? $request->query('tx_ref')
            ?? $request->query('trxref');

        $transactionId = $request->query('transaction_id');

        if (! $reference) {
            return redirect()->route('deposits.index')
                ->withErrors(['deposit' => 'We could not identify that payment. If you were debited, contact support with your reference.']);
        }

        $deposit = Deposit::query()
            ->where('user_id', $request->user()->id)
            ->where('reference', $reference)
            ->first();

        if (! $deposit) {
            return redirect()->route('deposits.index')
                ->withErrors(['deposit' => 'That payment does not belong to your account.']);
        }

        try {
            // Flutterwave identifies the transaction by id on the redirect;
            // Paystack echoes our own reference back.
            $credited = $this->deposits->confirmGatewayDeposit(
                $deposit,
                $channel === 'flutterwave' ? ($transactionId ?: $reference) : $reference,
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('deposits.index')
                ->withErrors(['deposit' => 'We could not verify that payment yet. It will be reconciled automatically -- please check back shortly.']);
        }

        if (! $credited) {
            return redirect()->route('deposits.index')
                ->withErrors(['deposit' => $deposit->fresh()->rejection_reason ?? 'That payment was not completed.']);
        }

        return redirect()->route('deposits.index')->with(
            'status',
            $deposit->fresh()->amount->formatWithSymbol().' has been credited to your deposit balance.',
        );
    }

    public function show(Request $request, Deposit $deposit): View
    {
        abort_unless($deposit->user_id === $request->user()->id, 404);

        return view('user.deposits.show', ['deposit' => $deposit->load('coupon')]);
    }
}
