<x-layouts.app title="Mitglied · Verlauf">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Änderungsverlauf</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $member->person->display_name }} · {{ $member->member_number }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('members.show', $member) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Stammdaten</a><a href="{{ route('members.crm', $member) }}" class="cv-button-primary">CRM & Verlauf</a></div>
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-bold">Audit-Trail</h2><p class="mt-1 text-sm text-slate-500">Protokollierte Änderungen an Mitglied, Person, Mitgliedschaften und Funktionen.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($logs as $log)
                @php($old = $log->old_values ? json_decode($log->old_values, true) : [])
                @php($new = $log->new_values ? json_decode($log->new_values, true) : [])
                <article class="p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="font-semibold text-slate-900">{{ $log->event }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->user_name ?: $log->user_email ?: 'System' }} · {{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d.m.Y H:i:s') }}</p></div>
                        <span class="text-xs text-slate-400">#{{ $log->id }}</span>
                    </div>
                    @if($old || $new)
                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Vorher</p><pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-xs text-slate-700">{{ json_encode($old, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></div>
                            <div class="rounded-lg bg-blue-50 p-3"><p class="text-xs font-bold uppercase tracking-wide text-blue-700">Nachher</p><pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-xs text-blue-950">{{ json_encode($new, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></div>
                        </div>
                    @endif
                    @if($log->reason)<p class="mt-3 text-sm text-slate-600"><strong>Grund:</strong> {{ $log->reason }}</p>@endif
                </article>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">Für dieses Mitglied liegen noch keine Audit-Einträge vor.</div>
            @endforelse
        </div>
    </section>
    <div class="mt-5">{{ $logs->links() }}</div>
</x-layouts.app>
