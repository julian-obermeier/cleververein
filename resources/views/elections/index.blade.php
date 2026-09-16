<x-layouts.app title="Wahlen & Delegierte">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Verbandsarbeit</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Wahlen & Delegiertenverwaltung</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Wahlberechtigung, Kandidaturen, Wahlgänge und Delegiertenmandate nachvollziehbar verwalten. Geheime Wahlen speichern ausschließlich aggregierte Ergebnisse.</p>
        </div>
        <a href="{{ route('governance.index') }}" class="cv-button border border-slate-300 bg-white">Gremien & Sitzungen</a>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Wahlen</p><p class="mt-1 text-3xl font-bold">{{ $elections->count() }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Offene Wahlen</p><p class="mt-1 text-3xl font-bold">{{ $elections->where('status', 'open')->count() }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Aktive Mandate</p><p class="mt-1 text-3xl font-bold">{{ $mandates->where('status', 'active')->count() }}</p></div>
    </div>

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.45fr)_minmax(380px,.55fr)]">
        <div class="space-y-5">
            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Wahlen</h2><p class="mt-1 text-sm text-slate-500">Aktuelle und vergangene Wahlvorgänge.</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse($elections as $election)
                        <a href="{{ route('elections.show', $election) }}" class="grid gap-3 px-5 py-4 transition hover:bg-slate-50 lg:grid-cols-[minmax(0,1fr)_170px_180px] lg:items-center">
                            <div>
                                <p class="font-semibold text-slate-950">{{ $election->title }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $election->organizationUnit?->name ?: 'Mandantenweit' }}@if($election->governanceMeeting) · {{ $election->governanceMeeting->title }}@endif</p>
                            </div>
                            <div class="text-sm text-slate-600">{{ $election->election_date->format('d.m.Y') }}<br><span class="text-xs">{{ $election->offices_count }} Ämter · {{ $election->voters_count }} Wahlberechtigte</span></div>
                            <div><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $election->status === 'finalized' ? 'bg-emerald-100 text-emerald-800' : ($election->status === 'open' ? 'bg-blue-100 text-blue-800' : ($election->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700')) }}">{{ ['draft'=>'Entwurf','open'=>'Geöffnet','finalized'=>'Festgestellt','cancelled'=>'Abgesagt'][$election->status] ?? $election->status }}</span></div>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center text-slate-500">Noch keine Wahl angelegt.</div>
                    @endforelse
                </div>
            </section>

            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Delegiertenmandate</h2><p class="mt-1 text-sm text-slate-500">Vertretungsmandate mit Gliederung, Zielverband und Stimmgewicht.</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Mandat</th><th class="px-5 py-3">Delegierte Person</th><th class="px-5 py-3">Vertretene Gliederung</th><th class="px-5 py-3">Ziel</th><th class="px-5 py-3">Gewicht</th><th class="px-5 py-3">Zeitraum</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($mandates as $mandate)
                                <tr>
                                    <td class="px-5 py-3 font-semibold">{{ $mandate->mandate_number }}</td>
                                    <td class="px-5 py-3">{{ $mandate->member?->person?->display_name }}</td>
                                    <td class="px-5 py-3">{{ $mandate->representedOrganization?->name }}</td>
                                    <td class="px-5 py-3">{{ $mandate->receivingOrganization?->name ?: 'ohne feste Zielgliederung' }}</td>
                                    <td class="px-5 py-3">{{ rtrim(rtrim(number_format((float) $mandate->voting_weight, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-5 py-3">{{ $mandate->starts_at->format('d.m.Y') }} – {{ $mandate->ends_at?->format('d.m.Y') ?: 'offen' }}</td>
                                    <td class="px-5 py-3 text-right">@if($canManageDelegates && $mandate->status === 'active')<form method="post" action="{{ route('delegates.end', $mandate) }}">@csrf @method('PATCH')<button class="text-sm font-semibold text-red-700 hover:underline">Beenden</button></form>@else<span class="text-xs text-slate-400">{{ $mandate->status === 'active' ? 'aktiv' : 'beendet' }}</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">Noch keine Delegiertenmandate vorhanden.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            @if($canManage)
                <section class="cv-panel p-5">
                    <h2 class="font-bold">Neue Wahl</h2>
                    <form method="post" action="{{ route('elections.store') }}" class="mt-4 space-y-3">@csrf
                        <label class="block"><span class="text-sm font-semibold">Bezeichnung</span><input class="cv-input mt-1" name="title" placeholder="z. B. Vorstandswahl 2027" required></label>
                        <label class="block"><span class="text-sm font-semibold">Wahltag</span><input type="date" class="cv-input mt-1" name="election_date" required></label>
                        <label class="block"><span class="text-sm font-semibold">Gliederung</span><select class="cv-input mt-1" name="organization_unit_id"><option value="">Mandantenweit</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-sm font-semibold">Zugehörige Sitzung</span><select class="cv-input mt-1" name="governance_meeting_id"><option value="">Keine</option>@foreach($meetings as $meeting)<option value="{{ $meeting->id }}">{{ $meeting->starts_at->format('d.m.Y') }} · {{ $meeting->title }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-sm font-semibold">Basis der Wahlberechtigung</span><select class="cv-input mt-1" name="voter_basis"><option value="members">Aktive Mitglieder</option><option value="delegates">Aktive Delegiertenmandate</option><option value="manual">Manuelle Wählerliste</option></select></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_proxies" value="1"><span>Vollmachten nach eigener Satzung/Ordnung zulassen</span></label>
                        <textarea class="cv-input min-h-24" name="notes" placeholder="Interne Hinweise / Wahlordnung"></textarea>
                        <button class="cv-button-primary w-full">Wahl anlegen</button>
                    </form>
                </section>
            @endif

            @if($canManageDelegates)
                <section class="cv-panel p-5">
                    <h2 class="font-bold">Delegiertenmandat anlegen</h2>
                    <form method="post" action="{{ route('delegates.store') }}" class="mt-4 space-y-3">@csrf
                        <label class="block"><span class="text-sm font-semibold">Mitglied</span><select class="cv-input mt-1" name="member_id" required><option value="">Bitte wählen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-sm font-semibold">Vertretene Gliederung</span><select class="cv-input mt-1" name="represented_organization_unit_id" required><option value="">Bitte wählen</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-sm font-semibold">Zielgliederung</span><select class="cv-input mt-1" name="receiving_organization_unit_id"><option value="">Keine feste Zielgliederung</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-sm font-semibold">Stimmgewicht</span><input type="number" min="0.001" step="0.001" class="cv-input mt-1" name="voting_weight" value="1" required></label>
                        <div class="grid grid-cols-2 gap-3"><label class="block"><span class="text-xs font-semibold">Beginn</span><input type="date" class="cv-input mt-1" name="starts_at" required></label><label class="block"><span class="text-xs font-semibold">Ende</span><input type="date" class="cv-input mt-1" name="ends_at"></label></div>
                        <textarea class="cv-input min-h-20" name="notes" placeholder="Hinweise zum Mandat"></textarea>
                        <button class="cv-button border border-slate-300 bg-white w-full">Mandat speichern</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
