<x-layouts.app title="Mitglieder · Stammdaten">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Stammdaten & Datenaustausch</h1>
            <p class="mt-1 text-sm text-slate-500">Mitgliedsarten, Funktionen sowie CSV-Import und -Export zentral verwalten.</p>
        </div>
        <div class="flex gap-2"><a href="{{ route('members.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zur Mitgliederliste</a><a href="{{ route('members.export') }}" class="cv-button-primary">CSV exportieren</a></div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Mitgliedsarten</h2>
            <p class="mt-1 text-sm text-slate-500">Mandantenspezifische Kategorien für Mitgliedschaften.</p>
            <form method="post" action="{{ route('members.types.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Name *</span><input required class="cv-input" name="name" placeholder="z. B. Familienmitglied"></label>
                <label><span class="cv-label">Code</span><input class="cv-input" name="code" placeholder="FAMILIE"></label>
                <label><span class="cv-label">Sortierung</span><input type="number" min="0" class="cv-input" name="sort_order" value="100"></label>
                <label class="sm:col-span-2"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20 py-3" name="description"></textarea></label>
                <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Mitgliedsart anlegen</button></div>
            </form>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">@foreach($memberTypes as $type)<div class="flex items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $type->name }}</p><p class="text-xs text-slate-500">{{ $type->code ?: 'Kein Code' }} · Sortierung {{ $type->sort_order }}</p></div><form method="post" action="{{ route('members.types.toggle', $type) }}">@csrf @method('patch')<button class="text-sm font-semibold {{ $type->is_active ? 'text-amber-700' : 'text-emerald-700' }}">{{ $type->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form></div>@endforeach</div>
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Ämter & Funktionen</h2>
            <p class="mt-1 text-sm text-slate-500">Frei definierbarer Funktionskatalog für Vorstand, Abteilungen und weitere Ebenen.</p>
            <form method="post" action="{{ route('members.functions.definitions.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Bezeichnung *</span><input required class="cv-input" name="name" placeholder="z. B. Jugendwart"></label>
                <label><span class="cv-label">Kategorie</span><input class="cv-input" name="category" placeholder="z. B. Vorstand"></label>
                <label><span class="cv-label">Code</span><input class="cv-input" name="code" placeholder="JUGENDWART"></label>
                <label><span class="cv-label">Sortierung</span><input type="number" min="0" class="cv-input" name="sort_order" value="100"></label>
                <label class="sm:col-span-2"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20 py-3" name="description"></textarea></label>
                <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Funktion anlegen</button></div>
            </form>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">@foreach($functions as $function)<div class="flex items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $function->name }}</p><p class="text-xs text-slate-500">{{ $function->category ?: 'Ohne Kategorie' }}{{ $function->code ? ' · '.$function->code : '' }}</p></div><form method="post" action="{{ route('members.functions.definitions.toggle', $function) }}">@csrf @method('patch')<button class="text-sm font-semibold {{ $function->is_active ? 'text-amber-700' : 'text-emerald-700' }}">{{ $function->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form></div>@endforeach</div>
        </section>
    </div>

    <section class="cv-panel mt-5 p-5">
        <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
            <div><h2 class="text-lg font-bold">CSV-Import</h2><p class="mt-2 text-sm text-slate-500">Unterstützt Semikolon- und Komma-CSV. Pflichtspalten sind Vorname und Nachname. Bekannte Spalten: Mitgliedsnummer, E-Mail, Geburtsdatum, Status, Eintritt, Telefon, Mobil, Straße, PLZ, Ort. Mögliche Dubletten werden automatisch übersprungen.</p></div>
            <form method="post" action="{{ route('members.import') }}" enctype="multipart/form-data" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5">@csrf
                <label><span class="cv-label">CSV-Datei *</span><input required type="file" accept=".csv,.txt,text/csv" class="cv-input bg-white" name="file"></label>
                <div class="mt-4 flex justify-end"><button class="cv-button-primary">Import starten</button></div>
            </form>
        </div>
    </section>
</x-layouts.app>
