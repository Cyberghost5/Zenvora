@php
    use App\Support\Money;

    // Money settings are stored in kobo; the form works in naira.
    $toMajor = fn (string $key, int $fallback) => number_format(
        Money::fromMinor($settings->integer($key, $fallback))->toMajor(), 2, '.', ''
    );

    // Rates are stored in basis points; the form works in percent.
    $toPercent = fn (string $key, int $fallback) => rtrim(rtrim(
        number_format($settings->integer($key, $fallback) / 100, 2, '.', ''), '0'
    ), '.') ?: '0';

    $enabledChannels = $settings->array('deposit_channels', ['paystack', 'flutterwave', 'coupon', 'manual']);
    $selectedDays = array_map('intval', $settings->array('withdrawal_days', [1, 2, 3, 4, 5]));
@endphp

<x-layouts.admin title="Settings"
                 heading="Platform settings"
                 subheading="Deposit and withdrawal rules, the withdrawal window, and referral rates.">

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ------------------------------------------------------------ --}}
        {{-- Deposits                                                     --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <h2 class="text-lg font-semibold text-white">Deposits</h2>
            <p class="mt-1 text-sm text-slate-400">How much users may fund, and by which methods.</p>

            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="deposit_min" class="label">Minimum deposit</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                            {{ config('zenvora.currency_symbol') }}
                        </span>
                        <input id="deposit_min" name="deposit_min" type="number" step="0.01" min="0.01"
                               value="{{ old('deposit_min', $toMajor('deposit_min', 360000)) }}"
                               required class="input tabular !pl-9">
                    </div>
                    <x-input-error for="deposit_min" />
                </div>

                <div>
                    <label for="deposit_max" class="label">Maximum deposit</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                            {{ config('zenvora.currency_symbol') }}
                        </span>
                        <input id="deposit_max" name="deposit_max" type="number" step="0.01" min="0.01"
                               value="{{ old('deposit_max', $toMajor('deposit_max', 500000000)) }}"
                               required class="input tabular !pl-9">
                    </div>
                    <x-input-error for="deposit_max" />
                </div>

                <div>
                    <label for="welcome_bonus" class="label">Welcome Bonus</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                            {{ config('zenvora.currency_symbol') }}
                        </span>
                        <input id="welcome_bonus" name="welcome_bonus" type="number" step="0.01" min="0"
                               value="{{ old('welcome_bonus', $toMajor('welcome_bonus', 250000)) }}"
                               required class="input tabular !pl-9">
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Credited on registration.</p>
                    <x-input-error for="welcome_bonus" />
                </div>
            </div>

            <fieldset class="mt-5">
                <legend class="label">Funding methods</legend>

                <div class="grid gap-3 sm:grid-cols-2">
                    @php
                        $channelMeta = [
                            'paystack' => ['Paystack', 'Requires PAYSTACK_SECRET_KEY in .env', filled(config('services.paystack.secret'))],
                            'flutterwave' => ['Flutterwave', 'Requires FLUTTERWAVE_SECRET_KEY in .env', filled(config('services.flutterwave.secret'))],
                            'coupon' => ['Coupon codes', 'Issue codes from the Coupons page', true],
                            'manual' => ['Manual bank transfer', 'Requires the account details below', true],
                        ];
                    @endphp

                    @foreach ($channelMeta as $key => [$label, $note, $configured])
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-ink-950/50 p-3.5">
                            <input type="checkbox" name="deposit_channels[]" value="{{ $key }}"
                                   @checked(in_array($key, old('deposit_channels', $enabledChannels), true))
                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-white">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $note }}</span>
                                {{-- Say plainly when a channel is enabled but unusable, so it
                                     is not a mystery why users cannot see it. --}}
                                @unless ($configured)
                                    <span class="mt-1 block text-xs text-amber-400">
                                        Not configured - hidden from users until keys are set.
                                    </span>
                                @endunless
                            </span>
                        </label>
                    @endforeach
                </div>

                <x-input-error for="deposit_channels" />
            </fieldset>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Withdrawals                                                  --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <h2 class="text-lg font-semibold text-white">Withdrawals</h2>
            <p class="mt-1 text-sm text-slate-400">
                Limits and the schedule during which requests may be submitted.
            </p>

            <div class="mt-5 space-y-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-ink-950/50 p-4">
                    <input type="checkbox" name="withdrawal_enabled" value="1"
                           @checked(old('withdrawal_enabled', $settings->boolean('withdrawal_enabled', true)))
                           class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                    <span>
                        <span class="block text-sm font-medium text-white">Withdrawals enabled</span>
                        <span class="mt-0.5 block text-xs text-slate-500">
                            Unticking this closes withdrawals immediately, whatever the window says.
                        </span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-ink-950/50 p-4">
                    <input type="checkbox" name="withdrawal_require_investment" value="1"
                           @checked(old('withdrawal_require_investment', $settings->boolean('withdrawal_require_investment', true)))
                           class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                    <span>
                        <span class="block text-sm font-medium text-white">Require VIP plan activation before withdrawal</span>
                        <span class="mt-0.5 block text-xs text-slate-500">
                            When enabled, users must subscribe to at least one investment plan (such as VIP Trial) before withdrawing.
                        </span>
                    </span>
                </label>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="withdrawal_min" class="label">Minimum withdrawal</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                            {{ config('zenvora.currency_symbol') }}
                        </span>
                        <input id="withdrawal_min" name="withdrawal_min" type="number" step="0.01" min="0.01"
                               value="{{ old('withdrawal_min', $toMajor('withdrawal_min', 100000)) }}"
                               required class="input tabular !pl-9">
                    </div>
                    <x-input-error for="withdrawal_min" />
                </div>

                <div>
                    <label for="withdrawal_max" class="label">Maximum withdrawal</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                            {{ config('zenvora.currency_symbol') }}
                        </span>
                        <input id="withdrawal_max" name="withdrawal_max" type="number" step="0.01" min="0.01"
                               value="{{ old('withdrawal_max', $toMajor('withdrawal_max', 100000000)) }}"
                               required class="input tabular !pl-9">
                    </div>
                    <x-input-error for="withdrawal_max" />
                </div>

                <div>
                    <label for="withdrawal_fee_percent" class="label">Withdrawal fee</label>
                    <div class="relative">
                        <input id="withdrawal_fee_percent" name="withdrawal_fee_percent" type="number"
                               step="0.01" min="0" max="100"
                               value="{{ old('withdrawal_fee_percent', $toPercent('withdrawal_fee_bp', 0)) }}"
                               required class="input tabular !pr-8">
                        <span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-500">%</span>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Deducted from the requested amount.</p>
                    <x-input-error for="withdrawal_fee_percent" />
                </div>
            </div>

            {{-- The window: days plus opening hours plus timezone. --}}
            <div class="mt-6 border-t border-white/5 pt-6">
                <h3 class="text-sm font-semibold text-white">Withdrawal window</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Requests submitted outside this window are refused, both in the form and in the service layer.
                </p>

                <fieldset class="mt-4">
                    <legend class="label">Days requests are accepted</legend>

                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($dayNames as $iso => $name)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-white/10 bg-ink-950/50 px-3.5 py-2 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-500/15 has-[:checked]:text-brand-300">
                                <input type="checkbox" name="withdrawal_days[]" value="{{ $iso }}"
                                       @checked(in_array($iso, array_map('intval', old('withdrawal_days', $selectedDays)), true))
                                       class="h-4 w-4 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                                <span class="text-sm font-medium text-slate-200">{{ substr($name, 0, 3) }}</span>
                            </label>
                        @endforeach
                    </div>

                    <p class="mt-2 text-xs text-slate-500">
                        Selecting none closes withdrawals entirely.
                    </p>
                    <x-input-error for="withdrawal_days" />
                </fieldset>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="withdrawal_opens_at" class="label">Opens at</label>
                        <input id="withdrawal_opens_at" name="withdrawal_opens_at" type="time"
                               value="{{ old('withdrawal_opens_at', $settings->string('withdrawal_opens_at', '09:00')) }}"
                               required class="input tabular">
                        <x-input-error for="withdrawal_opens_at" />
                    </div>

                    <div>
                        <label for="withdrawal_closes_at" class="label">Closes at</label>
                        <input id="withdrawal_closes_at" name="withdrawal_closes_at" type="time"
                               value="{{ old('withdrawal_closes_at', $settings->string('withdrawal_closes_at', '17:00')) }}"
                               required class="input tabular">
                        <p class="mt-1.5 text-xs text-slate-500">
                            A closing time earlier than the opening time wraps past midnight.
                        </p>
                        <x-input-error for="withdrawal_closes_at" />
                    </div>

                    <div>
                        <label for="withdrawal_timezone" class="label">Timezone</label>
                        <select id="withdrawal_timezone" name="withdrawal_timezone" required class="input">
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}"
                                        @selected(old('withdrawal_timezone', $settings->string('withdrawal_timezone', 'Africa/Lagos')) === $tz)>
                                    {{ str_replace('_', ' ', $tz) }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error for="withdrawal_timezone" />
                    </div>
                </div>

                <p class="mt-4 rounded-xl border border-white/10 bg-ink-950/50 px-4 py-3 text-sm">
                    <span class="text-slate-400">Currently:</span>
                    <span class="font-medium text-white">{{ $window->summary() }}</span>
                    <span class="{{ $window->isOpen() ? 'text-emerald-400' : 'text-amber-400' }}">
                        - {{ $window->isOpen() ? 'open right now' : 'closed right now' }}
                    </span>
                </p>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Referrals                                                    --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <h2 class="text-lg font-semibold text-white">Referral commissions</h2>
            <p class="mt-1 text-sm text-slate-400">
                Paid when a referred user invests, as a percentage of their principal. Changing a rate affects
                future investments only.
            </p>

            <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-ink-950/50 p-4">
                <input type="checkbox" name="referral_enabled" value="1"
                       @checked(old('referral_enabled', $settings->boolean('referral_enabled', true)))
                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                <span>
                    <span class="block text-sm font-medium text-white">Referral commissions enabled</span>
                    <span class="mt-0.5 block text-xs text-slate-500">
                        Turning this off stops all new commission from accruing.
                    </span>
                </span>
            </label>

            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                @foreach ([1 => 'Tier 1 (direct)', 2 => 'Tier 2', 3 => 'Tier 3'] as $tier => $label)
                    <div>
                        <label for="referral_tier_{{ $tier }}_percent" class="label">{{ $label }}</label>
                        <div class="relative">
                            <input id="referral_tier_{{ $tier }}_percent"
                                   name="referral_tier_{{ $tier }}_percent"
                                   type="number" step="0.01" min="0" max="100"
                                   value="{{ old('referral_tier_'.$tier.'_percent', $toPercent('referral_tier_'.$tier.'_bp', [1 => 1000, 2 => 500, 3 => 200][$tier])) }}"
                                   required class="input tabular !pr-8">
                            <span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-500">%</span>
                        </div>
                        <x-input-error :for="'referral_tier_'.$tier.'_percent'" />
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-xs leading-relaxed text-slate-500">
                Total across all three tiers is currently
                <span class="tabular font-semibold text-slate-300">
                    {{ rtrim(rtrim(number_format((
                        $settings->integer('referral_tier_1_bp', 1000)
                        + $settings->integer('referral_tier_2_bp', 500)
                        + $settings->integer('referral_tier_3_bp', 200)
                    ) / 100, 2, '.', ''), '0'), '.') }}%
                </span>
                of every investment placed by a referred user. Set 0 to disable an individual tier.
            </p>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Manual transfer details                                      --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <h2 class="text-lg font-semibold text-white">Manual bank transfer</h2>
            <p class="mt-1 text-sm text-slate-400">
                Shown to users who fund by bank transfer. Manual deposits stay hidden until the account
                number is filled in.
            </p>

            <div class="mt-5 border-t border-white/5 pt-4">
                <h3 class="text-sm font-semibold text-white">Bank Account 1</h3>
                <div class="mt-3 grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="manual_bank_name" class="label">Bank name</label>
                        <input id="manual_bank_name" name="manual_bank_name" type="text"
                               value="{{ old('manual_bank_name', $settings->string('manual_bank_name')) }}"
                               class="input" placeholder="e.g. Access Bank">
                        <x-input-error for="manual_bank_name" />
                    </div>

                    <div>
                        <label for="manual_account_number" class="label">Account number</label>
                        <input id="manual_account_number" name="manual_account_number" type="text"
                               value="{{ old('manual_account_number', $settings->string('manual_account_number')) }}"
                               class="input tabular font-mono" placeholder="0123456789">
                        <x-input-error for="manual_account_number" />
                    </div>

                    <div>
                        <label for="manual_account_name" class="label">Account name</label>
                        <input id="manual_account_name" name="manual_account_name" type="text"
                               value="{{ old('manual_account_name', $settings->string('manual_account_name')) }}"
                               class="input" placeholder="Your company name">
                        <x-input-error for="manual_account_name" />
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t border-white/5 pt-4">
                <h3 class="text-sm font-semibold text-white">Bank Account 2 <span class="font-normal text-slate-500">(Optional)</span></h3>
                <div class="mt-3 grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="manual_bank_name_2" class="label">Bank name 2</label>
                        <input id="manual_bank_name_2" name="manual_bank_name_2" type="text"
                               value="{{ old('manual_bank_name_2', $settings->string('manual_bank_name_2')) }}"
                               class="input" placeholder="e.g. GTBank">
                        <x-input-error for="manual_bank_name_2" />
                    </div>

                    <div>
                        <label for="manual_account_number_2" class="label">Account number 2</label>
                        <input id="manual_account_number_2" name="manual_account_number_2" type="text"
                               value="{{ old('manual_account_number_2', $settings->string('manual_account_number_2')) }}"
                               class="input tabular font-mono" placeholder="0123456789">
                        <x-input-error for="manual_account_number_2" />
                    </div>

                    <div>
                        <label for="manual_account_name_2" class="label">Account name 2</label>
                        <input id="manual_account_name_2" name="manual_account_name_2" type="text"
                               value="{{ old('manual_account_name_2', $settings->string('manual_account_name_2')) }}"
                               class="input" placeholder="Your company name">
                        <x-input-error for="manual_account_name_2" />
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <label for="manual_instructions" class="label">Instructions shown to depositors</label>
                <textarea id="manual_instructions" name="manual_instructions" rows="3"
                          class="input">{{ old('manual_instructions', $settings->string('manual_instructions')) }}</textarea>
                <x-input-error for="manual_instructions" />
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Announcement Popup Notice                                     --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Login Announcement Popup</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Display a modal announcement notice to users when they sign in or open their dashboard.
                    </p>
                </div>

                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" name="announcement_enabled" value="1"
                           @checked(old('announcement_enabled', $settings->boolean('announcement_enabled')))
                           class="peer sr-only">
                    <div class="h-6 w-11 rounded-full bg-ink-950/85 ring-1 ring-inset ring-white/10 transition
                                peer-checked:bg-brand-500 peer-checked:ring-brand-400
                                after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full
                                after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="announcement_title" class="label">Announcement Title</label>
                    <input id="announcement_title" name="announcement_title" type="text"
                           value="{{ old('announcement_title', $settings->string('announcement_title', 'Important Notice')) }}"
                           placeholder="e.g. Welcome to Zenvora!" class="input">
                    <x-input-error for="announcement_title" />
                </div>

                <div>
                    <label class="label">Announcement Content (WYSIWYG Editor)</label>
                    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
                    <style>
                        .ql-toolbar.ql-snow {
                            border-color: rgba(255, 255, 255, 0.15) !important;
                            border-top-left-radius: 0.75rem;
                            border-top-right-radius: 0.75rem;
                            background-color: rgba(17, 23, 28, 0.7);
                        }
                        .ql-container.ql-snow {
                            border-color: rgba(255, 255, 255, 0.15) !important;
                            border-bottom-left-radius: 0.75rem;
                            border-bottom-right-radius: 0.75rem;
                            background-color: rgba(9, 14, 18, 0.5);
                            color: #e2e8f0;
                            font-size: 0.95rem;
                        }
                        html[data-theme="light"] .ql-toolbar.ql-snow {
                            border-color: #cbd5e1 !important;
                            background-color: #f8fafc;
                        }
                        html[data-theme="light"] .ql-container.ql-snow {
                            border-color: #cbd5e1 !important;
                            background-color: #ffffff;
                            color: #0f172a;
                        }
                        .ql-stroke {
                            stroke: #94a3b8 !important;
                        }
                        .ql-fill {
                            fill: #94a3b8 !important;
                        }
                        .ql-picker {
                            color: #94a3b8 !important;
                        }
                        .ql-editor {
                            min-height: 160px;
                        }
                    </style>

                    <div id="quill-announcement-editor">
                        {!! old('announcement_body', $settings->string('announcement_body')) !!}
                    </div>

                    <input type="hidden" name="announcement_body" id="announcement_body" value="{{ old('announcement_body', $settings->string('announcement_body')) }}">
                    <p class="mt-1.5 text-xs text-slate-500">Rich formatted message displayed to signed-in users on login.</p>
                    <x-input-error for="announcement_body" />

                    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const container = document.getElementById('quill-announcement-editor');
                            if (!container) return;

                            const quill = new Quill('#quill-announcement-editor', {
                                theme: 'snow',
                                placeholder: 'Compose your announcement message here...',
                                modules: {
                                    toolbar: [
                                        [{ 'header': [1, 2, 3, false] }],
                                        ['bold', 'italic', 'underline', 'strike'],
                                        [{ 'color': [] }, { 'background': [] }],
                                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                        ['link', 'clean']
                                    ]
                                }
                            });

                            const hiddenInput = document.getElementById('announcement_body');
                            const form = hiddenInput.closest('form');

                            form.addEventListener('submit', function() {
                                hiddenInput.value = quill.root.innerHTML;
                            });
                        });
                    </script>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Site                                                         --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <h2 class="text-lg font-semibold text-white">Site details</h2>

            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="site_name" class="label">Site name</label>
                    <input id="site_name" name="site_name" type="text"
                           value="{{ old('site_name', $settings->string('site_name', config('app.name'))) }}"
                           required class="input">
                    <p class="mt-1.5 text-xs text-slate-500">
                        Page titles use APP_NAME from .env; this is used in content.
                    </p>
                    <x-input-error for="site_name" />
                </div>

                <div>
                    <label for="support_email" class="label">Support email</label>
                    <input id="support_email" name="support_email" type="email"
                           value="{{ old('support_email', $settings->string('support_email')) }}"
                           required class="input">
                    <x-input-error for="support_email" />
                </div>

                <div>
                    <label for="support_phone" class="label">
                        Support phone <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <input id="support_phone" name="support_phone" type="tel"
                           value="{{ old('support_phone', $settings->string('support_phone')) }}"
                           class="input">
                    <x-input-error for="support_phone" />
                </div>
            </div>
        </section>

        <div class="sticky bottom-4 flex flex-wrap gap-3 rounded-2xl border border-white/10 bg-ink-900/95 p-4 backdrop-blur">
            <button type="submit" class="btn-primary">Save all settings</button>
            <a href="{{ route('admin.dashboard') }}" class="btn-ghost">Cancel</a>
            <p class="w-full text-xs text-slate-500 sm:ml-auto sm:w-auto sm:self-center">
                Every change is written to the audit log.
            </p>
        </div>
    </form>
</x-layouts.admin>
