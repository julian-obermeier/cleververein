<x-layouts.app title="Mitglieder · Stammdaten">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Stammdaten & Datenaustausch</h1>
            <p class="mt-1 text-sm text-slate-500">Mitgliedsarten, Funktionen, Tags, Zusatzfelder sowie Im- und Export zentral verwalten.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('members.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zur Mitgliederliste</a><a href="{{ route('members.segments.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Segmente</a><a href="{{ route('members.export.xlsx') }}" class="cv-button-primary">Excel exportieren</a></div>
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
            <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold">Ämter & Funktionen</h2><p class="mt-1 text-sm text-slate-500">Frei definierbarer Funktionskatalog für Vorstand, Abteilungen und weitere Ebenen.</p></div><a href="{{ route('members.functions.index') }}" class="text-sm font-semibold text-blue-700">Besetzungen ansehen</a></div>
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

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Tags</h2>
            <p class="mt-1 text-sm text-slate-500">Freie Kennzeichnungen für Mitglieder, Segmente und Massenaktionen.</p>
            <form method="post" action="{{ route('members.tags.store') }}" class="mt-5 flex flex-col gap-3 sm:flex-row">@csrf
                <label class="flex-1"><span class="cv-label">Bezeichnung *</span><input required class="cv-input" name="name" placeholder="z. B. Jubiläum 2027"></label>
                <label class="sm:w-40"><span class="cv-label">Farbcode</span><input class="cv-input" name="color" placeholder="#2563eb"></label>
                <div class="flex items-end"><button class="cv-button-primary">Tag anlegen</button></div>
            </form>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">@forelse($tags as $tag)<div class="flex items-center justify-between gap-3 py-3"><div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full border border-slate-300" style="background:{{ $tag->color ?: '#cbd5e1' }}"></span><p class="font-semibold">{{ $tag->name }}</p></div><form method="post" action="{{ route('members.tags.toggle', $tag) }}">@csrf @method('patch')<button class="text-sm font-semibold {{ $tag->is_active ? 'text-amber-700' : 'text-emerald-700' }}">{{ $tag->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form></div>@empty<p class="py-4 text-sm text-slate-500">Noch keine Tags angelegt.</p>@endforelse</div>
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Benutzerdefinierte Mitgliedsfelder</h2>
            <p class="mt-1 text-sm text-slate-500">Zusatzdaten ohne Datenbankumbau, z. B. T-Shirt-Größe, Qualifikation oder Datenschutzstatus.</p>
            <form method="post" action="{{ route('members.custom-fields.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Bezeichnung *</span><input required class="cv-input" name="name" placeholder="z. B. T-Shirt-Größe"></label>
                <label><span class="cv-label">Schlüssel</span><input class="cv-input" name="key" placeholder="wird automatisch erzeugt"></label>
                <label><span class="cv-label">Feldtyp *</span><select required class="cv-input" name="field_type"><option value="text">Text</option><option value="textarea">Mehrzeiliger Text</option><option value="number">Zahl</option><option value="date">Datum</option><option value="checkbox">Ja/Nein</option><option value="select">Auswahl</option></select></label>
                <label><span class="cv-label">Sortierung</span><input type="number" min="0" class="cv-input" name="sort_order" value="100"></label>
                <label class="sm:col-span-2"><span class="cv-label">Auswahloptionen</span><textarea class="cv-input min-h-20 py-3" name="options" placeholder="Nur für Auswahlfelder; eine Option pro Zeile oder kommagetrennt"></textarea></label>
                <label class="sm:col-span-2 flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3"><input type="checkbox" name="is_required" value="1"><span class="text-sm font-medium">Pflichtfeld</span></label>
                <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Zusatzfeld anlegen</button></div>
            </form>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">@forelse($customFields as $field)<div class="flex items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $field->name }}</p><p class="text-xs text-slate-500">{{ $field->key }} · {{ $field->field_type }}{{ $field->is_required ? ' · Pflichtfeld' : '' }}</p></div><form method="post" action="{{ route('members.custom-fields.toggle', $field) }}">@csrf @method('patch')<button class="text-sm font-semibold {{ $field->is_active ? 'text-amber-700' : 'text-emerald-700' }}">{{ $field->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form></div>@empty<p class="py-4 text-sm text-slate-500">Noch keine Zusatzfelder definiert.</p>@endforelse</div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5">
            <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold">CSV-Import</h2><p class="mt-2 text-sm text-slate-500">Unterstützt Semikolon- und Komma-CSV. Pflichtspalten sind Vorname und Nachname. Mögliche Dubletten werden automatisch übersprungen.</p></div><a href="{{ route('members.export') }}" class="text-sm font-semibold text-blue-700">CSV exportieren</a></div>
            <form method="post" action="{{ route('members.import') }}" enctype="multipart/form-data" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5">@csrf
                <label><span class="cv-label">CSV-Datei *</span><input required type="file" accept=".csv,.txt,text/csv" class="cv-input bg-white" name="file"></label>
                <div class="mt-4 flex justify-end"><button class="cv-button-primary">CSV importieren</button></div>
            </form>
        </section>

        <section class="cv-panel p-5">
            <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold">Excel-Import</h2><p class="mt-2 text-sm text-slate-500">Für umfangreichere Datenmigrationen. Unterstützt XLSX/XLS mit derselben Spaltenstruktur wie der Export.</p></div><a href="{{ route('members.import.template.xlsx') }}" class="text-sm font-semibold text-blue-700">Importvorlage</a></div>
            <form method="post" action="{{ route('members.import.xlsx') }}" enctype="multipart/form-data" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5">@csrf
                <label><span class="cv-label">Excel-Datei *</span><input required type="file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="cv-input bg-white" name="file"></label>
                <div class="mt-4 flex items-center justify-between gap-3"><a href="{{ route('members.export.xlsx') }}" class="text-sm font-semibold text-slate-600">Excel exportieren</a><button class="cv-button-primary">Excel importieren</button></div>
            </form>
        </section>
    </div>

    <section class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
        <h2 class="font-bold">Unterstützte Im-/Exportspalten</h2>
        <p class="mt-1">Mitgliedsnummer, Vorname, Nachname, E-Mail, Geburtsdatum, Status, Eintritt, Organisation, Mitgliedsart, Telefon, Mobil, Straße, PLZ und Ort. Datumswerte können als <code>YYYY-MM-DD</code> oder <code>TT.MM.JJJJ</code> angegeben werden.</p>
    </section>
</x-layouts.app>
