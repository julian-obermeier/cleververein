<x-layouts.app title="Finanzoperationen">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Beiträge & Finanzen</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Finanzoperationen</h1><p class="mt-2 max-w-3xl text-sm text-slate-500">Finanzstammdaten, Haushaltsbeiträge, SEPA-Dateien und Bankabgleich. Sensible Bankdaten werden verschlüsselt gespeichert.</p></div>
        <a class="cv-button border border-slate-300 bg-white text-slate-700" href="{{ route('finance.index') }}">← Finanz-Cockpit</a>
    </div>
    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($canManage)
    <section class="cv-panel mt-6 p-5">
        <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold">Finanzstammdaten</h2><p class="text-sm text-slate-500">Absenderdaten für Rechnungen und Gläubigerdaten für SEPA. Leere IBAN/BIC-Felder lassen bereits gespeicherte Werte unverändert.</p></div>@if($settings?->masked_iban)<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">IBAN {{ $settings->masked_iban }}</span>@endif</div>
        <form method="post" action="{{ route('finance.operations.settings') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf @method('put')
            <label class="xl:col-span-2"><span class="cv-label">Gläubiger-/Vereinsname *</span><input required class="cv-input" name="creditor_name" value="{{ old('creditor_name',$settings?->creditor_name ?? auth()->user()->currentTenant->name) }}"></label>
            <label><span class="cv-label">Straße</span><input class="cv-input" name="street" value="{{ old('street',$settings?->street) }}"></label>
            <label><span class="cv-label">PLZ / Ort</span><div class="flex gap-2"><input class="cv-input w-28" name="postal_code" value="{{ old('postal_code',$settings?->postal_code) }}"><input class="cv-input" name="city" value="{{ old('city',$settings?->city) }}"></div></label>
            <label><span class="cv-label">Land *</span><input required maxlength="2" class="cv-input" name="country" value="{{ old('country',$settings?->country ?? 'DE') }}"></label>
            <label><span class="cv-label">Steuernummer</span><input class="cv-input" name="tax_number" value="{{ old('tax_number',$settings?->tax_number) }}"></label>
            <label><span class="cv-label">USt-IdNr.</span><input class="cv-input" name="vat_id" value="{{ old('vat_id',$settings?->vat_id) }}"></label>
            <label><span class="cv-label">Zahlungsziel Tage *</span><input required type="number" min="1" max="180" class="cv-input" name="payment_terms_days" value="{{ old('payment_terms_days',$settings?->payment_terms_days ?? 14) }}"></label>
            <label class="xl:col-span-2"><span class="cv-label">SEPA Gläubiger-ID</span><input class="cv-input" name="creditor_id" value="{{ old('creditor_id',$settings?->creditor_id) }}" placeholder="DE98ZZZ09999999999"></label>
            <label><span class="cv-label">Gläubiger-IBAN</span><input class="cv-input" name="iban" autocomplete="off" placeholder="{{ $settings?->masked_iban ?: 'DE…' }}"></label>
            <label><span class="cv-label">Gläubiger-BIC</span><input class="cv-input" name="bic" autocomplete="off" placeholder="{{ $settings?->bic ? 'gespeichert – leer = unverändert' : 'optional' }}"></label>
            <label class="md:col-span-2 xl:col-span-4"><span class="cv-label">Rechnungsfußzeile</span><textarea class="cv-input min-h-20 py-3" name="invoice_footer">{{ old('invoice_footer',$settings?->invoice_footer) }}</textarea></label>
            <div class="md:col-span-2 xl:col-span-4 flex justify-end"><button class="cv-button-primary">Finanzstammdaten speichern</button></div>
        </form>
    </section>
    @endif

    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        @if($canManage)
        <section class="cv-panel p-5"><h2 class="text-lg font-bold">Haushalts-/Familienbeiträge</h2><p class="mt-1 text-sm text-slate-500">Erzeugt pro Haushalt höchstens einen Entwurf je Beitragsart und Jahr. Hauptkontakt wird Rechnungsempfänger.</p>
            <form method="post" action="{{ route('finance.households.run') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Haushaltsbeitrag *</span><select required class="cv-input" name="contribution_rate_id"><option value="">Bitte wählen</option>@foreach($householdRates as $rate)<option value="{{ $rate->id }}">{{ $rate->name }} · {{ number_format((float)$rate->amount,2,',','.') }} €</option>@endforeach</select></label>
                <label><span class="cv-label">Beitragsjahr *</span><input required type="number" min="2000" max="2100" class="cv-input" name="year" value="{{ now()->year }}"></label>
                <div class="sm:col-span-2 flex items-center justify-between"><span class="text-xs text-slate-500">{{ $households->count() }} Haushalt(e) vorhanden</span><button class="cv-button-primary" @disabled($householdRates->isEmpty())>Haushaltslauf starten</button></div>
            </form>
            @if($householdRates->isEmpty())<p class="mt-3 text-sm text-amber-700">Lege im Finanz-Cockpit zuerst einen Beitragssatz mit Typ „Haushalt/Familie“ an.</p>@endif
        </section>
        @endif

        @if($canSepa)
        <section class="cv-panel p-5"><h2 class="text-lg font-bold">SEPA-Lastschriftlauf</h2><p class="mt-1 text-sm text-slate-500">Erzeugt eine private pain.008.001.08-Datei aus offenen Rechnungen mit aktivem Mandat. Das Erzeugen bucht noch keine Lastschrift.</p>
            <form method="post" action="{{ route('finance.sepa.batches.store') }}" class="mt-5 flex flex-wrap items-end gap-3">@csrf<label class="flex-1"><span class="cv-label">Einzugsdatum *</span><input required type="date" min="{{ now()->toDateString() }}" class="cv-input" name="collection_date" value="{{ now()->addDays(5)->toDateString() }}"></label><button class="cv-button-primary">SEPA-Datei erzeugen</button></form>
            <div class="mt-5 space-y-2">@forelse($sepaBatches as $batch)<div class="rounded-lg border border-slate-200 p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-semibold">{{ $batch->batch_reference }}</p><p class="text-xs text-slate-500">{{ $batch->collection_date->format('d.m.Y') }} · {{ $batch->transaction_count }} Buchungen · {{ number_format((float)$batch->total_amount,2,',','.') }} € · {{ $batch->status }}</p></div><div class="flex gap-2">@if($batch->file_path)<a class="text-sm font-semibold text-blue-700" href="{{ route('finance.sepa.batches.download',$batch) }}">XML</a>@endif @if(in_array($batch->status,['generated','exported']))<form method="post" action="{{ route('finance.sepa.batches.submit',$batch) }}">@csrf @method('patch')<button class="text-sm font-semibold text-emerald-700">Als eingereicht markieren</button></form>@endif</div></div></div>@empty<p class="text-sm text-slate-500">Noch keine SEPA-Läufe vorhanden.</p>@endforelse</div>
        </section>
        @endif
    </div>

    @if($canBank)
    <section class="cv-panel mt-6 p-5"><div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><h2 class="text-lg font-bold">Bankimport & Zahlungsabgleich</h2><p class="text-sm text-slate-500">CSV mit Buchungstag, Betrag und Verwendungszweck; weitere erkannte Spalten: Valutadatum, Währung, Name Zahlungsbeteiligter und IBAN.</p></div><form method="post" enctype="multipart/form-data" action="{{ route('finance.bank.import') }}" class="flex flex-wrap items-end gap-2">@csrf<label><span class="cv-label">Bank-CSV</span><input required type="file" accept=".csv,.txt,text/csv" class="cv-input" name="bank_file"></label><button class="cv-button-primary">Importieren & abgleichen</button></form></div>
        <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[980px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Datum</th><th class="px-3 py-2">Zahler</th><th class="px-3 py-2">Verwendungszweck</th><th class="px-3 py-2 text-right">Betrag</th><th class="px-3 py-2">Zuordnen</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($unmatched as $transaction)<tr><td class="px-3 py-3">{{ $transaction->booking_date->format('d.m.Y') }}</td><td class="px-3 py-3">{{ $transaction->payer_name ?: '—' }}</td><td class="max-w-md px-3 py-3 text-slate-600">{{ $transaction->reference ?: '—' }}</td><td class="px-3 py-3 text-right font-semibold {{ (float)$transaction->amount < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format((float)$transaction->amount,2,',','.') }} €</td><td class="px-3 py-3">@if((float)$transaction->amount > 0)<form method="post" action="{{ route('finance.bank.assign',$transaction) }}" class="flex gap-2">@csrf<select required class="cv-input min-w-72" name="finance_invoice_id"><option value="">Rechnung wählen</option>@foreach($openInvoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · {{ $invoice->member?->person?->display_name }} · {{ number_format($invoice->open_amount,2,',','.') }} €</option>@endforeach</select><button class="cv-button-primary">Zuordnen</button></form>@endif<form method="post" action="{{ route('finance.bank.ignore',$transaction) }}" class="mt-1">@csrf @method('patch')<button class="text-xs font-semibold text-slate-500">Ignorieren</button></form></td></tr>@empty<tr><td colspan="5" class="px-3 py-7 text-center text-slate-500">Keine ungeklärten Bankumsätze.</td></tr>@endforelse</tbody></table></div>
        <div class="mt-5 flex flex-wrap gap-2">@foreach($bankBatches as $batch)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{{ $batch->original_name }} · {{ $batch->matched_count }}/{{ $batch->row_count }} zugeordnet</span>@endforeach</div>
    </section>
    @endif

    <section class="cv-panel mt-6 p-5"><h2 class="text-lg font-bold">Letzte Gutschriften</h2><div class="mt-4 divide-y divide-slate-100">@forelse($creditNotes as $credit)<div class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $credit->credit_number }} · {{ number_format((float)$credit->amount,2,',','.') }} €</p><p class="text-sm text-slate-500">{{ $credit->reason }}@if($credit->invoice) · Rechnung {{ $credit->invoice->invoice_number }}@endif</p></div><a class="text-sm font-semibold text-blue-700" href="{{ route('finance.credits.pdf',$credit) }}">PDF</a></div>@empty<p class="text-sm text-slate-500">Noch keine Gutschriften vorhanden.</p>@endforelse</div></section>
</x-layouts.app>
