<x-layouts.app title="Mitglieder">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Phase 2</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Mitglieder</h1>
            <p class="mt-1 text-sm text-slate-500">Mitgliederstammdaten, CRM, Status, Mitgliedsarten und Organisationszuordnungen verwalten.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('members.segments.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Segmente</a>
            <a href="{{ route('members.settings') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Stammdaten & Import</a>
            <a href="{{ route('members.create') }}" class="cv-button-primary">+ Mitglied anlegen</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="get" class="cv-panel mt-6 grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_170px_minmax(200px,1fr)_190px_auto]">
        <label><span class="cv-label">Suche</span><input class="cv-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, E-Mail oder Mitgliedsnummer"></label>
        <label><span class="cv-label">Status</span><select class="cv-input" name="status"><option value="">Alle</option>@foreach(['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben','archived'=>'Archiviert'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span class="cv-label">Organisation</span><select class="cv-input" name="organization_unit_id"><option value="">Alle Einheiten</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)($filters['organization_unit_id'] ?? '') === (string)$organization->id)>{{ $organization->type?->name }} · {{ $organization->name }}</option>@endforeach</select></label>
        <label><span class="cv-label">Mitgliedsart</span><select class="cv-input" name="member_type_id"><option value="">Alle Arten</option>@foreach($memberTypes as $type)<option value="{{ $type->id }}" @selected((string)($filters['member_type_id'] ?? '') === (string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2"><button class="cv-button-primary" type="submit">Filtern</button><a class="cv-button border border-slate-300 bg-white text-slate-700" href="{{ route('members.index') }}">Reset</a></div>
    </form>

    @if(($filters['status'] ?? '') !== 'archived')
        <form method="post" action="{{ route('members.bulk') }}" class="mt-4">@csrf
            <section class="cv-panel overflow-hidden">
                <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-[minmax(180px,1fr)_180px_minmax(180px,1fr)_auto]">
                    <label><span class="cv-label">Massenaktion</span><select required class="cv-input bg-white" name="action"><option value="set_status">Status setzen</option><option value="add_tag">Tag hinzufügen</option><option value="remove_tag">Tag entfernen</option><option value="archive">Archivieren</option></select></label>
                    <label><span class="cv-label">Status</span><select class="cv-input bg-white" name="status"><option value="">Bitte wählen …</option><option value="active">Aktiv</option><option value="pending">Vorgemerkt</option><option value="inactive">Inaktiv</option><option value="resigned">Ausgetreten</option><option value="deceased">Verstorben</option></select></label>
                    <label><span class="cv-label">Tag</span><select class="cv-input bg-white" name="member_tag_id"><option value="">Bitte wählen …</option>@foreach($bulkTags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></label>
                    <div class="flex items-end"><button class="cv-button-primary">Auf Auswahl anwenden</button></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] text-left text-sm">
                        <thead class="bg-white text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3"><span class="sr-only">Auswahl</span></th><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Nr.</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Mitgliedsart</th><th class="px-5 py-3">Organisation</th><th class="px-5 py-3">Eintritt</th><th class="px-5 py-3 text-right">Aktion</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($members as $member)
                            @php($primary = $member->memberships->where('is_primary', true)->first() ?? $member->memberships->first())
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-4"><input type="checkbox" name="members[]" value="{{ $member->id }}" aria-label="{{ $member->person->display_name }} auswählen"></td>
                                <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $member->person->display_name }}</p><p class="text-xs text-slate-500">{{ $member->person->email ?: 'Keine E-Mail' }}</p></td>
                                <td class="px-5 py-4 font-mono text-xs">{{ $member->member_number }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ ['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben'][$member->status] ?? $member->status }}</span></td>
                                <td class="px-5 py-4 text-slate-600">{{ $primary?->memberType?->name ?? $primary?->membership_type ?? '–' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $primary?->organizationUnit?->name ?? 'Nicht zugeordnet' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $member->joined_at?->format('d.m.Y') ?: '–' }}</td>
                                <td class="px-5 py-4 text-right"><div class="flex justify-end gap-3"><a class="font-semibold text-slate-600 hover:underline" href="{{ route('members.show', $member) }}">Details</a><a class="font-semibold text-blue-700 hover:underline" href="{{ route('members.crm', $member) }}">CRM</a></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-12 text-center"><p class="font-semibold">Keine Mitglieder gefunden</p><p class="mt-1 text-sm text-slate-500">Passen Sie die Filter an oder legen Sie das erste Mitglied an.</p></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($members->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $members->links() }}</div>@endif
            </section>
        </form>
    @else
        <section class="cv-panel mt-4 overflow-hidden">
            <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Nr.</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Austritt</th><th class="px-5 py-3 text-right">Aktion</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($members as $member)<tr><td class="px-5 py-4 font-semibold">{{ $member->person->display_name }}</td><td class="px-5 py-4 font-mono text-xs">{{ $member->member_number }}</td><td class="px-5 py-4">{{ $member->status }}</td><td class="px-5 py-4">{{ $member->left_at?->format('d.m.Y') ?: '—' }}</td><td class="px-5 py-4 text-right"><form method="post" action="{{ route('members.restore', $member->id) }}" class="inline">@csrf<button class="font-semibold text-blue-700 hover:underline">Wiederherstellen</button></form></td></tr>@empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Keine archivierten Mitglieder gefunden.</td></tr>@endforelse</tbody></table></div>
            @if($members->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $members->links() }}</div>@endif
        </section>
    @endif
</x-layouts.app>
