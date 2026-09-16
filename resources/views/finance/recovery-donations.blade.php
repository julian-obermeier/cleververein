<x-layouts.app title="Spenden & Erstattungen">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Beiträge & Finanzen</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Spenden & Erstattungen</h1>
            <p class="mt-2 max-w-4xl text-sm text-slate-500">Rücklastschriften und Erstattungen als nachvollziehbare Zahlungskorrekturen sowie eigenständige Spendenverwaltung mit privaten Zuwendungsbestätigungen.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('finance.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanz-Cockpit</a>
            <a href="{{ route('finance.ledger.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanzjournal</a>
            <a href="{{ route('finance.operations.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Finanzstammdaten</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Zuwendungen {{ $year }}</p><p class="mt-2 text-2xl font-bold">{{ $metrics['donations'] }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Zuwendungssumme</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format($metrics['amount'],2,',','.') }} €</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aufwandsverzichte</p><p class="mt-2 text-2xl font-bold">{{ $metrics['expense_waivers'] }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Gültige Bestätigungen</p><p class="mt-2 text-2xl font-bold">{{ $metrics['certificates'] }}</p></div>
        <div class="cv-panel p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Zahlungskorrekturen</p><p class="mt-2 text-2xl font-bold">{{ $metrics['adjustments'] }}</p></div>
    </section>

    <div class="mt-5 rounded-xl border {{ $settings?->donation_receipt_ready ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4 text-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-bold {{ $settings?->donation_receipt_ready ? 'text-emerald-900' : 'text-amber-900' }}">Zuwendungsbestätigungen: {{ $settings?->donation_receipt_ready ? 'steuerliche Stammdaten vollständig' : 'noch nicht freigeschaltet' }}</p>
                <p class="mt-1 {{ $settings?->donation_receipt_ready ? 'text-emerald-800' : 'text-amber-800' }}">Ausstellung erfolgt nur mit aktivierter Funktion, vollständigen Bescheiddaten, gültigem Bescheiddatum und vollständiger Anschrift des Zuwendenden.</p>
            </div>
            <a href="{{ route('finance.operations.index') }}" class="shrink-0 font-semibold text-blue-700">Stammdaten prüfen →</a>
        </div>
    </div>

    @if($canDonate)
    <section class="cv-panel mt-5 p-5">
        <div><h2 class="text-lg font-bold">Zuwendung erfassen</h2><p class="mt-1 text-sm text-slate-500">Geldzuwendungen werden automatisch im Finanzjournal unter „Spenden“ gebucht. Ein Aufwandsverzicht erzeugt bewusst keinen Geldfluss.</p></div>
        <form method="post" action="{{ route('finance.donations.store') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-6">@csrf
            <label class="xl:col-span-2"><span class="cv-label">Zuwendender *</span><input required maxlength="180" class="cv-input" name="donor_name" value="{{ old('donor_name') }}"></label>
            <label><span class="cv-label">Mitglied optional</span><select class="cv-input" name="member_id"><option value="">Kein Mitglied</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((int)old('member_id')===$member->id)>{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
            <label><span class="cv-label">Art *</span><select required class="cv-input" name="donation_kind"><option value="money" @selected(old('donation_kind','money')==='money')>Geldzuwendung</option><option value="membership_contribution" @selected(old('donation_kind')==='membership_contribution')>Mitgliedsbeitrag</option><option value="expense_waiver" @selected(old('donation_kind')==='expense_waiver')>Verzicht auf Aufwendungsersatz</option></select></label>
            <label><span class="cv-label">Betrag *</span><div class="relative"><input required type="number" min="0.01" step="0.01" class="cv-input pr-8" name="amount" value="{{ old('amount') }}"><span class="absolute right-3 top-2.5 text-slate-400">€</span></div></label>
            <label><span class="cv-label">Datum *</span><input required type="date" class="cv-input" name="donation_date" value="{{ old('donation_date',now()->toDateString()) }}"></label>
            <label class="xl:col-span-2"><span class="cv-label">Straße</span><input maxlength="180" class="cv-input" name="donor_street" value="{{ old('donor_street') }}"></label>
            <label><span class="cv-label">PLZ</span><input maxlength="20" class="cv-input" name="donor_postal_code" value="{{ old('donor_postal_code') }}"></label>
            <label><span class="cv-label">Ort</span><input maxlength="120" class="cv-input" name="donor_city" value="{{ old('donor_city') }}"></label>
            <label><span class="cv-label">Land *</span><input required maxlength="2" class="cv-input" name="donor_country" value="{{ old('donor_country','DE') }}"></label>
            <label><span class="cv-label">E-Mail</span><input type="email" maxlength="180" class="cv-input" name="donor_email" value="{{ old('donor_email') }}"></label>
            <label class="xl:col-span-2"><span class="cv-label">Finanzkonto</span><select class="cv-input" name="finance_account_id"><option value="">Bei Aufwandsverzicht nicht erforderlich</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((int)old('finance_account_id')===$account->id)>{{ $account->name }}</option>@endforeach</select></label>
            <label class="xl:col-span-2"><span class="cv-label">Steuerbegünstigter Zweck *</span><input required maxlength="500" class="cv-input" name="purpose" value="{{ old('purpose') }}" placeholder="z. B. Förderung des Sports"></label>
            <label class="xl:col-span-2"><span class="cv-label">Referenz</span><input maxlength="180" class="cv-input" name="reference" value="{{ old('reference') }}" placeholder="Verwendungszweck / Bankreferenz"></label>
            <label class="md:col-span-2 xl:col-span-6"><span class="cv-label">Interne Notiz</span><textarea maxlength="5000" class="cv-input min-h-20 py-3" name="notes">{{ old('notes') }}</textarea></label>
            <div class="md:col-span-2 xl:col-span-6 flex justify-end"><button class="cv-button-primary">Zuwendung verbindlich erfassen</button></div>
        </form>
    </section>
    @endif

    @if($canAdjust)
    <section class="cv-panel mt-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Zahlungskorrekturen</h2><p class="mt-1 text-sm text-slate-500">Rücklastschriften öffnen eine Rechnung wieder. Erstattungen sind ausschließlich bis zur Höhe eines vorhandenen Rechnungsguthabens möglich.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1180px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Zahlung</th><th class="px-4 py-3">Rechnung / Mitglied</th><th class="px-4 py-3 text-right">Ursprünglich</th><th class="px-4 py-3 text-right">Korrigiert</th><th class="px-4 py-3 text-right">Wirksam</th><th class="px-4 py-3">Neue Korrektur</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($payments as $payment)
                <tr class="align-top"><td class="px-4 py-3"><p class="font-semibold">{{ $payment->paid_at->format('d.m.Y') }}</p><p class="text-xs text-slate-500">{{ $payment->reference ?: ucfirst($payment->method) }}</p></td><td class="px-4 py-3"><p class="font-semibold">{{ $payment->invoice?->invoice_number ?: '—' }}</p><p class="text-xs text-slate-500">{{ $payment->invoice?->member?->person?->display_name ?: '—' }}</p></td><td class="px-4 py-3 text-right font-semibold">{{ number_format((float)$payment->amount,2,',','.') }} €</td><td class="px-4 py-3 text-right">{{ number_format($payment->adjusted_amount,2,',','.') }} €</td><td class="px-4 py-3 text-right font-bold {{ $payment->effective_amount > 0 ? 'text-emerald-700' : 'text-slate-400' }}">{{ number_format($payment->effective_amount,2,',','.') }} €</td><td class="px-4 py-3">
                    @if($payment->effective_amount > 0)
                    <details><summary class="cursor-pointer font-semibold text-blue-700">Rücklastschrift / Erstattung erfassen</summary><form method="post" action="{{ route('finance.payment-adjustments.store',$payment) }}" class="mt-3 grid w-[520px] max-w-full grid-cols-2 gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-lg">@csrf
                        <label><span class="cv-label">Art *</span><select required class="cv-input" name="type"><option value="chargeback">Rücklastschrift</option><option value="refund">Erstattung</option></select></label>
                        <label><span class="cv-label">Datum *</span><input required type="date" class="cv-input" name="adjustment_date" value="{{ now()->toDateString() }}"></label>
                        <label><span class="cv-label">Betrag *</span><input required type="number" min="0.01" max="{{ $payment->effective_amount }}" step="0.01" class="cv-input" name="amount" value="{{ number_format($payment->effective_amount,2,'.','') }}"></label>
                        <label><span class="cv-label">Bankgebühr</span><input type="number" min="0" step="0.01" class="cv-input" name="fee_amount" value="0"></label>
                        <label class="col-span-2"><span class="cv-label">Grund *</span><input required maxlength="255" class="cv-input" name="reason" placeholder="z. B. Rückgabe mangels Deckung"></label>
                        <label class="col-span-2"><span class="cv-label">Referenz</span><input maxlength="180" class="cv-input" name="reference"></label>
                        <div class="col-span-2 flex justify-end"><button class="cv-button-primary">Verbindlich buchen</button></div>
                    </form></details>
                    @else<span class="text-xs text-slate-400">vollständig korrigiert</span>@endif
                </td></tr>
            @empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Noch keine Rechnungzahlungen vorhanden.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endif

    <section class="cv-panel mt-5 overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-bold">Zuwendungen</h2><p class="mt-1 text-sm text-slate-500">{{ $donations->total() }} Einträge im gewählten Jahr.</p></div><form method="get" class="flex gap-2"><input type="number" min="2000" max="2100" class="cv-input w-28" name="year" value="{{ $year }}"><input class="cv-input" name="donation_q" value="{{ request('donation_q') }}" placeholder="Spender, Nummer, Zweck …"><button class="cv-button-primary">Filtern</button></form></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nr. / Datum</th><th class="px-4 py-3">Zuwendender</th><th class="px-4 py-3">Art / Zweck</th><th class="px-4 py-3 text-right">Betrag</th><th class="px-4 py-3">Journal</th><th class="px-4 py-3">Zuwendungsbestätigung</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($donations as $donation)
                @php($certificate=$donation->active_certificate)
                @php($latestCertificate=$donation->certificates->sortByDesc('id')->first())
                <tr class="align-top"><td class="px-4 py-3"><p class="font-semibold">{{ $donation->donation_number }}</p><p class="text-xs text-slate-500">{{ $donation->donation_date->format('d.m.Y') }}</p></td><td class="px-4 py-3"><p class="font-medium">{{ $donation->donor_name }}</p><p class="text-xs text-slate-500">{{ trim(($donation->donor_postal_code ?? '').' '.($donation->donor_city ?? '')) ?: 'keine vollständige Anschrift' }}</p>@if($donation->member)<p class="mt-1 text-xs text-blue-700">{{ $donation->member->member_number }} · {{ $donation->member->person?->display_name }}</p>@endif</td><td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ match($donation->donation_kind){'membership_contribution'=>'Mitgliedsbeitrag','expense_waiver'=>'Aufwandsverzicht',default=>'Geldzuwendung'} }}</span><p class="mt-2 max-w-sm text-slate-600">{{ $donation->purpose }}</p></td><td class="px-4 py-3 text-right text-base font-bold text-emerald-700">{{ number_format((float)$donation->amount,2,',','.') }} €</td><td class="px-4 py-3">@if($donation->entry)<a class="font-semibold text-blue-700" href="{{ route('finance.ledger.index',['q'=>$donation->entry->entry_number]) }}">{{ $donation->entry->entry_number }}</a>@elseif($donation->expense_waiver)<span class="text-xs text-slate-500">kein Geldfluss</span>@else<span>—</span>@endif</td><td class="px-4 py-3">
                    @if($certificate)
                        <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $certificate->certificate_number }}</span><a class="text-xs font-semibold text-blue-700" href="{{ route('finance.donation-certificates.pdf',$certificate) }}">PDF</a></div>
                        @if($canCertificates)<details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-red-600">Bestätigung stornieren</summary><form method="post" action="{{ route('finance.donation-certificates.void',$certificate) }}" class="mt-2 flex gap-2">@csrf @method('patch')<input required maxlength="500" class="cv-input" name="reason" placeholder="Stornogrund"><button class="text-xs font-bold text-red-700">Storno</button></form></details>@endif
                    @else
                        @if($latestCertificate?->status === 'voided')<div class="mb-2"><a class="text-xs font-semibold text-red-600" href="{{ route('finance.donation-certificates.pdf',$latestCertificate) }}">{{ $latestCertificate->certificate_number }} · storniert</a></div>@endif
                        @if($canCertificates)<form method="post" action="{{ route('finance.donations.certificates.issue',$donation) }}">@csrf<button class="text-sm font-semibold text-blue-700">Bestätigung ausstellen</button></form>@else<span class="text-xs text-slate-400">nicht ausgestellt</span>@endif
                    @endif
                </td></tr>
            @empty<tr><td colspan="6" class="px-5 py-9 text-center text-slate-500">Für dieses Jahr wurden noch keine Zuwendungen erfasst.</td></tr>@endforelse
        </tbody></table></div>
        <div class="border-t border-slate-200 px-5 py-4">{{ $donations->links() }}</div>
    </section>

    <section class="cv-panel mt-5 p-5"><h2 class="text-lg font-bold">Letzte Rücklastschriften & Erstattungen</h2><div class="mt-4 divide-y divide-slate-100">@forelse($adjustments as $adjustment)<div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">{{ $adjustment->type==='chargeback' ? 'Rücklastschrift' : 'Erstattung' }} · {{ number_format((float)$adjustment->amount,2,',','.') }} €</p><p class="text-sm text-slate-500">{{ $adjustment->adjustment_date->format('d.m.Y') }} · {{ $adjustment->invoice?->invoice_number }} · {{ $adjustment->invoice?->member?->person?->display_name }} · {{ $adjustment->reason }}</p></div><div class="text-right text-xs text-slate-500">@if((float)$adjustment->fee_amount>0)<p>Gebühr {{ number_format((float)$adjustment->fee_amount,2,',','.') }} €</p>@endif @if($adjustment->reversalEntry)<p>{{ $adjustment->reversalEntry->entry_number }}</p>@endif</div></div>@empty<p class="text-sm text-slate-500">Noch keine Zahlungskorrekturen vorhanden.</p>@endforelse</div></section>
</x-layouts.app>
