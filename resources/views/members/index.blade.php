<x-layouts.app title="Mitglieder">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Phase 2</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Mitglieder</h1>
            <p class="mt-1 text-sm text-slate-500">Mitgliederstammdaten, Status, Mitgliedsarten und Organisationszuordnungen verwalten.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('members.settings') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Stammdaten & Import</a>
            <a href="{{ route('members.export') }}" class="cv-button border border-slate-300 bg-white text-slate-700">CSV exportieren</a>
            <a href="{{ route('members.create') }}" class="cv-button-primary">+ Mitglied anlegen</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif

    <form method="get" class="cv-panel mt-6 grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_170px_minmax(200px,1fr)_190px_auto]">
        <label><span class="cv-label">Suche</span><input class="cv-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, E-Mail oder Mitgliedsnummer"></label>
        <label><span class="cv-label">Status</span><select class="cv-input" name="status"><option value="">Alle</option>@foreach(['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben','archived'=>'Archiviert'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span class="cv-label">Organisation</span><select class="cv-input" name="organization_unit_id"><option value="">Alle Einheiten</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)($filters['organization_unit_id'] ?? '') === (string)$organization->id)>{{ $organization->type?->name }} · {{ $organization->name }}</option>@endforeach</select></label>
        <label><span class="cv-label">Mitgliedsart</span><select class="cv-input" name="member_type_id"><option value="">Alle Arten</option>@foreach($memberTypes as $type)<option value="{{ $type->id }}" @selected((string)($filters['member_type_id'] ?? '') === (string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2"><button class="cv-button-primary" type="submit">Filtern</button><a class="cv-button border border-slate-300 bg-white text-slate-700" href="{{ route('members.index') }}">Reset</a></div>
    </form>

    <section class="cv-panel mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Nr.</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Mitgliedsart</th><th class="px-5 py-3">Organisation</th><th class="px-5 py-3">Eintritt</th><th class="px-5 py-3 text-right">Aktion</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($members as $member)
                    @php($primary = $member->memberships->where('is_primary', true)->first() ?? $member->memberships->first())
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $member->person->display_name }}</p><p class="text-xs text-slate-500">{{ $member->person->email ?: 'Keine E-Mail' }}</p></td>
                        <td class="px-5 py-4 font-mono text-xs">{{ $member->member_number }}</td>
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ ['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben'][$member->status] ?? $member->status }}</span></td>
                        <td class="px-5 py-4 text-slate-600">{{ $primary?->memberType?->name ?? $primary?->membership_type ?? '–' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $primary?->organizationUnit?->name ?? 'Nicht zugeordnet' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $member->joined_at?->format('d.m.Y') ?: '–' }}</td>
                        <td class="px-5 py-4 text-right">@if($member->trashed())<form method="post" action="{{ route('members.restore', $member->id) }}" class="inline">@csrf<button class="font-semibold text-blue-700 hover:underline">Wiederherstellen</button></form>@else<a class="font-semibold text-blue-700 hover:underline" href="{{ route('members.show', $member) }}">Öffnen</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center"><p class="font-semibold">Keine Mitglieder gefunden</p><p class="mt-1 text-sm text-slate-500">Passen Sie die Filter an oder legen Sie das erste Mitglied an.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($members->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $members->links() }}</div>@endif
    </section>
</x-layouts.app>
