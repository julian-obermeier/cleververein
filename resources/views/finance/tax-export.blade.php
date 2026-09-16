<x-layouts.app title="Steuerberater-Export">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold text-blue-700">Finanzen · Übergabe</p><h1 class="text-2xl font-bold tracking-tight">Steuerberater-Export</h1><p class="mt-1 max-w-3xl text-sm text-slate-500">Kontenzuordnung und prüfbare CSV-Arbeitsdatei für Steuerberatung bzw. DATEV-nahe Weiterverarbeitung.</p></div>
        <a href="{{ route('finance.ledger.index') }}" class="cv-button-secondary">Zum Finanzjournal</a>
    </div>

    @if(session('success'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Bitte prüfen:</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="cv-panel mt-5 border-amber-200 bg-amber-50/50 p-5">
        <h2 class="font-bold text-amber-950">Prüfbarer Übergabeexport – kein zertifizierter DATEV-Direktimport</h2>
        <p class="mt-1 text-sm text-amber-900">Die Arbeitsdatei enthält Betrag, Soll/Haben, Konto, Gegenkonto und Beleginformationen. Kontenrahmen und Kontierungen müssen mit der Steuerberatung abgestimmt werden. cleververein behauptet für diese Datei keine DATEV-Zertifizierung.</p>
    </section>

    <form method="post" action="{{ route('finance.tax-export.mapping') }}" class="mt-5 space-y-5">
        @csrf @method('put')
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Kanzlei- und Exportstammdaten</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label><span class="cv-label">Beraternummer</span><input class="cv-input" name="datev_consultant_number" inputmode="numeric" value="{{ old('datev_consultant_number',$settings?->datev_consultant_number) }}"></label>
                <label><span class="cv-label">Mandantennummer</span><input class="cv-input" name="datev_client_number" inputmode="numeric" value="{{ old('datev_client_number',$settings?->datev_client_number) }}"></label>
                <label><span class="cv-label">Kontenrahmen</span><input class="cv-input" name="datev_chart" placeholder="z. B. SKR03 / SKR04" value="{{ old('datev_chart',$settings?->datev_chart) }}"></label>
                <label><span class="cv-label">Kontenlänge</span><input required type="number" min="4" max="8" class="cv-input" name="datev_account_length" value="{{ old('datev_account_length',$settings?->datev_account_length ?? 4) }}"></label>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Finanzkonten → Gegenkonto</h2><p class="text-sm text-slate-500">Bank, Kasse oder Verrechnung werden als Gegenkonto der Arbeitsdatei ausgegeben.</p></div>
                <div class="divide-y divide-slate-100">
                    @foreach($accounts as $account)
                        <label class="grid grid-cols-[1fr_150px] items-center gap-4 px-5 py-3"><span><strong>{{ $account->name }}</strong><span class="block text-xs text-slate-500">{{ $account->code }} · {{ $account->type }}</span></span><input class="cv-input" inputmode="numeric" name="account_map[{{ $account->id }}]" placeholder="Konto" value="{{ old('account_map.'.$account->id,$account->datev_account) }}"></label>
                    @endforeach
                </div>
            </section>

            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Kategorien → Sachkonto</h2><p class="text-sm text-slate-500">Jede im Zeitraum verwendete Einnahmen-/Ausgabenkategorie benötigt ein Sachkonto.</p></div>
                <div class="divide-y divide-slate-100">
                    @foreach($categories as $category)
                        <label class="grid grid-cols-[1fr_150px] items-center gap-4 px-5 py-3"><span><strong>{{ $category->name }}</strong><span class="block text-xs text-slate-500">{{ $category->code }} · {{ $category->direction==='income'?'Einnahme':'Ausgabe' }}</span></span><input class="cv-input" inputmode="numeric" name="category_map[{{ $category->id }}]" placeholder="Sachkonto" value="{{ old('category_map.'.$category->id,$category->datev_account) }}"></label>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="flex justify-end"><button class="cv-button-primary">Zuordnung speichern</button></div>
    </form>

    <section class="cv-panel mt-5 p-5">
        <h2 class="text-lg font-bold">Arbeitsdatei erzeugen</h2><p class="mt-1 text-sm text-slate-500">Vor dem Export werden alle im Zeitraum tatsächlich verwendeten Konten und Kategorien auf vollständige Zuordnung geprüft.</p>
        <form method="get" action="{{ route('finance.tax-export.csv') }}" class="mt-4 grid gap-4 sm:grid-cols-[180px_220px_auto] sm:items-end">
            <label><span class="cv-label">Jahr</span><input required type="number" min="2000" max="2100" class="cv-input" name="year" value="{{ $year }}"></label>
            <label><span class="cv-label">Monat</span><select class="cv-input" name="month"><option value="">Ganzes Jahr</option>@foreach(range(1,12) as $number)<option value="{{ $number }}" @selected($month===$number)>{{ str_pad((string)$number,2,'0',STR_PAD_LEFT) }}</option>@endforeach</select></label>
            <button class="cv-button-primary">Steuerberater-CSV herunterladen</button>
        </form>
    </section>
</x-layouts.app>
