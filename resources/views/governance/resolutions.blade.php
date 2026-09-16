<x-layouts.app title="Beschlussregister">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Verbandsarbeit</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Beschlussregister</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Zentrale Recherche über alle gefassten, abgelehnten oder protokollierten Beschlüsse des aktuellen Mandanten.</p>
        </div>
        <a class="cv-button border border-slate-300 bg-white text-slate-700" href="{{ route('governance.index') }}">← Gremien & Sitzungen</a>
    </div>

    <form method="get" class="cv-panel mt-6 grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-5">
        <label class="xl:col-span-2"><span class="cv-label">Suche</span><input class="cv-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nummer, Titel oder Beschlusstext"></label>
        <label><span class="cv-label">Ergebnis</span><select class="cv-input" name="status"><option value="">Alle</option><option value="passed" @selected(($filters['status'] ?? '')==='passed')>Angenommen</option><option value="rejected" @selected(($filters['status'] ?? '')==='rejected')>Abgelehnt</option><option value="recorded" @selected(($filters['status'] ?? '')==='recorded')>Festgestellt</option></select></label>
        <label><span class="cv-label">Gliederung</span><select class="cv-input" name="organization_unit_id"><option value="">Alle</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)($filters['organization_unit_id'] ?? '')===(string)$organization->id)>{{ $organization->name }}</option>@endforeach</select></label>
        <label><span class="cv-label">Jahr</span><div class="flex gap-2"><input type="number" min="2000" max="2100" class="cv-input" name="year" value="{{ $filters['year'] ?? '' }}" placeholder="{{ now()->year }}"><button class="cv-button-primary">Filtern</button></div></label>
    </form>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nummer</th><th class="px-4 py-3">Beschluss</th><th class="px-4 py-3">Sitzung</th><th class="px-4 py-3">Gliederung</th><th class="px-4 py-3">Abstimmung</th><th class="px-4 py-3">Aufgaben</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($resolutions as $resolution)
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-4 py-4"><span class="rounded bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ $resolution->resolution_number }}</span><p class="mt-2 text-xs font-semibold {{ $resolution->decision_status === 'passed' ? 'text-emerald-700' : ($resolution->decision_status === 'rejected' ? 'text-red-700' : 'text-slate-600') }}">{{ match($resolution->decision_status){'passed'=>'angenommen','rejected'=>'abgelehnt',default=>'festgestellt'} }}</p></td>
                            <td class="max-w-xl px-4 py-4"><p class="font-semibold text-slate-950">{{ $resolution->title }}</p><p class="mt-1 line-clamp-3 whitespace-pre-line text-sm text-slate-600">{{ $resolution->resolution_text }}</p>@if($resolution->motion)<p class="mt-2 text-xs text-slate-500">aus {{ $resolution->motion->motion_number }}</p>@endif</td>
                            <td class="px-4 py-4"><a class="font-semibold text-blue-700" href="{{ route('governance.meetings.show',$resolution->meeting) }}">{{ $resolution->meeting->title }}</a><p class="mt-1 text-xs text-slate-500">{{ $resolution->meeting->starts_at->format('d.m.Y H:i') }}@if($resolution->meeting->committee) · {{ $resolution->meeting->committee->name }}@endif</p></td>
                            <td class="px-4 py-4">{{ $resolution->organizationUnit?->name ?? 'mandantenweit' }}</td>
                            <td class="px-4 py-4 whitespace-nowrap"><p>{{ match($resolution->voting_method){'secret'=>'geheim','unanimous'=>'einstimmig','acclamation'=>'Akklamation',default=>'offen'} }}</p><p class="mt-1 text-xs text-slate-500">Ja {{ $resolution->votes_yes }} · Nein {{ $resolution->votes_no }} · Enth. {{ $resolution->votes_abstain }}</p></td>
                            <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $resolution->open_tasks_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $resolution->open_tasks_count }} offen</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Keine Beschlüsse für diese Filter gefunden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($resolutions->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $resolutions->links() }}</div>@endif
    </section>
</x-layouts.app>
