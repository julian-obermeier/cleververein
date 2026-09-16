<x-layouts.app title="Bankabgleich">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-sm font-semibold text-blue-700">Finanzen · Zahlungsverkehr</p><h1 class="text-2xl font-bold tracking-tight">Bankabgleich & CAMT</h1><p class="mt-1 max-w-3xl text-sm text-slate-500">CSV, CAMT.053 und CAMT.054 importieren, Zahlungseingänge zuordnen und sichere SEPA-Rücklastschriften automatisch gegen bereits eingereichte Lastschriftläufe abgleichen.</p></div>
        <a href="{{ route('finance.operations.index') }}" class="cv-button-secondary">Finanzoperationen</a>
    </div>

    @if(session('success'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="cv-panel mt-5 p-5">
        <div class="grid gap-5 lg:grid-cols-[1fr_420px] lg:items-center">
            <div><h2 class="text-lg font-bold">Bankdatei importieren</h2><p class="mt-1 text-sm text-slate-500">Unterstützt werden bisherige CSV-Dateien sowie CAMT.053-Kontoauszüge und CAMT.054-Umsatzbenachrichtigungen. XML-Namespaces werden versionsunabhängig verarbeitet.</p><p class="mt-3 text-xs text-slate-500">Automatische Rücklastschrift nur bei exakter SEPA-EndToEnd-ID und identischem Betrag. Unsichere Belastungen bleiben ungeklärt.</p></div>
            <form method="post" enctype="multipart/form-data" action="{{ route('finance.bank.import') }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4">@csrf
                <label><span class="cv-label">CSV- oder CAMT-Datei *</span><input required type="file" accept=".csv,.txt,.xml,text/csv,application/xml,text/xml" class="cv-input bg-white" name="bank_file"></label>
                <button class="cv-button-primary mt-3 w-full">Importieren & automatisch abgleichen</button>
            </form>
        </div>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <section class="cv-panel p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Importe</p><p class="mt-2 text-3xl font-bold">{{ $batches->count() }}</p><p class="text-xs text-slate-500">letzte 40 Dateien</p></section>
        <section class="cv-panel p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ungeklärt</p><p class="mt-2 text-3xl font-bold text-amber-700">{{ $unmatched->count() }}</p><p class="text-xs text-slate-500">manuelle Prüfung erforderlich</p></section>
        <section class="cv-panel p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Auto-Rücklastschriften</p><p class="mt-2 text-3xl font-bold text-red-700">{{ $chargebacks->count() }}</p><p class="text-xs text-slate-500">letzte 80 sichere Treffer</p></section>
    </div>

    <section class="cv-panel mt-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Importhistorie</h2><p class="text-sm text-slate-500">Dateityp, CAMT-Nachrichtenkennung und Verarbeitungsergebnis.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Import</th><th class="px-4 py-3">Typ</th><th class="px-4 py-3">Message-ID</th><th class="px-4 py-3 text-right">Umsätze</th><th class="px-4 py-3 text-right">Automatisch</th><th class="px-4 py-3 text-right">Ungeklärt</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($batches as $batch)<tr><td class="px-4 py-3"><p class="font-semibold">{{ $batch->original_name }}</p><p class="text-xs text-slate-500">{{ $batch->created_at->format('d.m.Y H:i') }}</p></td><td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ match($batch->file_type){'camt053'=>'CAMT.053','camt054'=>'CAMT.054',default=>'CSV'} }}</span></td><td class="px-4 py-3 text-xs text-slate-600">{{ $batch->message_id ?: '—' }}</td><td class="px-4 py-3 text-right font-semibold">{{ $batch->transactions_count }}</td><td class="px-4 py-3 text-right"><span class="text-emerald-700">{{ $batch->matched_count }}</span>@if($batch->chargeback_count)<span class="block text-xs text-red-700">davon {{ $batch->chargeback_count }} Rücklast.</span>@endif</td><td class="px-4 py-3 text-right {{ $batch->unmatched_count ? 'font-bold text-amber-700' : 'text-slate-500' }}">{{ $batch->unmatched_count }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Noch keine Bankdateien importiert.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="cv-panel mt-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Ungeklärte Umsätze</h2><p class="text-sm text-slate-500">Positive Zahlungseingänge können einer offenen Rechnung zugeordnet werden. Negative Umsätze werden bewusst nicht heuristisch gebucht.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1200px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Datum</th><th class="px-4 py-3">Gegenpartei</th><th class="px-4 py-3">Referenzen</th><th class="px-4 py-3">CAMT</th><th class="px-4 py-3 text-right">Betrag</th><th class="px-4 py-3">Aktion</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($unmatched as $transaction)<tr class="align-top"><td class="px-4 py-3">{{ $transaction->booking_date->format('d.m.Y') }}<p class="text-xs text-slate-400">{{ $transaction->batch?->file_type }}</p></td><td class="px-4 py-3"><p class="font-semibold">{{ $transaction->payer_name ?: '—' }}</p>@if($transaction->payer_iban)<p class="text-xs text-slate-500">IBAN gespeichert</p>@endif</td><td class="px-4 py-3"><p class="max-w-md break-words">{{ $transaction->reference ?: '—' }}</p>@if($transaction->end_to_end_id)<p class="mt-1 text-xs font-semibold text-slate-500">E2E: {{ $transaction->end_to_end_id }}</p>@endif</td><td class="px-4 py-3 text-xs"><p>{{ $transaction->bank_transaction_code ?: '—' }}</p>@if($transaction->return_reason_code)<p class="mt-1 font-semibold text-red-700">{{ $transaction->return_reason_code }} {{ $transaction->return_reason_text }}</p>@endif</td><td class="px-4 py-3 text-right font-bold {{ (float)$transaction->amount < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ number_format((float)$transaction->amount,2,',','.') }} €</td><td class="px-4 py-3">
                @if((float)$transaction->amount > 0)
                    <form method="post" action="{{ route('finance.bank.assign',$transaction) }}" class="flex min-w-[310px] gap-2">@csrf<select required class="cv-input py-2 text-xs" name="finance_invoice_id"><option value="">Offene Rechnung wählen</option>@foreach($openInvoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · {{ $invoice->member?->person?->display_name }} · {{ number_format($invoice->open_amount,2,',','.') }} €</option>@endforeach</select><button class="text-xs font-bold text-blue-700">Zuordnen</button></form>
                @else
                    <span class="text-xs font-semibold text-amber-700">Manuell prüfen</span>
                @endif
            </td></tr>@empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Keine ungeklärten Umsätze.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="cv-panel mt-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Automatisch verarbeitete Rücklastschriften</h2><p class="text-sm text-slate-500">Nur exakte SEPA-EndToEnd-/Betragstreffer. Die Zahlung bleibt historisch bestehen; die Korrektur ist separat im Journal gebucht.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Datum</th><th class="px-4 py-3">EndToEnd-ID</th><th class="px-4 py-3">Rückgabegrund</th><th class="px-4 py-3">Rechnung</th><th class="px-4 py-3 text-right">Betrag</th><th class="px-4 py-3">Nachweis</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($chargebacks as $transaction)<tr><td class="px-4 py-3">{{ $transaction->booking_date->format('d.m.Y') }}</td><td class="px-4 py-3 font-mono text-xs">{{ $transaction->end_to_end_id }}</td><td class="px-4 py-3"><strong>{{ $transaction->return_reason_code ?: '—' }}</strong><p class="text-xs text-slate-500">{{ $transaction->return_reason_text ?: $transaction->match_reason }}</p></td><td class="px-4 py-3">{{ $transaction->invoice?->invoice_number ?: '—' }}</td><td class="px-4 py-3 text-right font-bold text-red-700">{{ number_format(abs((float)$transaction->amount),2,',','.') }} €</td><td class="px-4 py-3 text-xs">{{ $transaction->adjustments->first()?->public_id ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Noch keine automatisch verarbeitete Rücklastschrift.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
