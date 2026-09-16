<x-layouts.app title="Mitglieder · Segmente">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Segmente</h1>
            <p class="mt-1 text-sm text-slate-500">Dynamische Zielgruppen anhand gespeicherter Filterkriterien.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('members.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Mitgliederliste</a><a href="{{ route('members.settings') }}" class="cv-button-primary">Stammdaten</a></div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,.8fr)]">
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Gespeicherte Segmente</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @forelse($segments as $segment)
                    <article class="rounded-xl border border-slate-200 p-4 {{ $segment->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-4"><div><h3 class="font-bold">{{ $segment->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $segment->description ?: 'Keine Beschreibung' }}</p></div><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{{ $segment->member_count }} Mitglieder</span></div>
                        <dl class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-500">
                            @foreach($segment->criteria ?? [] as $key => $value)<div><dt class="font-semibold">{{ $key }}</dt><dd>{{ $value }}</dd></div>@endforeach
                        </dl>
                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3"><a href="{{ route('members.segments.show', $segment) }}" class="text-sm font-semibold text-blue-700">Mitglieder anzeigen</a><form method="post" action="{{ route('members.segments.toggle', $segment) }}">@csrf @method('patch')<button class="text-sm font-semibold {{ $segment->is_active ? 'text-amber-700' : 'text-emerald-700' }}">{{ $segment->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form></div>
                    </article>
                @empty
                    <div class="md:col-span-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">Noch keine Segmente angelegt.</div>
                @endforelse
            </div>
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Neues Segment</h2>
            <p class="mt-1 text-sm text-slate-500">Alle gesetzten Kriterien werden mit UND verknüpft.</p>
            <form method="post" action="{{ route('members.segments.store') }}" class="mt-5 grid gap-3">@csrf
                <label><span class="cv-label">Name *</span><input required class="cv-input" name="name" placeholder="z. B. Jugendmitglieder Gießen"></label>
                <label><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20 py-3" name="description"></textarea></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label><span class="cv-label">Mitgliedsstatus</span><select class="cv-input" name="status"><option value="">Alle</option><option value="active">Aktiv</option><option value="pending">Vorgemerkt</option><option value="inactive">Inaktiv</option><option value="resigned">Ausgetreten</option><option value="deceased">Verstorben</option></select></label>
                    <label><span class="cv-label">Tag</span><select class="cv-input" name="member_tag_id"><option value="">Alle</option>@foreach($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Organisation</span><select class="cv-input" name="organization_unit_id"><option value="">Alle</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Mitgliedsart</span><select class="cv-input" name="member_type_id"><option value="">Alle</option>@foreach($memberTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Eintritt ab</span><input type="date" class="cv-input" name="joined_from"></label>
                    <label><span class="cv-label">Eintritt bis</span><input type="date" class="cv-input" name="joined_to"></label>
                </div>
                <div class="flex justify-end"><button class="cv-button-primary">Segment speichern</button></div>
            </form>
        </section>
    </div>
</x-layouts.app>
