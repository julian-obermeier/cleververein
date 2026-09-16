<x-layouts.app :title="$election->title">
    @php
        $presentVoters = $election->voters->where('status', 'present');
        $presentWeight = $presentVoters->sum(fn ($voter) => (float) $voter->voting_weight);
        $statusLabels = ['draft'=>'Entwurf','open'=>'Geöffnet','finalized'=>'Festgestellt','cancelled'=>'Abgesagt'];
        $resultLabels = ['pending'=>'Offen','decided'=>'Entschieden','runoff'=>'Weiterer Wahlgang nötig','no_result'=>'Kein Ergebnis'];
    @endphp

    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
            <a href="{{ route('elections.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Wahlen & Delegierte</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-bold tracking-tight">{{ $election->title }}</h1>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $election->status === 'finalized' ? 'bg-emerald-100 text-emerald-800' : ($election->status === 'open' ? 'bg-blue-100 text-blue-800' : ($election->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700')) }}">{{ $statusLabels[$election->status] ?? $election->status }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">{{ $election->election_date->format('d.m.Y') }} · {{ $election->organizationUnit?->name ?: 'Mandantenweit' }} · {{ ['members'=>'Mitglieder','delegates'=>'Delegierte','manual'=>'Manuelle Wählerliste'][$election->voter_basis] ?? $election->voter_basis }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canManage && $election->status !== 'finalized')
                @if($election->status !== 'open')<form method="post" action="{{ route('elections.status.update', $election) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="open"><button class="cv-button-primary">Wahl öffnen</button></form>@endif
                @if($election->status === 'open')<form method="post" action="{{ route('elections.status.update', $election) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="draft"><button class="cv-button border border-slate-300 bg-white">Zurück auf Entwurf</button></form>@endif
            @endif
            @if($canFinalize && $election->status !== 'finalized' && $election->status !== 'cancelled')
                <form method="post" action="{{ route('elections.finalize', $election) }}" onsubmit="return confirm('Wahl endgültig feststellen? Danach können Ergebnisse nicht mehr verändert werden.')">@csrf<button class="cv-button border border-emerald-300 bg-emerald-50 text-emerald-800">Wahl feststellen</button></form>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Wahlberechtigte</p><p class="mt-1 text-3xl font-bold">{{ $election->voters->count() }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Anwesend</p><p class="mt-1 text-3xl font-bold">{{ $presentVoters->count() }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Direktes Stimmgewicht</p><p class="mt-1 text-3xl font-bold">{{ rtrim(rtrim(number_format($presentWeight, 3, ',', '.'), '0'), ',') }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Ämter</p><p class="mt-1 text-3xl font-bold">{{ $election->offices->count() }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Vollmachten</p><p class="mt-1 text-3xl font-bold">{{ $election->proxies->where('status', 'active')->count() }}</p></div>
    </div>

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.5fr)_minmax(390px,.5fr)]">
        <div class="space-y-5">
            <section class="cv-panel overflow-hidden">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div><h2 class="text-lg font-bold">Wahlberechtigte & Anwesenheit</h2><p class="mt-1 text-sm text-slate-500">Stimmgewicht und Anwesenheit werden getrennt geführt. Vollmachten erhöhen nur das verfügbare Gewicht des anwesenden Bevollmächtigten.</p></div>
                    @if($canManage && $election->status !== 'finalized' && $election->voter_basis !== 'manual')<form method="post" action="{{ route('elections.voters.seed', $election) }}">@csrf<button class="cv-button border border-slate-300 bg-white">Aus Basis ergänzen</button></form>@endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Quelle</th><th class="px-5 py-3">Gewicht</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($election->voters->sortBy(fn ($voter) => $voter->member?->person?->display_name) as $voter)
                                <tr>
                                    <td class="px-5 py-3"><span class="font-semibold">{{ $voter->member?->person?->display_name }}</span><br><span class="text-xs text-slate-500">{{ $voter->member?->member_number }}</span></td>
                                    <td class="px-5 py-3">{{ ['member'=>'Mitglied','delegate'=>'Delegiertenmandat','manual'=>'Manuell'][$voter->source] ?? $voter->source }}@if($voter->delegateMandate)<br><span class="text-xs text-slate-500">{{ $voter->delegateMandate->mandate_number }}</span>@endif</td>
                                    @if($canManage && $election->status !== 'finalized')
                                        <td colspan="3" class="px-5 py-3"><form method="post" action="{{ route('elections.voters.update', [$election, $voter]) }}" class="grid gap-2 sm:grid-cols-[120px_180px_auto] sm:items-center">@csrf @method('PUT')<input type="number" step="0.001" min="0.001" class="cv-input" name="voting_weight" value="{{ $voter->voting_weight }}"><select class="cv-input" name="status"><option value="eligible" @selected($voter->status==='eligible')>Wahlberechtigt</option><option value="present" @selected($voter->status==='present')>Anwesend</option><option value="absent" @selected($voter->status==='absent')>Abwesend</option><option value="ineligible" @selected($voter->status==='ineligible')>Nicht wahlberechtigt</option></select><button class="text-sm font-semibold text-blue-700 hover:underline">Speichern</button></form></td>
                                    @else
                                        <td class="px-5 py-3">{{ $voter->voting_weight }}</td><td class="px-5 py-3">{{ ['eligible'=>'Wahlberechtigt','present'=>'Anwesend','absent'=>'Abwesend','ineligible'=>'Nicht wahlberechtigt'][$voter->status] ?? $voter->status }}</td><td></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Noch keine Wählerliste vorhanden.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @foreach($election->offices as $office)
                <section class="cv-panel overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Zu wählendes Amt</p><h2 class="mt-1 text-xl font-bold">{{ $office->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $office->seats }} {{ $office->seats === 1 ? 'Sitz' : 'Sitze' }} · {{ ['secret'=>'geheim','open'=>'offen','acclamation'=>'Akklamation'][$office->voting_method] ?? $office->voting_method }} · {{ ['simple'=>'einfache Mehrheit','absolute'=>'absolute Mehrheit','two_thirds'=>'Zweidrittelmehrheit','highest_votes'=>'höchste Stimmenzahl'][$office->majority_type] ?? $office->majority_type }}</p></div>
                            @if($office->functionDefinition)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">→ {{ $office->functionDefinition->name }}</span>@endif
                        </div>
                    </div>

                    <div class="grid gap-5 p-5 xl:grid-cols-2">
                        <div>
                            <h3 class="font-bold">Kandidaturen</h3>
                            <div class="mt-3 space-y-2">
                                @forelse($office->candidates as $candidate)
                                    <div class="rounded-lg border border-slate-200 p-3">
                                        <div class="flex justify-between gap-3"><div><p class="font-semibold">{{ $candidate->member?->person?->display_name }}</p><p class="text-xs text-slate-500">{{ ['nominated'=>'Vorgeschlagen','accepted'=>'Kandidatur angenommen','withdrawn'=>'Zurückgezogen'][$candidate->status] ?? $candidate->status }}</p></div>
                                        @if($canManage && $election->status !== 'finalized')<form method="post" action="{{ route('elections.candidates.update', [$election, $office, $candidate]) }}">@csrf @method('PATCH')<select class="cv-input py-1 text-xs" name="status" onchange="this.form.submit()"><option value="nominated" @selected($candidate->status==='nominated')>Vorgeschlagen</option><option value="accepted" @selected($candidate->status==='accepted')>Angenommen</option><option value="withdrawn" @selected($candidate->status==='withdrawn')>Zurückgezogen</option></select></form>@endif</div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Noch keine Kandidatur.</p>
                                @endforelse
                            </div>
                            @if($canManage && $election->status !== 'finalized')
                                <form method="post" action="{{ route('elections.candidates.store', [$election, $office]) }}" class="mt-4 grid gap-2">@csrf
                                    <select class="cv-input" name="member_id" required><option value="">Kandidierendes Mitglied</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select>
                                    <select class="cv-input" name="nominated_by_member_id"><option value="">Vorgeschlagen von – optional</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->person?->display_name }}</option>@endforeach</select>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accepted" value="1"><span>Kandidatur wurde angenommen</span></label>
                                    <textarea class="cv-input min-h-20" name="statement" placeholder="Optionale Erklärung / Kandidaturhinweis"></textarea>
                                    <button class="cv-button border border-slate-300 bg-white">Kandidatur erfassen</button>
                                </form>
                            @endif
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3"><h3 class="font-bold">Wahlgänge</h3>@if($canConduct && $election->status === 'open' && !$office->rounds->contains('status', 'open'))<form method="post" action="{{ route('elections.rounds.store', [$election, $office]) }}">@csrf<button class="cv-button-primary">Neuen Wahlgang öffnen</button></form>@endif</div>
                            <div class="mt-3 space-y-3">
                                @forelse($office->rounds as $round)
                                    <div class="rounded-lg border {{ $round->status === 'open' ? 'border-blue-300 bg-blue-50/40' : 'border-slate-200' }} p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold">Wahlgang {{ $round->round_number }}</p><span class="text-xs font-semibold {{ $round->result_status === 'decided' ? 'text-emerald-700' : ($round->result_status === 'runoff' ? 'text-amber-700' : 'text-slate-500') }}">{{ $resultLabels[$round->result_status] ?? $round->result_status }}</span></div>
                                        @if($round->status === 'open' && $canConduct)
                                            <form method="post" action="{{ route('elections.rounds.finalize', [$election, $office, $round]) }}" class="mt-3 space-y-2">@csrf
                                                @foreach($office->candidates->whereIn('status', ['nominated','accepted']) as $candidate)
                                                    <label class="grid grid-cols-[1fr_120px] items-center gap-3 text-sm"><span>{{ $candidate->member?->person?->display_name }}</span><input type="number" min="0" step="0.001" class="cv-input" name="votes[{{ $candidate->id }}]" value="0" required></label>
                                                @endforeach
                                                <div class="grid grid-cols-2 gap-2"><label class="text-xs font-semibold">Enthaltungen<input type="number" min="0" step="0.001" class="cv-input mt-1" name="abstain_weight" value="0"></label><label class="text-xs font-semibold">Ungültig<input type="number" min="0" step="0.001" class="cv-input mt-1" name="invalid_weight" value="0"></label></div>
                                                <p class="text-xs text-slate-500">Verfügbares Stimmgewicht bei Öffnung: {{ $round->eligible_weight }}</p>
                                                <button class="cv-button-primary w-full">Wahlgang auswerten</button>
                                            </form>
                                        @else
                                            <div class="mt-3 space-y-1 text-sm">
                                                @foreach($round->results as $result)<div class="flex justify-between gap-3"><span>{{ $result->candidate?->member?->person?->display_name }} @if($result->is_elected)<strong class="text-emerald-700">· gewählt</strong>@endif</span><span class="font-semibold">{{ $result->votes }}</span></div>@endforeach
                                            </div>
                                            <p class="mt-3 text-xs text-slate-500">Abgegeben {{ $round->cast_weight }} · Enthaltungen {{ $round->abstain_weight }} · ungültig {{ $round->invalid_weight }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Noch kein Wahlgang durchgeführt.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach

            @if($election->protocols->isNotEmpty())
                <section class="cv-panel overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Wahlprotokolle</h2><p class="mt-1 text-sm text-slate-500">Private, versionierte Snapshots der festgestellten Wahldaten.</p></div>
                    <div class="divide-y divide-slate-100">@foreach($election->protocols as $protocol)<div class="flex items-center justify-between gap-3 px-5 py-4"><div><p class="font-semibold">Version {{ $protocol->version }}</p><p class="text-xs text-slate-500">{{ $protocol->generated_at->format('d.m.Y H:i') }} · {{ $protocol->generator?->name }}</p></div><a class="text-sm font-semibold text-blue-700 hover:underline" href="{{ route('elections.protocols.download', [$election, $protocol]) }}">PDF herunterladen</a></div>@endforeach</div>
                    @if($canFinalize && $election->status === 'finalized')<div class="border-t border-slate-200 p-4"><form method="post" action="{{ route('elections.protocols.store', $election) }}">@csrf<button class="cv-button border border-slate-300 bg-white">Neue Protokollversion erzeugen</button></form></div>@endif
                </section>
            @endif
        </div>

        <aside class="space-y-5">
            @if($canManage && $election->status !== 'finalized')
                <section class="cv-panel p-5">
                    <h2 class="font-bold">Wahlamt hinzufügen</h2>
                    <form method="post" action="{{ route('elections.offices.store', $election) }}" class="mt-4 space-y-3">@csrf
                        <input class="cv-input" name="name" placeholder="z. B. Vorsitz" required>
                        <label class="block"><span class="text-xs font-semibold">Verknüpfte Funktion</span><select class="cv-input mt-1" name="function_definition_id"><option value="">Keine automatische Amtszuordnung</option>@foreach($functions as $function)<option value="{{ $function->id }}">{{ $function->name }}</option>@endforeach</select></label>
                        <div class="grid grid-cols-2 gap-3"><label class="block"><span class="text-xs font-semibold">Sitze</span><input type="number" min="1" max="100" class="cv-input mt-1" name="seats" value="1" required></label><label class="block"><span class="text-xs font-semibold">Max. Wahlgänge</span><input type="number" min="1" max="20" class="cv-input mt-1" name="max_rounds" value="3" required></label></div>
                        <label class="block"><span class="text-xs font-semibold">Wahlverfahren</span><select class="cv-input mt-1" name="voting_method"><option value="secret">Geheim</option><option value="open">Offen</option><option value="acclamation">Akklamation</option></select></label>
                        <label class="block"><span class="text-xs font-semibold">Mehrheitsregel</span><select class="cv-input mt-1" name="majority_type"><option value="absolute">Absolute Mehrheit</option><option value="simple">Einfache Mehrheit / Rangfolge</option><option value="two_thirds">Zweidrittelmehrheit</option><option value="highest_votes">Höchste Stimmenzahl</option></select></label>
                        <label class="block"><span class="text-xs font-semibold">Bezugsgröße der Mehrheit</span><select class="cv-input mt-1" name="majority_basis"><option value="valid_votes">Gültige Kandidatenstimmen</option><option value="cast_including_abstentions">Abgegebene Stimmen inkl. Enthaltungen</option><option value="eligible_weight">Anwesendes Stimmgewicht</option></select></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_abstention" value="1" checked><span>Enthaltungen zulassen</span></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sync_function_assignments" value="1"><span>Gewählte nach Feststellung automatisch als Funktionsträger übernehmen</span></label>
                        <div class="grid grid-cols-2 gap-3"><label class="block"><span class="text-xs font-semibold">Amtszeit ab</span><input type="date" class="cv-input mt-1" name="term_starts_at"></label><label class="block"><span class="text-xs font-semibold">Amtszeit bis</span><input type="date" class="cv-input mt-1" name="term_ends_at"></label></div>
                        <textarea class="cv-input min-h-20" name="notes" placeholder="Satzungs-/Wahlordnungshinweise"></textarea>
                        <button class="cv-button-primary w-full">Amt hinzufügen</button>
                    </form>
                </section>

                <section class="cv-panel p-5">
                    <h2 class="font-bold">Wahlberechtigten manuell ergänzen</h2>
                    <form method="post" action="{{ route('elections.voters.store', $election) }}" class="mt-4 space-y-3">@csrf
                        <select class="cv-input" name="member_id" required><option value="">Mitglied auswählen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select>
                        <input type="number" min="0.001" step="0.001" class="cv-input" name="voting_weight" value="1" required>
                        <textarea class="cv-input min-h-20" name="notes" placeholder="Hinweis zur Wahlberechtigung"></textarea>
                        <button class="cv-button border border-slate-300 bg-white w-full">Wahlberechtigung ergänzen</button>
                    </form>
                </section>
            @endif

            @if($election->allow_proxies)
                <section class="cv-panel p-5">
                    <h2 class="font-bold">Vollmachten</h2>
                    <div class="mt-3 space-y-2">@forelse($election->proxies as $proxy)<div class="rounded-lg border border-slate-200 p-3 text-sm"><p><strong>{{ $proxy->grantor?->person?->display_name }}</strong> → {{ $proxy->holder?->person?->display_name }}</p><p class="mt-1 text-xs text-slate-500">Gewicht {{ $proxy->voting_weight }} · {{ $proxy->status === 'active' ? 'aktiv' : 'widerrufen' }}</p>@if($canManage && $proxy->status === 'active' && $election->status !== 'finalized')<form method="post" action="{{ route('elections.proxies.revoke', [$election, $proxy]) }}" class="mt-2">@csrf @method('PATCH')<button class="text-xs font-semibold text-red-700 hover:underline">Widerrufen</button></form>@endif</div>@empty<p class="text-sm text-slate-500">Keine Vollmacht erfasst.</p>@endforelse</div>
                    @if($canManage && $election->status !== 'finalized')
                        <form method="post" action="{{ route('elections.proxies.store', $election) }}" class="mt-4 space-y-2">@csrf
                            <select class="cv-input" name="grantor_member_id" required><option value="">Vollmachtgeber</option>@foreach($election->voters as $voter)<option value="{{ $voter->member_id }}">{{ $voter->member?->person?->display_name }}</option>@endforeach</select>
                            <select class="cv-input" name="proxy_member_id" required><option value="">Bevollmächtigter</option>@foreach($election->voters as $voter)<option value="{{ $voter->member_id }}">{{ $voter->member?->person?->display_name }}</option>@endforeach</select>
                            <input type="number" step="0.001" min="0.001" class="cv-input" name="voting_weight" value="1" required>
                            <textarea class="cv-input min-h-16" name="notes" placeholder="Nachweis / Hinweis"></textarea>
                            <button class="cv-button border border-slate-300 bg-white w-full">Vollmacht speichern</button>
                        </form>
                    @endif
                </section>
            @endif

            <section class="cv-panel p-5">
                <h2 class="font-bold">Wahlinformation</h2>
                <dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">Erstellt von</dt><dd class="text-right font-medium">{{ $election->creator?->name }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Sitzung</dt><dd class="text-right font-medium">{{ $election->governanceMeeting?->title ?: '–' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Vollmachten</dt><dd class="font-medium">{{ $election->allow_proxies ? 'zugelassen' : 'nicht aktiviert' }}</dd></div>@if($election->finalized_at)<div class="flex justify-between gap-3"><dt class="text-slate-500">Festgestellt</dt><dd class="text-right font-medium">{{ $election->finalized_at->format('d.m.Y H:i') }}<br>{{ $election->finalizer?->name }}</dd></div>@endif</dl>
                @if($election->notes)<div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $election->notes }}</div>@endif
            </section>
        </aside>
    </div>
</x-layouts.app>
