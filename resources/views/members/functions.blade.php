<x-layouts.app title="Ämter & Funktionen">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Ämter & Funktionen</h1>
            <p class="mt-1 text-sm text-slate-500">Zentrale Übersicht aller Funktionsbesetzungen über Vereine, Abteilungen und Verbandsebenen hinweg.</p>
        </div>
        <div class="flex gap-2"><a href="{{ route('members.settings') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Funktionskatalog</a><a href="{{ route('members.index') }}" class="cv-button-primary">Mitglieder</a></div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Aktive Besetzungen</p><p class="mt-1 text-3xl font-bold">{{ $activeCount }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Funktionsarten</p><p class="mt-1 text-3xl font-bold">{{ $definitions->count() }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Organisationseinheiten</p><p class="mt-1 text-3xl font-bold">{{ $organizations->count() }}</p></div>
    </div>

    <section class="cv-panel mt-5 overflow-hidden">
        <form method="get" class="grid gap-3 border-b border-slate-200 p-5 md:grid-cols-5">
            <label class="md:col-span-2"><span class="cv-label">Suche</span><input class="cv-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Person, Funktion oder Organisation …"></label>
            <label><span class="cv-label">Status</span><select class="cv-input" name="status"><option value="">Alle</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktiv</option><option value="ended" @selected(($filters['status'] ?? '') === 'ended')>Beendet</option></select></label>
            <label><span class="cv-label">Funktion</span><select class="cv-input" name="function_definition_id"><option value="">Alle</option>@foreach($definitions as $definition)<option value="{{ $definition->id }}" @selected(($filters['function_definition_id'] ?? null) == $definition->id)>{{ $definition->name }}</option>@endforeach</select></label>
            <label><span class="cv-label">Organisation</span><select class="cv-input" name="organization_unit_id"><option value="">Alle</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected(($filters['organization_unit_id'] ?? null) == $organization->id)>{{ $organization->name }}</option>@endforeach</select></label>
            <div class="md:col-span-5 flex justify-end gap-2"><a href="{{ route('members.functions.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zurücksetzen</a><button class="cv-button-primary">Filtern</button></div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Funktion</th><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Organisation</th><th class="px-5 py-3">Zeitraum</th><th class="px-5 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($assignments as $assignment)
                        @php($active = !$assignment->ends_at || $assignment->ends_at->isToday() || $assignment->ends_at->isFuture())
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $assignment->definition->name }}</p><p class="text-xs text-slate-500">{{ $assignment->definition->category ?: 'Ohne Kategorie' }}</p></td>
                            <td class="px-5 py-4"><a href="{{ route('members.show', $assignment->member) }}" class="font-semibold text-blue-700 hover:text-blue-900">{{ $assignment->member->person->display_name }}</a><p class="text-xs text-slate-500">{{ $assignment->member->member_number }}</p></td>
                            <td class="px-5 py-4 text-slate-700">{{ $assignment->organizationUnit?->name ?? 'Mandantenweit' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $assignment->starts_at?->format('d.m.Y') ?? 'offen' }} – {{ $assignment->ends_at?->format('d.m.Y') ?? 'offen' }}</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $active ? 'Aktiv' : 'Beendet' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Keine Funktionsbesetzungen gefunden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assignments->hasPages())<div class="border-t border-slate-200 p-4">{{ $assignments->links() }}</div>@endif
    </section>
</x-layouts.app>
