<x-layouts.admin title="Audit log"
                 heading="Audit log"
                 subheading="Every administrative action, in order, with what changed.">

    <form method="GET" action="{{ route('admin.audit') }}" class="flex flex-wrap gap-2">
        <select name="action" class="input max-w-56">
            <option value="">All actions</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected($activeAction === $action)>{{ $action }}</option>
            @endforeach
        </select>

        <input type="search" name="q" value="{{ $search }}"
               placeholder="Search descriptions or admin name"
               class="input max-w-sm flex-1">

        <button type="submit" class="btn-ghost">Filter</button>

        @if ($search || $activeAction)
            <a href="{{ route('admin.audit') }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    <section class="mt-6">
        @if ($logs->isEmpty())
            <x-empty-state title="Nothing logged"
                           message="Admin actions such as approving a deposit or changing a setting will appear here." />
        @else
            <ul class="space-y-3">
                @foreach ($logs as $log)
                    <li class="card !p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="pill bg-white/5 font-mono text-slate-300 ring-white/10">
                                        {{ $log->action }}
                                    </span>
                                    @if ($subject = $log->subjectLabel())
                                        <span class="text-xs text-slate-500">{{ $subject }}</span>
                                    @endif
                                </div>

                                <p class="mt-2 text-sm text-slate-200">{{ $log->description }}</p>

                                <p class="mt-1.5 text-xs text-slate-500">
                                    {{ $log->admin_name ?? 'System' }}
                                    @if ($log->ip_address)
                                        &middot; <span class="font-mono">{{ $log->ip_address }}</span>
                                    @endif
                                    &middot; {{ $log->created_at->format('j M Y, H:i:s') }}
                                </p>
                            </div>

                            <span class="shrink-0 text-xs text-slate-500">{{ $log->created_at->diffForHumans() }}</span>
                        </div>

                        {{-- before/after only when something actually changed, in a
                             disclosure so the list stays scannable. --}}
                        @if ($log->before || $log->after)
                            <details class="group mt-3 border-t border-white/5 pt-3">
                                <summary class="cursor-pointer list-none text-xs font-medium text-brand-400 hover:text-brand-300">
                                    <span class="group-open:hidden">Show what changed</span>
                                    <span class="hidden group-open:inline">Hide details</span>
                                </summary>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium text-slate-500">Before</p>
                                        <pre class="overflow-x-auto rounded-lg bg-ink-950/60 p-3 text-xs text-rose-200">{{ json_encode($log->before ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium text-slate-500">After</p>
                                        <pre class="overflow-x-auto rounded-lg bg-ink-950/60 p-3 text-xs text-emerald-200">{{ json_encode($log->after ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $logs->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
