@inject('settingsService', 'App\Services\SettingsService')

@php
    $enabled = $settingsService->boolean('announcement_enabled');
    $title = $settingsService->string('announcement_title', 'Important Notice');
    $body = $settingsService->string('announcement_body', '');
    $noticeHash = md5($title . '|' . $body);
@endphp

@if ($enabled && filled($body))
    <div id="announcement-modal"
         data-notice-hash="{{ $noticeHash }}"
         class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm transition-opacity"
         aria-modal="true" role="dialog">
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-white/10 bg-ink-900 shadow-2xl p-6 transition-all sm:p-8">
            <button type="button"
                    onclick="dismissAnnouncementModal()"
                    class="absolute top-4 right-4 grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-ink-950/50 text-slate-400 hover:bg-white/10 hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-500/15 text-brand-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 000-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white">{{ $title }}</h3>
            </div>

            <div class="mt-4 text-sm leading-relaxed text-slate-300 prose prose-invert max-w-none">
                {!! $body !!}
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button"
                        onclick="dismissAnnouncementModal()"
                        class="btn-primary w-full sm:w-auto px-6">
                    Got it
                </button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('announcement-modal');
            if (!modal) return;
            const hash = modal.getAttribute('data-notice-hash');
            const storageKey = 'zenvora_notice_dismissed_' + hash;

            if (!sessionStorage.getItem(storageKey)) {
                // modal.classList.remove('hidden');
            }

            window.dismissAnnouncementModal = function() {
                if (modal) {
                    // modal.classList.add('hidden');
                    sessionStorage.setItem(storageKey, 'true');
                }
            };
        })();
    </script>
@endif
