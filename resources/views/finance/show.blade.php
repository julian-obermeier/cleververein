<x-layouts.app title="Rechnung">
    @php($labels=['draft'=>'Entwurf','open'=>'Offen','overdue'=>'Überfällig','paid'=>'Bezahlt','cancelled'=>'Storniert'])
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Beiträge & Finanzen</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $invoice->invoice_number ?: 'Rechnungsentwurf #'.$invoice->id }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $invoice->member?->person?->display_name ?? 'Ohne Mitglied' }}@if($invoice->member) · {{ $invoice->member->member_number }}@endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('finance.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zurück zum Finanz-Cockpit</a>
            @if($canManage && $invoice->status === 'draft')<form method="post" action="{{ route('finance.invoices.issue', $invoice) }}">@csrf<button class="cv-button-primary">Rechnung ausstellen</button></form>@endif
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="cv-panel p-4"><p class="text-xs uppercase text-slate-400">Status</p><p class="mt-2 font-bold {{ $invoice->status === 'overdue' ? 'text-red-600' : '' }}">{{ $labels[$invoice->status] ?? $invoice->status }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs uppercase text-slate-400">Rechnungsdatum</p><p class="mt-2 font-bold">{{ $invoice->invoice_date?->format('d.m.Y') ?: '—' }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs uppercase text-slate-400">Fällig am</p><p class="mt-2 font-bold">{{ $invoice->due_date?->format('d.m.Y') ?: '—' }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs uppercase text-slate-400">Gesamt</p><p class="mt-2 font-bold">{{ number_format((float)$invoice->gross_amount,2,',','.') }} €</p></div>
        <div class="cv-panel p-4"><p class="text-xs uppercase text-slate-400">Noch offen</p><p class="mt-2 font-bold {{ $invoice->open_amount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($invoice->open_amount,2,',','.') }} €</p></div>
    </div>

    <section class="cv-panel mt-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Rechnungspositionen</h2></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Beschreibung</th><th class="px-5 py-3 text-right">Menge</th><th class="px-5 py-3 text-right">Einzelpreis</th><th class="px-5 py-3 text-right">Steuer</th><th class="px-5 py-3 text-right">Gesamt</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($invoice->items as $item)<tr><td class="px-5 py-4"><p class="font-semibold">{{ $item->description }}</p>@if($item->contributionRate)<p class="text-xs text-slate-400">Beitragssatz: {{ $item->contributionRate->name }}</p>@endif</td><td class="px-5 py-4 text-right">{{ number_format((float)$item->quantity,2,',','.') }}</td><td class="px-5 py-4 text-right">{{ number_format((float)$item->unit_price,2,',','.') }} €</td><td class="px-5 py-4 text-right">{{ number_format((float)$item->tax_rate,2,',','.') }} %</td><td class="px-5 py-4 text-right font-semibold">{{ number_format((float)$item->gross_amount,2,',','.') }} €</td></tr>@endforeach</tbody><tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold"><tr><td colspan="4" class="px-5 py-4 text-right">Gesamtbetrag</td><td class="px-5 py-4 text-right">{{ number_format((float)$invoice->gross_amount,2,',','.') }} €</td></tr></tfoot></table></div>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Zahlungen</h2>
            @if($canManage && !in_array($invoice->status,['draft','cancelled'],true))
                <form method="post" action="{{ route('finance.invoices.payments.store', $invoice) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    <label><span class="cv-label">Betrag *</span><input required type="number" step="0.01" min="0.01" class="cv-input" name="amount" value="{{ number_format($invoice->open_amount,2,'.','') }}"></label>
                    <label><span class="cv-label">Zahlungsdatum *</span><input required type="date" class="cv-input" name="paid_at" value="{{ now()->toDateString() }}"></label>
                    <label><span class="cv-label">Zahlungsart *</span><select class="cv-input" name="method"><option value="bank_transfer">Überweisung</option><option value="sepa">SEPA-Lastschrift</option><option value="cash">Bar</option><option value="card">Karte</option><option value="other">Sonstiges</option></select></label>
                    <label><span class="cv-label">Referenz</span><input class="cv-input" name="reference"></label>
                    <label class="sm:col-span-2"><span class="cv-label">Notiz</span><textarea class="cv-input min-h-20 py-3" name="notes"></textarea></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Zahlung verbuchen</button></div>
                </form>
            @endif
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">
                @forelse($invoice->payments->sortByDesc('paid_at') as $payment)<div class="flex items-start justify-between gap-4 py-4"><div><p class="font-semibold">{{ number_format((float)$payment->amount,2,',','.') }} €</p><p class="text-xs text-slate-500">{{ $payment->paid_at->format('d.m.Y') }} · {{ $payment->method }}@if($payment->reference) · {{ $payment->reference }}@endif</p></div><span class="text-xs text-slate-400">{{ $payment->recorder?->name ?? 'System' }}</span></div>@empty<p class="py-5 text-sm text-slate-500">Noch keine Zahlungen gebucht.</p>@endforelse
            </div>
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Mahnwesen</h2>
            <p class="mt-1 text-sm text-slate-500">Mahnstufen werden historisch dokumentiert. Eine Mahngebühr verändert den ursprünglichen Rechnungsbetrag nicht automatisch.</p>
            @if($canDunning && in_array($invoice->status,['open','overdue'],true))
                <form method="post" action="{{ route('finance.invoices.dunnings.store', $invoice) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    <label><span class="cv-label">Mahnstufe *</span><select class="cv-input" name="level"><option value="1">1. Zahlungserinnerung</option><option value="2">2. Mahnung</option><option value="3">3. Mahnung</option></select></label>
                    <label><span class="cv-label">Mahndatum *</span><input required type="date" class="cv-input" name="dunned_at" value="{{ now()->toDateString() }}"></label>
                    <label><span class="cv-label">Mahngebühr</span><input required type="number" min="0" step="0.01" class="cv-input" name="fee" value="0"></label>
                    <label class="sm:col-span-2"><span class="cv-label">Notiz</span><textarea class="cv-input min-h-20 py-3" name="notes"></textarea></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Mahnung dokumentieren</button></div>
                </form>
            @endif
            <div class="mt-5 space-y-3">
                @forelse($invoice->dunnings->sortByDesc('dunned_at') as $dunning)<div class="rounded-xl border border-slate-200 p-4"><div class="flex items-start justify-between gap-4"><div><p class="font-semibold">Mahnstufe {{ $dunning->level }}</p><p class="text-xs text-slate-500">{{ $dunning->dunned_at->format('d.m.Y') }} · Gebühr {{ number_format((float)$dunning->fee,2,',','.') }} €</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $dunning->status }}</span></div>@if($dunning->notes)<p class="mt-2 text-sm text-slate-600">{{ $dunning->notes }}</p>@endif</div>@empty<p class="text-sm text-slate-500">Noch keine Mahnvorgänge vorhanden.</p>@endforelse
            </div>
        </section>
    </div>

    @if($invoice->notes)<section class="cv-panel mt-5 p-5"><h2 class="text-lg font-bold">Interne Notiz</h2><p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $invoice->notes }}</p></section>@endif
</x-layouts.app>
