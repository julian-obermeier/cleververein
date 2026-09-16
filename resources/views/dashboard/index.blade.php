<x-layouts.app title="Übersicht">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Guten Morgen, {{ auth()->user()->person?->first_name ?? 'Willkommen' }}</h1><p class="mt-1 text-sm text-slate-500">Hier ist die aktuelle Übersicht für {{ auth()->user()->currentTenant->name }}.</p></div>
        <div class="flex gap-2"><a href="{{ route('members.create') }}" class="cv-button-primary">+ Mitglied</a><a href="{{ route('organization.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Organisation</a></div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('members.index', ['status'=>'active']) }}" class="cv-panel p-5 transition hover:-translate-y-0.5 hover:shadow-sm"><p class="text-sm font-medium text-slate-500">Aktive Mitglieder</p><p class="mt-2 text-3xl font-bold">{{ number_format($activeMembers, 0, ',', '.') }}</p><p class="mt-2 text-xs font-semibold text-emerald-700">Aktueller Bestand</p></a>
        <a href="{{ route('members.index', ['status'=>'pending']) }}" class="cv-panel p-5 transition hover:-translate-y-0.5 hover:shadow-sm"><p class="text-sm font-medium text-slate-500">Vorgemerkte Mitglieder</p><p class="mt-2 text-3xl font-bold">{{ number_format($pendingMembers, 0, ',', '.') }}</p><p class="mt-2 text-xs text-slate-500">Noch nicht vollständig aktiv</p></a>
        <div class="cv-panel p-5"><p class="text-sm font-medium text-slate-500">Neue Mitglieder im Monat</p><p class="mt-2 text-3xl font-bold">{{ number_format($newThisMonth, 0, ',', '.') }}</p><p class="mt-2 text-xs text-slate-500">Seit {{ now()->startOfMonth()->format('d.m.Y') }}</p></div>
        <a href="{{ route('organization.index') }}" class="cv-panel p-5 transition hover:-translate-y-0.5 hover:shadow-sm"><p class="text-sm font-medium text-slate-500">Aktive Gliederungen</p><p class="mt-2 text-3xl font-bold">{{ number_format($organizationUnits, 0, ',', '.') }}</p><p class="mt-2 text-xs text-slate-500">Vereine, Verbände und Untergliederungen</p></a>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,.55fr)]">
        <section class="cv-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-bold">Zuletzt angelegte Mitglieder</h2><p class="mt-1 text-sm text-slate-500">Die neuesten Datensätze im aktuellen Mandanten.</p></div><a href="{{ route('members.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">Alle anzeigen</a></div>
            <div class="divide-y divide-slate-100">
                @forelse($recentMembers as $member)
                    <a href="{{ route('members.show', $member) }}" class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50"><div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-100 font-bold text-blue-700">{{ strtoupper(substr($member->person->first_name,0,1).substr($member->person->last_name,0,1)) }}</div><div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $member->person->display_name }}</p><p class="truncate text-xs text-slate-500">{{ $member->memberships->where('is_primary', true)->first()?->organizationUnit?->name ?? $member->memberships->first()?->organizationUnit?->name ?? 'Noch keiner Gliederung zugeordnet' }}</p></div><div class="text-right"><p class="font-mono text-xs text-slate-500">{{ $member->member_number }}</p><p class="mt-1 text-xs text-slate-400">{{ $member->created_at->format('d.m.Y') }}</p></div></a>
                @empty
                    <div class="px-5 py-12 text-center"><p class="font-semibold">Noch keine Mitglieder erfasst</p><p class="mt-1 text-sm text-slate-500">Legen Sie das erste Mitglied an, um die Mitgliederverwaltung zu starten.</p><a href="{{ route('members.create') }}" class="cv-button-primary mt-4">Erstes Mitglied anlegen</a></div>
                @endforelse
            </div>
        </section>

        <section class="cv-panel p-5">
            <h2 class="font-bold">Mitgliederstatus</h2>
            <p class="mt-1 text-sm text-slate-500">Verteilung der aktuell nicht archivierten Mitglieder.</p>
            <dl class="mt-6 space-y-4 text-sm">
                @foreach(['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben'] as $key=>$label)
                    <div class="flex items-center justify-between gap-4"><dt class="text-slate-600">{{ $label }}</dt><dd class="font-bold">{{ number_format((int)($statusCounts[$key] ?? 0), 0, ',', '.') }}</dd></div>
                @endforeach
            </dl>
        </section>
    </div>

    <section class="cv-panel mt-4 overflow-hidden" aria-labelledby="systemstatus"><div class="border-b border-slate-200 px-5 py-4"><h2 id="systemstatus" class="font-bold">Systemstatus</h2></div><div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Bereich</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Stand</th></tr></thead><tbody class="divide-y divide-slate-100"><tr><td class="px-5 py-4 font-semibold">Mandantentrennung</td><td class="px-5 py-4"><span class="text-emerald-700">● Aktiv</span></td><td class="px-5 py-4 text-slate-500">Request-Kontext und automatische Scopes</td></tr><tr><td class="px-5 py-4 font-semibold">Organisation</td><td class="px-5 py-4"><span class="text-emerald-700">● Aktiv</span></td><td class="px-5 py-4 text-slate-500">Closure Table mit frei definierbaren Ebenen</td></tr><tr><td class="px-5 py-4 font-semibold">Mitgliederverwaltung</td><td class="px-5 py-4"><span class="text-emerald-700">● Aktiv</span></td><td class="px-5 py-4 text-slate-500">Stammdaten, Status, Archiv und Mehrfachmitgliedschaften</td></tr></tbody></table></div></section>
</x-layouts.app>
