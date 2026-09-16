<x-layouts.app title="Finanzjournal & Berichte">
    @php($months=[1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember'])
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Beiträge & Finanzen</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Finanzjournal & Berichte</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Unveränderliches Einnahmen-/Ausgabenjournal mit Konten, Kategorien, Stornobuchungen und periodischen Auswertungen. Automatische Rechnungzahlungen werden direkt übernommen.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('finance.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanz-Cockpit</a>
            <a href="{{ route('finance.operations.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanzoperationen</a>
            @if($canReports)<a href="{{ route('finance.ledger.export', request()->query()) }}" class="cv-button-primary">Journal CSV</a>@endif
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Einnahmen {{ $year }}</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format($metrics['income'],2,',','.') }} €</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ausgaben {{ $year }}</p><p class="mt-2 text-2xl font-bold text-red-600">{{ number_format($metrics['expense'],2,',','.') }} €</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ergebnis {{ $year }}</p><p class="mt-2 text-2xl font-bold {{ $metrics['result'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($metrics['result'],2,',','.') }} €</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Enthaltene Steuer</p><p class="mt-2 text-2xl font-bold">{{ number_format($metrics['tax'],2,',','.') }} €</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Buchungen</p><p class="mt-2 text-2xl font-bold">{{ number_format($metrics['count'],0,',','.') }}</p></div>
    </section>

    <form method="get" class="cv-panel mt-5 grid gap-3 p-4 md:grid-cols-3 xl:grid-cols-7">
        <label><span class="cv-label">Jahr</span><input class="cv-input" type="number" min="2000" max="2100" name="year" value="{{ $year }}"></label>
        <label><span class="cv-label">Monat</span><select class="cv-input" name="month"><option value="">Alle</option>@foreach($months as $number=>$label)<option value="{{ $number }}" @selected($month===$number)>{{ $label }}</option>@endforeach</select></label>
        <label><span class="cv-label">Art</span><select class="cv-input" name="direction"><option value="">Alle</option><option value="income" @selected(request('direction')==='income')>Einnahmen</option><option value="expense" @selected(request('direction')==='expense')>Ausgaben</option></select></label>
        <label><span class="cv-label">Konto</span><select class="cv-input" name="account"><option value="">Alle</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((int)request('account')===$account->id)>{{ $account->name }}</option>@endforeach</select></label>
        <label><span class="cv-label">Kategorie</span><select class="cv-input" name="category"><option value="">Alle</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)request('category')===$category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label class="xl:col-span-2"><span class="cv-label">Suche</span><div class="flex gap-2"><input class="cv-input" name="q" value="{{ request('q') }}" placeholder="BU-Nr., Beschreibung, Referenz …"><button class="cv-button-primary">Filtern</button></div></label>
    </form>

    @if($canManage)
    <section class="cv-panel mt-5 p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-bold">Neue Buchung</h2><p class="text-sm text-slate-500">Manuelle Einnahmen oder Ausgaben werden sofort verbindlich gebucht und erhalten eine fortlaufende Buchungsnummer.</p></div><span class="text-xs font-semibold text-amber-700">Korrekturen nur per Storno</span></div>
        <form method="post" action="{{ route('finance.ledger.entries.store') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-6">@csrf
            <label><span class="cv-label">Buchungsdatum *</span><input required type="date" class="cv-input" name="booking_date" value="{{ old('booking_date',now()->toDateString()) }}"></label>
            <label><span class="cv-label">Wertstellung</span><input type="date" class="cv-input" name="value_date" value="{{ old('value_date') }}"></label>
            <label><span class="cv-label">Art *</span><select required class="cv-input" name="direction"><option value="income" @selected(old('direction')==='income')>Einnahme</option><option value="expense" @selected(old('direction')==='expense')>Ausgabe</option></select></label>
            <label><span class="cv-label">Konto *</span><select required class="cv-input" name="finance_account_id"><option value="">Bitte wählen</option>@foreach($accounts->where('is_active',true) as $account)<option value="{{ $account->id }}" @selected((int)old('finance_account_id')===$account->id)>{{ $account->name }}</option>@endforeach</select></label>
            <label><span class="cv-label">Kategorie *</span><select required class="cv-input" name="finance_category_id"><option value="">Bitte wählen</option>@foreach($categories->where('is_active',true) as $category)<option value="{{ $category->id }}" @selected((int)old('finance_category_id')===$category->id)>{{ $category->direction==='income' ? 'Einnahme' : 'Ausgabe' }} · {{ $category->name }}</option>@endforeach</select></label>
            <label><span class="cv-label">Bruttobetrag *</span><div class="relative"><input required type="number" min="0.01" step="0.01" class="cv-input pr-8" name="gross_amount" value="{{ old('gross_amount') }}"><span class="absolute right-3 top-2.5 text-slate-400">€</span></div></label>
            <label><span class="cv-label">Steuersatz %</span><input type="number" min="0" max="100" step="0.01" class="cv-input" name="tax_rate" value="{{ old('tax_rate',0) }}"></label>
            <label><span class="cv-label">Mitglied optional</span><select class="cv-input" name="member_id"><option value="">Kein Mitglied</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((int)old('member_id')===$member->id)>{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
            <label class="md:col-span-2"><span class="cv-label">Beschreibung *</span><input required maxlength="255" class="cv-input" name="description" value="{{ old('description') }}" placeholder="z. B. Raummiete September"></label>
            <label class="md:col-span-2"><span class="cv-label">Referenz / Belegnummer</span><input maxlength="180" class="cv-input" name="reference" value="{{ old('reference') }}"></label>
            <label class="md:col-span-2 xl:col-span-6"><span class="cv-label">Interne Notiz</span><textarea maxlength="5000" class="cv-input min-h-20 py-3" name="notes">{{ old('notes') }}</textarea></label>
            <div class="md:col-span-2 xl:col-span-6 flex justify-end"><button class="cv-button-primary">Verbindlich buchen</button></div>
        </form>
    </section>
    @endif

    <section class="cv-panel mt-5 overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-bold">Finanzjournal</h2><p class="text-sm text-slate-500">{{ $entries->total() }} Buchungen im aktuellen Filter.</p></div><p class="text-xs text-slate-400">Negative Beträge kennzeichnen Gegenbuchungen.</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nr. / Datum</th><th class="px-4 py-3">Beschreibung</th><th class="px-4 py-3">Konto</th><th class="px-4 py-3">Kategorie</th><th class="px-4 py-3">Quelle</th><th class="px-4 py-3 text-right">Netto</th><th class="px-4 py-3 text-right">Steuer</th><th class="px-4 py-3 text-right">Brutto</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $entry)
                    <tr class="align-top {{ $entry->source_type==='reversal' ? 'bg-amber-50/50' : '' }}">
                        <td class="px-4 py-3"><p class="font-semibold">{{ $entry->entry_number }}</p><p class="text-xs text-slate-500">{{ $entry->booking_date->format('d.m.Y') }}</p></td>
                        <td class="px-4 py-3"><p class="font-medium">{{ $entry->description }}</p>@if($entry->reference)<p class="mt-1 text-xs text-slate-500">Ref. {{ $entry->reference }}</p>@endif @if($entry->member)<p class="mt-1 text-xs text-slate-500">{{ $entry->member->member_number }} · {{ $entry->member->person?->display_name }}</p>@endif</td>
                        <td class="px-4 py-3">{{ $entry->account?->name ?: '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $entry->direction==='income' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $entry->category?->name ?: ($entry->direction==='income'?'Einnahme':'Ausgabe') }}</span></td>
                        <td class="px-4 py-3"><p>{{ match($entry->source_type){'payment'=>'Rechnungzahlung','bank_import'=>'Bankimport','reversal'=>'Storno',default=>'Manuell'} }}</p>@if($entry->invoice)<a class="text-xs font-semibold text-blue-700" href="{{ route('finance.invoices.show',$entry->invoice) }}">{{ $entry->invoice->invoice_number }}</a>@endif</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float)$entry->net_amount,2,',','.') }} €</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float)$entry->tax_amount,2,',','.') }} €</td>
                        <td class="px-4 py-3 text-right font-bold {{ $entry->direction==='income' ? 'text-emerald-700' : 'text-red-600' }}">{{ $entry->direction==='expense' && (float)$entry->gross_amount>0 ? '− ' : '' }}{{ number_format(abs((float)$entry->gross_amount),2,',','.') }} €</td>
                        <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $entry->status==='reversed' ? 'Storniert' : 'Gebucht' }}</span></td>
                        <td class="px-4 py-3 text-right">@if($canManage && $entry->source_type==='manual' && $entry->status!=='reversed')<details class="inline-block text-left"><summary class="cursor-pointer text-xs font-semibold text-red-600">Storno</summary><form method="post" action="{{ route('finance.ledger.entries.reverse',$entry) }}" class="mt-2 w-64 rounded-lg border border-red-100 bg-white p-3 shadow-lg">@csrf<label><span class="cv-label">Stornogrund *</span><textarea required maxlength="500" class="cv-input min-h-20 py-2" name="reason"></textarea></label><button class="mt-2 text-xs font-bold text-red-700">Gegenbuchung erzeugen</button></form></details>@endif</td>
                    </tr>
                    @empty<tr><td colspan="10" class="px-5 py-10 text-center text-slate-500">Für diesen Filter sind noch keine Buchungen vorhanden.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4">{{ $entries->links() }}</div>
    </section>

    @if($canReports)
    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Monatsentwicklung {{ $year }}</h2><p class="mt-1 text-sm text-slate-500">Einnahmen, Ausgaben und Monatsergebnis auf Basis des Buchungsdatums.</p>
            @php($scale=max(1,max(array_map(fn($m)=>max(abs($m['income']),abs($m['expense'])), $monthly))))
            <div class="mt-5 space-y-3">@foreach($monthly as $number=>$row)<div class="grid grid-cols-[88px_1fr_110px] items-center gap-3"><span class="text-xs font-semibold text-slate-600">{{ substr($months[$number],0,3) }}</span><div class="space-y-1"><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100,abs($row['income'])/$scale*100) }}%"></div></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-red-400" style="width: {{ min(100,abs($row['expense'])/$scale*100) }}%"></div></div></div><span class="text-right text-xs font-semibold {{ $row['result']>=0?'text-emerald-700':'text-red-600' }}">{{ number_format($row['result'],2,',','.') }} €</span></div>@endforeach</div>
        </section>
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Kontostände</h2><p class="mt-1 text-sm text-slate-500">Eröffnungsbestand plus gebuchte Einnahmen minus Ausgaben bis zum Periodenende.</p>
            <div class="mt-4 divide-y divide-slate-100">@foreach($accountBalances as $row)<div class="flex items-center justify-between gap-4 py-3"><div><p class="font-semibold">{{ $row['account']->name }}</p><p class="text-xs text-slate-500">{{ $row['account']->code }} · {{ match($row['account']->type){'bank'=>'Bank','cash'=>'Kasse','clearing'=>'Verrechnung',default=>'Sonstiges'} }}</p></div><p class="text-lg font-bold {{ $row['balance']>=0?'text-slate-900':'text-red-600' }}">{{ number_format($row['balance'],2,',','.') }} €</p></div>@endforeach</div>
        </section>
    </div>

    <section class="cv-panel mt-5 p-5">
        <h2 class="text-lg font-bold">Kategorien {{ $year }}</h2><p class="mt-1 text-sm text-slate-500">Summen nach Einnahmen- und Ausgabenkategorien einschließlich Stornobuchungen.</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($categoryTotals as $row)<div class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><span class="font-semibold">{{ $row['name'] }}</span><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $row['direction']==='income'?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-700' }}">{{ $row['direction']==='income'?'Einnahme':'Ausgabe' }}</span></div><p class="mt-3 text-xl font-bold">{{ number_format($row['amount'],2,',','.') }} €</p></div>@empty<p class="text-sm text-slate-500">Noch keine Kategorienauswertung möglich.</p>@endforelse</div>
    </section>
    @endif

    @if($canManage)
    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5"><h2 class="text-lg font-bold">Finanzkonten</h2><p class="mt-1 text-sm text-slate-500">Bank-, Kassen- und Verrechnungskonten für das Journal.</p>
            <form method="post" action="{{ route('finance.ledger.accounts.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<label><span class="cv-label">Name *</span><input required class="cv-input" name="name"></label><label><span class="cv-label">Code *</span><input required class="cv-input" name="code" placeholder="z. B. BANK2"></label><label><span class="cv-label">Typ *</span><select class="cv-input" name="type"><option value="bank">Bank</option><option value="cash">Kasse</option><option value="clearing">Verrechnung</option><option value="other">Sonstiges</option></select></label><label><span class="cv-label">Währung *</span><input required maxlength="3" class="cv-input" name="currency" value="EUR"></label><label class="sm:col-span-2"><span class="cv-label">Eröffnungsbestand *</span><input required type="number" step="0.01" class="cv-input" name="opening_balance" value="0"></label><div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Konto anlegen</button></div></form>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">@foreach($accounts as $account)<div class="flex items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $account->name }} <span class="text-xs text-slate-400">{{ $account->code }}</span></p><p class="text-xs text-slate-500">Eröffnung {{ number_format((float)$account->opening_balance,2,',','.') }} € · {{ $account->is_active?'aktiv':'inaktiv' }}</p></div><form method="post" action="{{ route('finance.ledger.accounts.toggle',$account) }}">@csrf @method('patch')<button class="text-xs font-semibold {{ $account->is_active?'text-amber-700':'text-emerald-700' }}">{{ $account->is_active?'Deaktivieren':'Aktivieren' }}</button></form></div>@endforeach</div>
        </section>
        <section class="cv-panel p-5"><h2 class="text-lg font-bold">Buchungskategorien</h2><p class="mt-1 text-sm text-slate-500">Frei definierbare Kategorien für Auswertungen und manuelle Buchungen.</p>
            <form method="post" action="{{ route('finance.ledger.categories.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<label><span class="cv-label">Name *</span><input required class="cv-input" name="name"></label><label><span class="cv-label">Code *</span><input required class="cv-input" name="code" placeholder="z. B. MIETE"></label><label><span class="cv-label">Art *</span><select class="cv-input" name="direction"><option value="income">Einnahme</option><option value="expense">Ausgabe</option></select></label><label><span class="cv-label">Standard-Steuersatz %</span><input required type="number" min="0" max="100" step="0.01" class="cv-input" name="default_tax_rate" value="0"></label><div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Kategorie anlegen</button></div></form>
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">@foreach($categories as $category)<div class="flex items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $category->name }} <span class="text-xs text-slate-400">{{ $category->code }}</span></p><p class="text-xs text-slate-500">{{ $category->direction==='income'?'Einnahme':'Ausgabe' }} · {{ number_format((float)$category->default_tax_rate,2,',','.') }} % · {{ $category->is_active?'aktiv':'inaktiv' }}</p></div><form method="post" action="{{ route('finance.ledger.categories.toggle',$category) }}">@csrf @method('patch')<button class="text-xs font-semibold {{ $category->is_active?'text-amber-700':'text-emerald-700' }}">{{ $category->is_active?'Deaktivieren':'Aktivieren' }}</button></form></div>@endforeach</div>
        </section>
    </div>
    @endif
</x-layouts.app>
