<x-layouts.app title="Kasse & Prüfung">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Beiträge & Finanzen</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Kasse & Prüfung</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Private Belegablage, Kassenbuch, Kassenabschlüsse, Periodensperren und dokumentierte Kassenprüfungen auf Basis des unveränderlichen Finanzjournals.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('finance.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanz-Cockpit</a>
            <a href="{{ route('finance.ledger.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanzjournal</a>
            <a href="{{ route('finance.operations.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanzoperationen</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aktueller Kassenstand</p><p class="mt-2 text-2xl font-bold">{{ $selectedCash ? number_format((float)$cashBalance,2,',','.') . ' €' : '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ $selectedCash?->name ?? 'Kein Kassenkonto vorhanden' }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aktive Periodensperren</p><p class="mt-2 text-2xl font-bold">{{ $periodLocks->where('status','locked')->count() }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kassenabschlüsse</p><p class="mt-2 text-2xl font-bold">{{ $closings->count() }}</p><p class="mt-1 text-xs text-slate-500">letzte 40 angezeigt</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aktive Belege</p><p class="mt-2 text-2xl font-bold">{{ $receiptEntries->sum(fn($entry)=>$entry->receipts->where('status','active')->count()) }}</p><p class="mt-1 text-xs text-slate-500">zu den angezeigten Buchungen</p></div>
    </section>

    <section class="cv-panel mt-5 p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div><h2 class="text-lg font-bold">Belegablage</h2><p class="text-sm text-slate-500">Belege liegen außerhalb des öffentlichen Webroots und werden nur nach Berechtigungsprüfung ausgeliefert. Ungültige Belege bleiben als Nachweis erhalten.</p></div>
            <form method="get" class="flex w-full max-w-md gap-2"><input class="cv-input" name="receipt_q" value="{{ request('receipt_q') }}" placeholder="BU-Nr., Beschreibung, Referenz …"><button class="cv-button-primary">Suchen</button></form>
        </div>
        <div class="mt-5 space-y-3">
            @forelse($receiptEntries as $entry)
                <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4" @if(request('receipt_q')) open @endif>
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div><span class="font-bold">{{ $entry->entry_number }}</span><span class="ml-2 text-sm text-slate-500">{{ $entry->booking_date->format('d.m.Y') }} · {{ $entry->description }}</span></div>
                            <div class="flex items-center gap-2"><span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-600">{{ $entry->account?->name }}</span><span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $entry->receipts->where('status','active')->count() }} Beleg(e)</span></div>
                        </div>
                    </summary>
                    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_420px]">
                        <div class="space-y-2">
                            @forelse($entry->receipts as $receipt)
                                <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between {{ $receipt->status==='voided' ? 'opacity-60' : '' }}">
                                    <div><p class="text-sm font-semibold">{{ $receipt->original_name }}</p><p class="text-xs text-slate-500">{{ number_format($receipt->size/1024,1,',','.') }} KB @if($receipt->document_date) · Belegdatum {{ $receipt->document_date->format('d.m.Y') }} @endif · {{ $receipt->status==='voided' ? 'ungültig' : 'aktiv' }}</p>@if($receipt->notes)<p class="mt-1 text-xs text-slate-500">{{ $receipt->notes }}</p>@endif @if($receipt->void_reason)<p class="mt-1 text-xs font-medium text-red-600">Grund: {{ $receipt->void_reason }}</p>@endif</div>
                                    <div class="flex gap-2"><a class="text-xs font-bold text-blue-700" href="{{ route('finance.receipts.download',$receipt) }}">Download</a>@if($canReceipts && $receipt->status==='active')<details class="relative"><summary class="cursor-pointer text-xs font-bold text-red-600">Ungültig</summary><form method="post" action="{{ route('finance.receipts.void',$receipt) }}" class="absolute right-0 z-20 mt-2 w-72 rounded-lg border border-red-100 bg-white p-3 shadow-xl">@csrf @method('patch')<label><span class="cv-label">Begründung *</span><textarea required maxlength="500" class="cv-input min-h-20 py-2" name="reason"></textarea></label><button class="mt-2 text-xs font-bold text-red-700">Markierung bestätigen</button></form></details>@endif</div>
                                </div>
                            @empty<p class="text-sm text-slate-500">Noch kein Beleg zu dieser Buchung.</p>@endforelse
                        </div>
                        @if($canReceipts)
                        <form method="post" enctype="multipart/form-data" action="{{ route('finance.receipts.store',$entry) }}" class="rounded-lg border border-dashed border-slate-300 bg-white p-4">@csrf
                            <p class="text-sm font-bold">Beleg hinzufügen</p>
                            <label class="mt-3 block"><span class="cv-label">Datei *</span><input required type="file" class="cv-input py-2" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"></label>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2"><label><span class="cv-label">Belegdatum</span><input type="date" class="cv-input" name="document_date"></label><label><span class="cv-label">Hinweis</span><input maxlength="2000" class="cv-input" name="notes"></label></div>
                            <button class="cv-button-primary mt-3">Sicher hochladen</button>
                        </form>
                        @endif
                    </div>
                </details>
            @empty<p class="py-8 text-center text-sm text-slate-500">Keine passenden Buchungen gefunden.</p>@endforelse
        </div>
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-2">
        <section class="cv-panel p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-bold">Kassenbuch & Abschluss</h2><p class="text-sm text-slate-500">Nach einem Abschluss sind Buchungen bis einschließlich Abschlussdatum für dieses Kassenkonto gesperrt.</p></div>@if($cashAccounts->isNotEmpty())<form method="get"><select class="cv-input" name="cash_account" onchange="this.form.submit()">@foreach($cashAccounts as $account)<option value="{{ $account->id }}" @selected($selectedCash?->id===$account->id)>{{ $account->name }}</option>@endforeach</select></form>@endif</div>
            @if($selectedCash)
                <div class="mt-4 rounded-xl bg-slate-900 p-4 text-white"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Systembestand heute</p><p class="mt-1 text-3xl font-bold">{{ number_format((float)$cashBalance,2,',','.') }} €</p></div>
                @if($canCash)
                <form method="post" action="{{ route('finance.cash.closings.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="finance_account_id" value="{{ $selectedCash->id }}"><label><span class="cv-label">Abschlussdatum *</span><input required type="date" max="{{ now()->toDateString() }}" class="cv-input" name="closing_date" value="{{ now()->toDateString() }}"></label><label><span class="cv-label">Gezählter Bestand *</span><input required type="number" min="0" step="0.01" class="cv-input" name="counted_balance"></label><label class="sm:col-span-2"><span class="cv-label">Notiz</span><textarea maxlength="5000" class="cv-input min-h-20 py-2" name="notes"></textarea></label><div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Kassenabschluss speichern</button></div></form>
                @endif
                @if($canReports)<form method="get" action="{{ route('finance.cash.export') }}" class="mt-4 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-3"><input type="hidden" name="finance_account_id" value="{{ $selectedCash->id }}"><label><span class="cv-label">Von</span><input required type="date" class="cv-input" name="period_start" value="{{ now()->startOfYear()->toDateString() }}"></label><label><span class="cv-label">Bis</span><input required type="date" class="cv-input" name="period_end" value="{{ now()->toDateString() }}"></label><div class="flex items-end"><button class="cv-button border border-slate-300 bg-white text-slate-700">Kassenbuch CSV</button></div></form>@endif
            @else<p class="mt-5 text-sm text-slate-500">Es ist noch kein Kassenkonto vorhanden. Lege im Finanzjournal ein Konto vom Typ „Kasse“ an.</p>@endif
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Kassenabschlüsse</h2><p class="mt-1 text-sm text-slate-500">Systembestand und tatsächlich gezählter Bargeldbestand werden dauerhaft gegenübergestellt.</p>
            <div class="mt-4 space-y-2">@forelse($closings as $closing)<div class="rounded-lg border border-slate-200 p-3"><div class="flex items-center justify-between gap-3"><div><p class="font-semibold">{{ $closing->account?->name }} · {{ $closing->closing_date->format('d.m.Y') }}</p><p class="text-xs text-slate-500">System {{ number_format((float)$closing->system_balance,2,',','.') }} € · gezählt {{ number_format((float)$closing->counted_balance,2,',','.') }} €</p></div><span class="rounded-full px-2 py-1 text-xs font-bold {{ abs((float)$closing->difference)<0.01 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">Differenz {{ number_format((float)$closing->difference,2,',','.') }} €</span></div>@if($closing->notes)<p class="mt-2 text-xs text-slate-500">{{ $closing->notes }}</p>@endif</div>@empty<p class="text-sm text-slate-500">Noch keine Kassenabschlüsse vorhanden.</p>@endforelse</div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-2">
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Periodensperren</h2><p class="mt-1 text-sm text-slate-500">Gesperrte Zeiträume akzeptieren serverseitig keine neuen Buchungen oder Stornos mit Buchungsdatum in diesem Zeitraum.</p>
            @if($canPeriods)<form method="post" action="{{ route('finance.periods.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<label><span class="cv-label">Von *</span><input required type="date" class="cv-input" name="period_start"></label><label><span class="cv-label">Bis *</span><input required type="date" class="cv-input" name="period_end"></label><label class="sm:col-span-2"><span class="cv-label">Grund *</span><input required maxlength="500" class="cv-input" name="reason" placeholder="z. B. Jahresabschluss 2026"></label><div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Periode sperren</button></div></form>@endif
            <div class="mt-5 space-y-2">@forelse($periodLocks as $lock)<div class="rounded-lg border border-slate-200 p-3"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">{{ $lock->period_start->format('d.m.Y') }} – {{ $lock->period_end->format('d.m.Y') }}</p><p class="text-xs text-slate-500">{{ $lock->reason }}</p></div><span class="rounded-full px-2 py-1 text-xs font-bold {{ $lock->status==='locked' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ $lock->status==='locked' ? 'Gesperrt' : 'Geöffnet' }}</span></div>@if($canPeriods && $lock->status==='locked')<details class="mt-2"><summary class="cursor-pointer text-xs font-bold text-blue-700">Kontrolliert wieder öffnen</summary><form method="post" action="{{ route('finance.periods.unlock',$lock) }}" class="mt-2 flex gap-2">@csrf @method('patch')<input required maxlength="500" class="cv-input" name="reason" placeholder="Begründung für Wiederöffnung"><button class="cv-button border border-slate-300 bg-white text-slate-700">Öffnen</button></form></details>@endif</div>@empty<p class="text-sm text-slate-500">Noch keine Periodensperren vorhanden.</p>@endforelse</div>
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Kassenprüfung</h2><p class="mt-1 text-sm text-slate-500">Prüfzeitraum, Buchungsanzahl, Soll-/Istbestand und Feststellungen werden dauerhaft protokolliert.</p>
            @if($canAudit)<form method="post" action="{{ route('finance.cash-audits.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<label><span class="cv-label">Kassenkonto</span><select class="cv-input" name="finance_account_id"><option value="">Alle Finanzbuchungen</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></label><label><span class="cv-label">Ergebnis *</span><select required class="cv-input" name="result"><option value="passed">Ohne Beanstandung</option><option value="issues">Mit Feststellungen</option></select></label><label><span class="cv-label">Von *</span><input required type="date" class="cv-input" name="period_start"></label><label><span class="cv-label">Bis *</span><input required type="date" class="cv-input" name="period_end"></label><label><span class="cv-label">Gezählter Bestand optional</span><input type="number" min="0" step="0.01" class="cv-input" name="counted_balance"></label><label class="sm:col-span-2"><span class="cv-label">Feststellungen / Prüfvermerk</span><textarea maxlength="10000" class="cv-input min-h-24 py-2" name="findings"></textarea></label><div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Prüfung dokumentieren</button></div></form>@endif
            <div class="mt-5 space-y-2">@forelse($cashAudits as $audit)<div class="rounded-lg border border-slate-200 p-3"><div class="flex items-center justify-between gap-3"><div><p class="font-semibold">{{ $audit->period_start->format('d.m.Y') }} – {{ $audit->period_end->format('d.m.Y') }} · {{ $audit->account?->name ?? 'Gesamtprüfung' }}</p><p class="text-xs text-slate-500">{{ $audit->entry_count }} Buchungen @if($audit->difference!==null) · Differenz {{ number_format((float)$audit->difference,2,',','.') }} € @endif</p></div><span class="rounded-full px-2 py-1 text-xs font-bold {{ $audit->result==='passed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $audit->result==='passed' ? 'Ohne Beanstandung' : 'Feststellungen' }}</span></div>@if($audit->findings)<p class="mt-2 whitespace-pre-line text-xs text-slate-600">{{ $audit->findings }}</p>@endif</div>@empty<p class="text-sm text-slate-500">Noch keine Kassenprüfung dokumentiert.</p>@endforelse</div>
        </section>
    </div>
</x-layouts.app>
