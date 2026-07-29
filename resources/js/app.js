/*
 * Small, dependency-free behaviours. Everything here is progressive: the pages
 * are server-rendered and remain usable if this file fails to load.
 */

// -- Mobile navigation -------------------------------------------------------
function initNavToggle() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const panel = document.querySelector('[data-nav-panel]');

    if (!toggle || !panel) return;

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', () => {
        setOpen(panel.classList.contains('hidden'));
    });

    // Escape closes it, matching what a keyboard user expects of a menu.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
            setOpen(false);
            toggle.focus();
        }
    });
}

// -- Copy to clipboard ------------------------------------------------------
function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copy;
            const original = button.dataset.copyLabel || button.textContent.trim();

            try {
                await navigator.clipboard.writeText(value);
            } catch {
                // Clipboard access is refused outside a secure context, so fall
                // back to a selection the user can copy by hand.
                const field = document.createElement('textarea');
                field.value = value;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }

            button.dataset.copyLabel = original;
            button.textContent = 'Copied';
            button.classList.add('!bg-emerald-500', '!text-ink-950');

            setTimeout(() => {
                button.textContent = original;
                button.classList.remove('!bg-emerald-500', '!text-ink-950');
            }, 1600);
        });
    });
}

// -- Destructive-action confirmation ---------------------------------------
function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
}

// -- Disclosure panels (funding method, admin actions) ---------------------
function initTogglePanels() {
    document.querySelectorAll('[data-toggle-target]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const target = document.querySelector(trigger.dataset.toggleTarget);
            if (!target) return;

            const willShow = target.classList.contains('hidden');
            target.classList.toggle('hidden', !willShow);
            trigger.setAttribute('aria-expanded', String(willShow));

            if (willShow) {
                target.querySelector('input, textarea, select')?.focus();
            }
        });
    });
}

/*
 * Funding channel picker: only the fields for the chosen method are shown, so
 * the user is not asked for a coupon code and a bank receipt at the same time.
 */
function initChannelPicker() {
    const radios = document.querySelectorAll('input[name="channel"]');
    if (!radios.length) return;

    const apply = () => {
        const selected = document.querySelector('input[name="channel"]:checked')?.value;

        document.querySelectorAll('[data-channel-fields]').forEach((section) => {
            const isMatch = section.dataset.channelFields === selected;
            section.classList.toggle('hidden', !isMatch);

            // Required fields on a hidden section would block submission with
            // an error the user cannot see, so toggle them with visibility.
            section.querySelectorAll('[data-required]').forEach((field) => {
                if (isMatch) {
                    field.setAttribute('required', '');
                } else {
                    field.removeAttribute('required');
                }
            });
        });

        document.querySelectorAll('[data-channel-card]').forEach((card) => {
            const active = card.dataset.channelCard === selected;
            card.classList.toggle('border-brand-400', active);
            card.classList.toggle('bg-brand-500/10', active);
            card.classList.toggle('border-white/10', !active);
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', apply));
    apply();
}

/*
 * Live projection on the investment form: shows the daily return, total return
 * and maturity value as the user types, from the plan's own data attributes.
 */
function initInvestmentProjection() {
    document.querySelectorAll('[data-projection]').forEach((form) => {
        const amountField = form.querySelector('[data-projection-amount]');
        const output = form.querySelector('[data-projection-output]');

        if (!amountField || !output) return;

        const dailyBp = Number(form.dataset.dailyBp || 0);
        const days = Number(form.dataset.days || 0);
        const returnsCapital = form.dataset.returnCapital === '1';
        const symbol = form.dataset.symbol || '₦';

        const format = (value) =>
            symbol +
            value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

        const update = () => {
            const principal = parseFloat(amountField.value);

            if (!principal || principal <= 0) {
                output.classList.add('hidden');
                return;
            }

            // Mirrors Money::percentageBp -- floor to whole kobo, so the figure
            // shown here matches what the server will actually credit.
            const daily = Math.floor(principal * 100 * dailyBp / 10000) / 100;
            const total = daily * days;
            const matures = returnsCapital ? total + principal : total;

            output.querySelector('[data-out-daily]').textContent = format(daily);
            output.querySelector('[data-out-total]').textContent = format(total);
            output.querySelector('[data-out-matures]').textContent = format(matures);
            output.classList.remove('hidden');
        };

        amountField.addEventListener('input', update);
        update();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initNavToggle();
    initCopyButtons();
    initConfirmForms();
    initTogglePanels();
    initChannelPicker();
    initInvestmentProjection();
});
