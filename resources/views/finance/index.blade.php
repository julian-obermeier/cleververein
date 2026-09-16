<x-layouts.app title="Beiträge & Finanzen">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Beiträge & Finanzen</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Finanz-Cockpit</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Beiträge, Rechnungen, offene Posten, Zahlungen, SEPA-Mandate und Mahnwesen zentral verwalten.</p>
        </div>
        @if($canManage)
            <form method="post" action="{{ route('finance.contributions.run') }}" class="flex items-end gap-2 rounded-xl border border-blue-100 bg-blue-50 p-3">@csrf
                <label><span class="cv-label">Beitragsjahr</span><input class="cv-input w-28 bg-white" type="number" min="2000" max="2100" name="year" value="{{ now()->year }}" required></label>
                <button class="cv-button-primary">Beitragslauf starten</button>
            </form>
        @endif
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Offene Forderungen</p><p class="mt-2 text-3xl font-bold">{{ number_format($metrics['open_amount'], 2, ',', '.') }} €</p><p class="mt-1 text-xs text-slate-400">{{ $metrics['open_count'] }} offene Rechnung(en)</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Überfällig</p><p class="mt-2 text-3xl font-bold {{ $metrics['overdue_count'] ? 'text-red-600' : '' }}">{{ $metrics['overdue_count'] }}</p><p class="mt-1 text-xs text-slate-400">überfällige Rechnung(en)</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Zahlungseingänge {{ now()->year }}</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($metrics['paid_this_year'], 2, ',', '.') }} €</p><p class="mt-1 text-xs text-slate-400">gebuchte Zahlungen</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Aktive Beitragssätze</p><p class="mt-2 text-3xl font-bold">{{ $rates->where('is_active', true)->count() }}</p><p class="mt-1 text-xs text-slate-400">{{ $rules->where('is_active', true)->count() }} aktive Regel(n)</p></div>
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
            <div><h2 class="text-lg font-bold">Rechnungen & offene Posten</h2><p class="text-sm text-slate-500">Entwürfe werden erst mit „Ausstellen“ verbindlich und erhalten dann eine Rechnungsnummer.</p></div>
            <form method="get" class="flex flex-wrap gap-2">
                <input class="cv-input min-w-56" name="q" value="{{ request('q') }}" placeholder="Nummer oder Mitglied suchen">
                <select class="cv-input" name="status"><option value="">Alle Status</option>@foreach(['draft'=>'Entwurf','open'=>'Offen','overdue'=>'Überfällig','paid'=>'Bezahlt','cancelled'=>'Storniert'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
                <button class="cv-button border border-slate-300 bg-white text-slate-700">Filtern</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Rechnung</th><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Datum / Fällig</th><th class="px-5 py-3 text-right">Betrag</th><th class="px-5 py-3 text-right">Offen</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoices as $invoice)
                        @php($labels=['draft'=>'Entwurf','open'=>'Offen','overdue'=>'Überfällig','paid'=>'Bezahlt','cancelled'=>'Storniert'])
                        <tr>
                            <td class="px-5 py-4"><p class="font-semibold">{{ $invoice->invoice_number ?: 'Entwurf #'.$invoice->id }}</p><p class="text-xs text-slate-400">{{ $invoice->created_at->format('d.m.Y H:i') }}</p></td>
                            <td class="px-5 py-4">{{ $invoice->member?->person?->display_name ?? 'Ohne Mitglied' }}@if($invoice->member)<p class="text-xs text-slate-400">{{ $invoice->member->member_number }}</p>@endif</td>
                            <td class="px-5 py-4">{{ $invoice->invoice_date?->format('d.m.Y') ?: '—' }}<p class="text-xs {{ $invoice->status === 'overdue' ? 'font-semibold text-red-600' : 'text-slate-400' }}">fällig {{ $invoice->due_date?->format('d.m.Y') ?: '—' }}</p></td>
                            <td class="px-5 py-4 text-right font-semibold">{{ number_format((float)$invoice->gross_amount, 2, ',', '.') }} €</td>
                            <td class="px-5 py-4 text-right">{{ number_format($invoice->open_amount, 2, ',', '.') }} €</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($invoice->status === 'overdue' ? 'bg-red-50 text-red-700' : ($invoice->status === 'draft' ? 'bg-slate-100 text-slate-600' : 'bg-blue-50 text-blue-700')) }}">{{ $labels[$invoice->status] ?? $invoice->status }}</span></td>
                            <td class="px-5 py-4 text-right"><a class="font-semibold text-blue-700" href="{{ route('finance.invoices.show', $invoice) }}">Öffnen</a></td>
                        </tr>
                    @empty<tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Noch keine Rechnungen vorhanden.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4">{{ $invoices->links() }}</div>
    </section>

    @if($canManage)
        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Manueller Rechnungsentwurf</h2>
                <p class="mt-1 text-sm text-slate-500">Für Sonderbeiträge, Gebühren, Leistungen oder sonstige Forderungen.</p>
                <form method="post" action="{{ route('finance.invoices.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                    <label class="sm:col-span-2"><span class="cv-label">Mitglied</span><select class="cv-input" name="member_id"><option value="">Ohne Mitglied</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select></label>
                    <label class="sm:col-span-2"><span class="cv-label">Position *</span><input required class="cv-input" name="description" placeholder="z. B. Sonderbeitrag Veranstaltung"></label>
                    <label><span class="cv-label">Menge *</span><input required type="number" step="0.01" min="0.01" class="cv-input" name="quantity" value="1"></label>
                    <label><span class="cv-label">Einzelpreis *</span><input required type="number" step="0.01" min="0" class="cv-input" name="unit_price"></label>
                    <label><span class="cv-label">Steuersatz %</span><input required type="number" step="0.01" min="0" max="100" class="cv-input" name="tax_rate" value="0"></label>
                    <label><span class="cv-label">Rechnungsdatum *</span><input required type="date" class="cv-input" name="invoice_date" value="{{ now()->toDateString() }}"></label>
                    <label><span class="cv-label">Fällig am *</span><input required type="date" class="cv-input" name="due_date" value="{{ now()->addDays(14)->toDateString() }}"></label>
                    <label class="sm:col-span-2"><span class="cv-label">Notiz</span><textarea class="cv-input min-h-20 py-3" name="notes"></textarea></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Entwurf anlegen</button></div>
                </form>
            </section>

            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Beitragssatz anlegen</h2>
                <p class="mt-1 text-sm text-slate-500">Ein Beitragssatz definiert Betrag und Abrechnungsintervall; Regeln entscheiden, für wen er gilt.</p>
                <form method="post" action="{{ route('finance.rates.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                    <label><span class="cv-label">Name *</span><input required class="cv-input" name="name" placeholder="Aktiver Jahresbeitrag"></label>
                    <label><span class="cv-label">Code *</span><input required class="cv-input" name="code" placeholder="AKTIV-JAHR"></label>
                    <label><span class="cv-label">Betrag *</span><input required type="number" step="0.01" min="0" class="cv-input" name="amount"></label>
                    <label><span class="cv-label">Intervall *</span><select class="cv-input" name="interval"><option value="yearly">Jährlich</option><option value="half_yearly">Halbjährlich</option><option value="quarterly">Vierteljährlich</option><option value="monthly">Monatlich</option><option value="once">Einmalig</option></select></label>
                    <label><span class="cv-label">Abrechnungsmonat</span><input type="number" min="1" max="12" class="cv-input" name="billing_month"></label>
                    <label class="sm:col-span-2"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20 py-3" name="description"></textarea></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Beitragssatz speichern</button></div>
                </form>
                <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">
                    @foreach($rates as $rate)<div class="flex items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $rate->name }} <span class="text-xs font-normal text-slate-400">{{ $rate->code }}</span></p><p class="text-sm text-slate-500">{{ number_format((float)$rate->amount,2,',','.') }} € · {{ $rate->interval }}</p></div><form method="post" action="{{ route('finance.rates.toggle', $rate) }}">@csrf @method('patch')<button class="text-sm font-semibold {{ $rate->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $rate->is_active ? 'Aktiv' : 'Inaktiv' }}</button></form></div>@endforeach
                </div>
            </section>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-2">
            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Beitragsregeln</h2>
                <p class="mt-1 text-sm text-slate-500">Niedrigere Prioritätszahl gewinnt. Leere Kriterien gelten für alle.</p>
                <form method="post" action="{{ route('finance.rules.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                    <label><span class="cv-label">Beitragssatz *</span><select required class="cv-input" name="contribution_rate_id"><option value="">Bitte wählen</option>@foreach($rates->where('is_active',true) as $rate)<option value="{{ $rate->id }}">{{ $rate->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Priorität *</span><input required type="number" min="1" max="9999" class="cv-input" name="priority" value="100"></label>
                    <label><span class="cv-label">Mitgliedsart</span><select class="cv-input" name="member_type_id"><option value="">Alle</option>@foreach($memberTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Organisation</span><select class="cv-input" name="organization_unit_id"><option value="">Alle</option>@foreach($organizationUnits as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Mindestalter</span><input type="number" min="0" max="120" class="cv-input" name="min_age"></label>
                    <label><span class="cv-label">Höchstalter</span><input type="number" min="0" max="120" class="cv-input" name="max_age"></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Regel speichern</button></div>
                </form>
                <div class="mt-5 space-y-2">
                    @foreach($rules as $rule)<div class="rounded-lg border border-slate-200 p-3 text-sm"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">#{{ $rule->priority }} · {{ $rule->rate?->name }}</p><p class="mt-1 text-slate-500">{{ $rule->memberType?->name ?? 'alle Mitgliedsarten' }} · {{ $rule->organizationUnit?->name ?? 'alle Organisationen' }} · Alter {{ $rule->min_age ?? '0' }}–{{ $rule->max_age ?? '∞' }}</p></div><form method="post" action="{{ route('finance.rules.toggle', $rule) }}">@csrf @method('patch')<button class="font-semibold {{ $rule->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $rule->is_active ? 'Aktiv' : 'Inaktiv' }}</button></form></div></div>@endforeach
                </div>
            </section>

            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Individuelle Beiträge & Befreiungen</h2>
                <p class="mt-1 text-sm text-slate-500">Überschreibt allgemeine Regeln für einen Zeitraum. Eine Befreiung erzeugt keinen Beitragsentwurf.</p>
                <form method="post" action="{{ route('finance.overrides.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                    <label class="sm:col-span-2"><span class="cv-label">Mitglied *</span><select required class="cv-input" name="member_id"><option value="">Bitte wählen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Beitragssatz</span><select class="cv-input" name="contribution_rate_id"><option value="">Allgemeine Regel verwenden</option>@foreach($rates as $rate)<option value="{{ $rate->id }}">{{ $rate->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Individueller Betrag</span><input type="number" step="0.01" min="0" class="cv-input" name="amount"></label>
                    <label><span class="cv-label">Gültig ab</span><input type="date" class="cv-input" name="valid_from"></label>
                    <label><span class="cv-label">Gültig bis</span><input type="date" class="cv-input" name="valid_until"></label>
                    <label class="sm:col-span-2 flex items-center gap-3 rounded-lg border border-slate-200 p-3"><input type="hidden" name="is_exempt" value="0"><input type="checkbox" name="is_exempt" value="1"><span class="text-sm font-medium">Vollständige Beitragsbefreiung</span></label>
                    <label class="sm:col-span-2"><span class="cv-label">Begründung *</span><input required class="cv-input" name="reason" placeholder="z. B. Vorstandsbeschluss, Ehrenmitglied"></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Ausnahme speichern</button></div>
                </form>
            </section>
        </div>
    @endif

    @if($canSepa)
        <section class="cv-panel mt-6 p-5">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between"><div><h2 class="text-lg font-bold">SEPA-Mandate</h2><p class="text-sm text-slate-500">IBAN/BIC werden verschlüsselt gespeichert und in Übersichten nur maskiert dargestellt.</p></div><span class="text-sm text-slate-500">{{ $sepaMandates->count() }} zuletzt aktive Mandate</span></div>
            <form method="post" action="{{ route('finance.sepa.store') }}" class="mt-5 grid gap-3 md:grid-cols-3">@csrf
                <label><span class="cv-label">Mitglied *</span><select required class="cv-input" name="member_id"><option value="">Bitte wählen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Mandatsreferenz *</span><input required class="cv-input" name="mandate_reference"></label>
                <label><span class="cv-label">Kontoinhaber *</span><input required class="cv-input" name="account_holder"></label>
                <label><span class="cv-label">IBAN *</span><input required class="cv-input" name="iban" autocomplete="off"></label>
                <label><span class="cv-label">BIC</span><input class="cv-input" name="bic" autocomplete="off"></label>
                <label><span class="cv-label">Unterschrieben am *</span><input required type="date" class="cv-input" name="signed_at" value="{{ now()->toDateString() }}"></label>
                <div class="md:col-span-3 flex justify-end"><button class="cv-button-primary">SEPA-Mandat speichern</button></div>
            </form>
            <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Mitglied</th><th class="px-4 py-3">Referenz</th><th class="px-4 py-3">Kontoinhaber</th><th class="px-4 py-3">IBAN</th><th class="px-4 py-3">Seit</th><th></th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($sepaMandates as $mandate)<tr><td class="px-4 py-3">{{ $mandate->member?->person?->display_name }}</td><td class="px-4 py-3 font-mono text-xs">{{ $mandate->mandate_reference }}</td><td class="px-4 py-3">{{ $mandate->account_holder }}</td><td class="px-4 py-3 font-mono text-xs">{{ $mandate->masked_iban }}</td><td class="px-4 py-3">{{ $mandate->signed_at->format('d.m.Y') }}</td><td class="px-4 py-3 text-right"><form method="post" action="{{ route('finance.sepa.revoke', $mandate) }}">@csrf @method('patch')<button class="font-semibold text-red-600">Widerrufen</button></form></td></tr>@empty<tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Keine aktiven SEPA-Mandate vorhanden.</td></tr>@endforelse</tbody></table></div>
        </section>
    @endif
</x-layouts.app>
