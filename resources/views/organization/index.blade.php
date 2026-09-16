<x-layouts.app title="Organisation">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Phase 2</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Organisation</h1>
            <p class="mt-1 text-sm text-slate-500">Vereine, Verbände und Untergliederungen beliebig tief abbilden und verwalten.</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ $units->count() }} Einheit(en) · {{ $types->count() }} Typ(en)</div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(320px,.75fr)_minmax(0,1.25fr)]">
        <div class="space-y-4">
            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Organisationstyp anlegen</h2>
                <p class="mt-1 text-sm text-slate-500">Zusätzlich zu den vorbereiteten Verbandsebenen können eigene Typen verwendet werden.</p>
                <form method="post" action="{{ route('organization.types.store') }}" class="mt-5 grid gap-4 sm:grid-cols-[1fr_120px]">@csrf
                    <label><span class="cv-label">Bezeichnung *</span><input required class="cv-input" name="name" placeholder="z. B. Regionalgruppe"></label>
                    <label><span class="cv-label">Sortierung</span><input type="number" min="0" class="cv-input" name="sort_order" value="100"></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Typ anlegen</button></div>
                </form>
            </section>

            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Organisationseinheit anlegen</h2>
                <form method="post" action="{{ route('organization.units.store') }}" class="mt-5 grid gap-4">@csrf
                    <label><span class="cv-label">Typ *</span><select required class="cv-input" name="organization_type_id"><option value="">Bitte wählen</option>@foreach($types->where('is_active', true) as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Übergeordnete Einheit</span><select class="cv-input" name="parent_id"><option value="">Keine · oberste Ebene</option>@foreach($rows as $row)<option value="{{ $row['unit']->id }}">{{ str_repeat('— ', $row['depth']) }}{{ $row['unit']->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Name *</span><input required class="cv-input" name="name"></label>
                    <label><span class="cv-label">Kurzname</span><input class="cv-input" name="short_name"></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label><span class="cv-label">Status *</span><select class="cv-input" name="status"><option value="active">Aktiv</option><option value="planned">Geplant</option><option value="inactive">Inaktiv</option></select></label>
                        <label><span class="cv-label">Gründungsdatum</span><input type="date" class="cv-input" name="founded_at"></label>
                    </div>
                    <div class="flex justify-end"><button class="cv-button-primary">Einheit anlegen</button></div>
                </form>
            </section>
        </div>

        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Organisationsstruktur</h2><p class="mt-1 text-sm text-slate-500">Die Einrückung zeigt die Hierarchie. Elternknoten können beim Bearbeiten geändert werden.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    @php($unit = $row['unit'])
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4 hover:bg-slate-50">
                            <span class="text-slate-400 transition group-open:rotate-90">›</span>
                            <div class="min-w-0 flex-1" style="padding-left: {{ min($row['depth'] * 18, 126) }}px">
                                <div class="flex flex-wrap items-center gap-2"><span class="font-semibold">{{ $unit->name }}</span>@if($unit->short_name)<span class="text-xs text-slate-500">{{ $unit->short_name }}</span>@endif<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $unit->type?->name }}</span></div>
                                <p class="mt-1 text-xs text-slate-500">{{ $unit->memberships_count }} aktive Mitgliedschaft(en) · Status {{ $unit->status }}</p>
                            </div>
                        </summary>
                        <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-5">
                            <form method="post" action="{{ route('organization.units.update', $unit) }}" class="grid gap-4 lg:grid-cols-2">@csrf @method('put')
                                <label><span class="cv-label">Typ</span><select class="cv-input" name="organization_type_id">@foreach($types as $type)<option value="{{ $type->id }}" @selected($unit->organization_type_id === $type->id)>{{ $type->name }}</option>@endforeach</select></label>
                                <label><span class="cv-label">Übergeordnete Einheit</span><select class="cv-input" name="parent_id"><option value="">Keine · oberste Ebene</option>@foreach($rows as $parentRow)@if($parentRow['unit']->id !== $unit->id)<option value="{{ $parentRow['unit']->id }}" @selected($unit->parent_id === $parentRow['unit']->id)>{{ str_repeat('— ', $parentRow['depth']) }}{{ $parentRow['unit']->name }}</option>@endif @endforeach</select></label>
                                <label><span class="cv-label">Name</span><input required class="cv-input" name="name" value="{{ $unit->name }}"></label>
                                <label><span class="cv-label">Kurzname</span><input class="cv-input" name="short_name" value="{{ $unit->short_name }}"></label>
                                <label><span class="cv-label">Status</span><select class="cv-input" name="status"><option value="active" @selected($unit->status==='active')>Aktiv</option><option value="planned" @selected($unit->status==='planned')>Geplant</option><option value="inactive" @selected($unit->status==='inactive')>Inaktiv</option></select></label>
                                <label><span class="cv-label">Gründungsdatum</span><input type="date" class="cv-input" name="founded_at" value="{{ $unit->founded_at?->format('Y-m-d') }}"></label>
                                <input type="hidden" name="dissolved_at" value="{{ $unit->dissolved_at?->format('Y-m-d') }}">
                                <div class="lg:col-span-2 flex flex-wrap justify-end gap-2"><button class="cv-button-primary">Änderungen speichern</button></div>
                            </form>
                            <form method="post" action="{{ route('organization.units.archive', $unit) }}" class="mt-3 flex justify-end" onsubmit="return confirm('Organisationseinheit wirklich archivieren?');">@csrf @method('delete')<button class="text-sm font-semibold text-red-700 hover:underline">Einheit archivieren</button></form>
                        </div>
                    </details>
                @empty
                    <div class="px-5 py-12 text-center"><p class="font-semibold">Noch keine Organisationseinheiten</p><p class="mt-1 text-sm text-slate-500">Legen Sie links die erste Einheit an.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
