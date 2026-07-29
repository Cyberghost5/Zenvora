<button type="button"
        onclick="toggleAppTheme()"
        aria-label="Toggle theme"
        class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium text-slate-300 transition hover:border-white/20 hover:bg-white/10 hover:text-white dark-toggle-btn">
    <svg class="theme-icon-sun hidden h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
    <svg class="theme-icon-moon hidden h-4 w-4 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
    </svg>
    <span class="theme-toggle-text">Mode</span>
</button>

<script>
    if (typeof window.updateThemeUI !== 'function') {
        window.updateThemeUI = function() {
            const theme = document.documentElement.getAttribute('data-theme') || 'dark';
            document.querySelectorAll('.theme-icon-sun').forEach(el => {
                el.classList.toggle('hidden', theme !== 'light');
            });
            document.querySelectorAll('.theme-icon-moon').forEach(el => {
                el.classList.toggle('hidden', theme === 'light');
            });
            document.querySelectorAll('.theme-toggle-text').forEach(el => {
                el.textContent = theme === 'light' ? 'Light' : 'Dark';
            });
        };

        window.toggleAppTheme = function() {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            window.updateThemeUI();
        };

        document.addEventListener('DOMContentLoaded', window.updateThemeUI);
    }

    if (document.readyState !== 'loading') {
        window.updateThemeUI();
    }
</script>
