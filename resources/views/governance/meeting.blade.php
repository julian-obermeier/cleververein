<x-layouts.app title="{{ $meeting->title }}">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <a href="{{ route('governance.index') }}" class="text-sm font-semibold text-blue-700">← Gremien & Sitzungen</a>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $meeting->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $meeting->starts_at->format('d.m.Y H:i') }}@if($meeting->ends_at) – {{ $meeting->ends_at->format('d.m.Y H:i') }}@endif · {{ $meeting->committee?->name ?? $meeting->organizationUnit?->name ?? 'mandantenweit' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ match($meeting->status){'planned'=>'geplant','in_progress'=>'läuft','closed'=>'abgeschlossen','cancelled'=>'abgesagt',default=>$meeting->status} }}</span>
            @if($meeting->quorum_required !== null)<span class="rounded-full px-3 py-1 text-xs font-semibold {{ $meeting->quorum_met ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $meeting->quorum_met ? 'beschlussfähig' : 'nicht beschlussfähig' }}</span>@endif
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $meeting->minutes_status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">Protokoll: {{ match($meeting->minutes_status){'approved'=>'freigegeben','review'=>'Prüfung',default=>'Entwurf'} }}</span>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
        <section class="cv-panel p-5">
            <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold">Sitzungsdaten</h2><p class="text-sm text-slate-500">Termin, Ort, Status und Beschlussfähigkeitsgrenze.</p></div>@if($meeting->online_url)<a class="text-sm font-semibold text-blue-700" href="{{ $meeting->online_url }}" target="_blank" rel="noopener">Online teilnehmen ↗</a>@endif</div>
            @if($canManage)
                <form method="post" action="{{ route('governance.meetings.update',$meeting) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('put')
                    <label><span class="cv-label">Beginn *</span><input required type="datetime-local" class="cv-input" name="starts_at" value="{{ $meeting->starts_at->format('Y-m-d\TH:i') }}"></label>
                    <label><span class="cv-label">Ende</span><input type="datetime-local" class="cv-input" name="ends_at" value="{{ $meeting->ends_at?->format('Y-m-d\TH:i') }}"></label>
                    <label><span class="cv-label">Ort</span><input class="cv-input" name="location" value="{{ $meeting->location }}"></label>
                    <label><span class="cv-label">Online-Link</span><input type="url" class="cv-input" name="online_url" value="{{ $meeting->online_url }}"></label>
                    <label><span class="cv-label">Status *</span><select class="cv-input" name="status"><option value="planned" @selected($meeting->status==='planned')>Geplant</option><option value="in_progress" @selected($meeting->status==='in_progress')>Läuft</option><option value="closed" @selected($meeting->status==='closed')>Abgeschlossen</option><option value="cancelled" @selected($meeting->status==='cancelled')>Abgesagt</option></select></label>
                    <label><span class="cv-label">Beschlussfähig ab</span><input type="number" min="1" class="cv-input" name="quorum_required" value="{{ $meeting->quorum_required }}"></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Sitzungsdaten speichern</button></div>
                </form>
            @else
                <dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-xs font-semibold uppercase text-slate-500">Ort</dt><dd class="mt-1 text-sm">{{ $meeting->location ?: '–' }}</dd></div><div><dt class="text-xs font-semibold uppercase text-slate-500">Beschlussfähigkeit</dt><dd class="mt-1 text-sm">{{ $meeting->quorum_required ? 'ab '.$meeting->quorum_required.' Stimmberechtigten' : 'nicht hinterlegt' }}</dd></div></dl>
            @endif
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Sitzungskennzahlen</h2>
            @php($presentVoting = $meeting->participants->where('attendance_status','present')->where('has_voting_right',true)->count())
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-2xl font-bold">{{ $meeting->participants->where('attendance_status','present')->count() }}</p><p class="text-xs text-slate-500">anwesend</p></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-2xl font-bold">{{ $presentVoting }}</p><p class="text-xs text-slate-500">stimmberechtigt anwesend</p></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-2xl font-bold">{{ $meeting->motions->count() }}</p><p class="text-xs text-slate-500">Anträge</p></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-2xl font-bold">{{ $meeting->resolutions->count() }}</p><p class="text-xs text-slate-500">Beschlüsse</p></div>
            </div>
        </section>
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Teilnehmer & Anwesenheit</h2><p class="text-sm text-slate-500">Beschlussfähigkeit wird aus den anwesenden Stimmberechtigten berechnet.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($meeting->participants as $participant)
                <div class="px-5 py-4">
                    <form method="post" action="{{ route('governance.participants.update',[$meeting,$participant]) }}" class="grid gap-3 lg:grid-cols-[1fr_180px_180px_auto] lg:items-end">@csrf @method('put')
                        <div><p class="font-semibold">{{ $participant->member?->person?->display_name ?? $participant->external_name ?? 'Externer Teilnehmer' }}</p><p class="text-xs text-slate-500">{{ $participant->member?->member_number ?: 'extern' }}</p></div>
                        <label><span class="cv-label">Rolle</span><select class="cv-input" name="participant_role" @disabled(!$canManage)><option value="chair" @selected($participant->participant_role==='chair')>Sitzungsleitung</option><option value="participant" @selected($participant->participant_role==='participant')>Teilnehmer</option><option value="guest" @selected($participant->participant_role==='guest')>Gast</option><option value="minute_taker" @selected($participant->participant_role==='minute_taker')>Protokollführung</option></select></label>
                        <label><span class="cv-label">Anwesenheit</span><select class="cv-input" name="attendance_status" @disabled(!$canManage)><option value="invited" @selected($participant->attendance_status==='invited')>Eingeladen</option><option value="present" @selected($participant->attendance_status==='present')>Anwesend</option><option value="absent" @selected($participant->attendance_status==='absent')>Abwesend</option><option value="excused" @selected($participant->attendance_status==='excused')>Entschuldigt</option></select></label>
                        <div class="flex items-center gap-3"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="has_voting_right" value="0"><input type="checkbox" name="has_voting_right" value="1" @checked($participant->has_voting_right) @disabled(!$canManage)><span>Stimmrecht</span></label>@if($canManage)<button class="cv-button border border-slate-300 bg-white text-slate-700">Speichern</button>@endif</div>
                    </form>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">Noch keine Teilnehmer erfasst.</div>
            @endforelse
        </div>
        @if($canManage)
            <form method="post" action="{{ route('governance.participants.store',$meeting) }}" class="grid gap-3 border-t border-slate-200 bg-slate-50/60 p-5 md:grid-cols-2 xl:grid-cols-5">@csrf
                <label class="xl:col-span-2"><span class="cv-label">Mitglied</span><select class="cv-input" name="member_id"><option value="">Oder externen Namen verwenden</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Externer Name</span><input class="cv-input" name="external_name"></label>
                <label><span class="cv-label">Rolle *</span><select class="cv-input" name="participant_role"><option value="participant">Teilnehmer</option><option value="chair">Sitzungsleitung</option><option value="guest">Gast</option><option value="minute_taker">Protokollführung</option></select></label>
                <div class="flex items-end gap-3"><input type="hidden" name="attendance_status" value="invited"><label class="mb-2 flex items-center gap-2 text-sm"><input type="hidden" name="has_voting_right" value="0"><input type="checkbox" name="has_voting_right" value="1"><span>Stimmrecht</span></label><button class="cv-button-primary">Hinzufügen</button></div>
            </form>
        @endif
    </section>

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Tagesordnung</h2><p class="text-sm text-slate-500">Reihenfolge und Inhalt der Sitzung.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($meeting->agendaItems as $item)
                    <div class="px-5 py-4"><div class="flex items-start gap-4"><span class="mt-0.5 min-w-16 rounded bg-slate-100 px-2 py-1 text-center text-xs font-bold text-slate-700">{{ $item->item_number ?: 'TOP' }}</span><div class="min-w-0"><p class="font-semibold">{{ $item->title }}</p>@if($item->description)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $item->description }}</p>@endif<div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500"><span>{{ match($item->item_type){'information'=>'Information','discussion'=>'Beratung','motion'=>'Antrag','election'=>'Wahl',default=>'Sonstiges'} }}</span>@if($item->planned_minutes)<span>· {{ $item->planned_minutes }} Min.</span>@endif @if($item->motions->count())<span>· {{ $item->motions->count() }} Antrag/Anträge</span>@endif @if($item->resolutions->count())<span>· {{ $item->resolutions->count() }} Beschluss/Beschlüsse</span>@endif</div></div></div></div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-500">Noch keine Tagesordnung.</div>
                @endforelse
            </div>
            @if($canManage)
                <form method="post" action="{{ route('governance.agenda.store',$meeting) }}" class="grid gap-3 border-t border-slate-200 bg-slate-50/60 p-5 sm:grid-cols-2">@csrf
                    <label><span class="cv-label">TOP-Nummer</span><input class="cv-input" name="item_number" placeholder="automatisch"></label>
                    <label><span class="cv-label">Art *</span><select class="cv-input" name="item_type"><option value="discussion">Beratung</option><option value="information">Information</option><option value="motion">Antrag</option><option value="election">Wahl</option><option value="other">Sonstiges</option></select></label>
                    <label class="sm:col-span-2"><span class="cv-label">Titel *</span><input required class="cv-input" name="title"></label>
                    <label class="sm:col-span-2"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20 py-3" name="description"></textarea></label>
                    <label><span class="cv-label">Geplante Minuten</span><input type="number" min="1" class="cv-input" name="planned_minutes"></label>
                    <div class="flex items-end justify-end"><button class="cv-button-primary">TOP hinzufügen</button></div>
                </form>
            @endif
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Protokoll</h2>
            <p class="mt-1 text-sm text-slate-500">Freigegebene Protokolle sind gegen stilles Überschreiben gesperrt.</p>
            @if($canMinutes && $meeting->minutes_status !== 'approved')
                <form method="post" action="{{ route('governance.minutes.update',$meeting) }}" class="mt-4">@csrf @method('put')
                    <textarea required class="cv-input min-h-72 py-3" name="minutes_text" placeholder="Sitzungsverlauf, Feststellungen und Ergebnisse …">{{ old('minutes_text',$meeting->minutes_text) }}</textarea>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3"><select class="cv-input w-auto" name="minutes_status"><option value="draft" @selected($meeting->minutes_status==='draft')>Entwurf</option><option value="review" @selected($meeting->minutes_status==='review')>Zur Prüfung</option></select><button class="cv-button-primary">Protokoll speichern</button></div>
                </form>
                @if(filled($meeting->minutes_text))<form method="post" action="{{ route('governance.minutes.approve',$meeting) }}" class="mt-3 flex justify-end">@csrf<button class="cv-button border border-emerald-300 bg-emerald-50 text-emerald-800">Protokoll verbindlich freigeben</button></form>@endif
            @else
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm whitespace-pre-line text-slate-700">{{ $meeting->minutes_text ?: 'Noch kein Protokolltext vorhanden.' }}</div>
                @if($meeting->minutes_status==='approved')<p class="mt-3 text-xs font-semibold text-emerald-700">Freigegeben am {{ $meeting->minutes_approved_at?->format('d.m.Y H:i') }}</p>@endif
            @endif
        </section>
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Anträge</h2><p class="text-sm text-slate-500">Eingereichte Anträge mit eindeutiger AN-Nummer.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($meeting->motions as $motion)
                <div class="px-5 py-4"><div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><span class="rounded bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700">{{ $motion->motion_number }}</span><p class="font-semibold">{{ $motion->title }}</p></div><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $motion->motion_text }}</p>@if($motion->rationale)<p class="mt-2 text-sm text-slate-500"><strong>Begründung:</strong> {{ $motion->rationale }}</p>@endif<p class="mt-2 text-xs text-slate-500">Antragsteller: {{ $motion->proposer?->person?->display_name ?? $motion->proposer_name ?? 'nicht angegeben' }}</p></div><span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ match($motion->status){'submitted'=>'eingereicht','accepted'=>'angenommen','rejected'=>'abgelehnt','withdrawn'=>'zurückgezogen','deferred'=>'vertagt',default=>$motion->status} }}</span></div></div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">Keine Anträge erfasst.</div>
            @endforelse
        </div>
        @if($canDecide)
            <form method="post" action="{{ route('governance.motions.store',$meeting) }}" class="grid gap-3 border-t border-slate-200 bg-slate-50/60 p-5 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Tagesordnungspunkt</span><select class="cv-input" name="agenda_item_id"><option value="">Ohne festen TOP</option>@foreach($meeting->agendaItems as $item)<option value="{{ $item->id }}">{{ $item->item_number }} · {{ $item->title }}</option>@endforeach</select></label>
                <label><span class="cv-label">Antragsteller</span><select class="cv-input" name="proposer_member_id"><option value="">Extern / frei</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
                <label class="sm:col-span-2"><span class="cv-label">Externer Antragsteller</span><input class="cv-input" name="proposer_name"></label>
                <label class="sm:col-span-2"><span class="cv-label">Titel *</span><input required class="cv-input" name="title"></label>
                <label class="sm:col-span-2"><span class="cv-label">Antragstext *</span><textarea required class="cv-input min-h-28 py-3" name="motion_text"></textarea></label>
                <label class="sm:col-span-2"><span class="cv-label">Begründung</span><textarea class="cv-input min-h-20 py-3" name="rationale"></textarea></label>
                <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Antrag erfassen</button></div>
            </form>
        @endif
    </section>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Beschlüsse & Abstimmungen</h2><p class="text-sm text-slate-500">Bei geheimen Abstimmungen werden ausschließlich Summen gespeichert – keine personenbezogenen Einzelstimmen.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($meeting->resolutions as $resolution)
                <div class="px-5 py-4"><div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between"><div class="max-w-4xl"><div class="flex flex-wrap items-center gap-2"><span class="rounded bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ $resolution->resolution_number }}</span><p class="font-semibold">{{ $resolution->title }}</p></div><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $resolution->resolution_text }}</p></div><div class="shrink-0 rounded-xl bg-slate-50 px-4 py-3 text-xs"><p class="font-semibold {{ $resolution->decision_status === 'passed' ? 'text-emerald-700' : ($resolution->decision_status === 'rejected' ? 'text-red-700' : 'text-slate-700') }}">{{ match($resolution->decision_status){'passed'=>'angenommen','rejected'=>'abgelehnt',default=>'festgestellt'} }}</p><p class="mt-1 text-slate-500">Ja {{ $resolution->votes_yes }} · Nein {{ $resolution->votes_no }} · Enth. {{ $resolution->votes_abstain }} · Ungültig {{ $resolution->votes_invalid }}</p><p class="mt-1 text-slate-500">{{ match($resolution->voting_method){'secret'=>'geheim','unanimous'=>'einstimmig','acclamation'=>'per Akklamation',default=>'offen'} }}</p></div></div></div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">Noch kein Beschluss erfasst.</div>
            @endforelse
        </div>
        @if($canDecide)
            <form method="post" action="{{ route('governance.resolutions.store',$meeting) }}" class="grid gap-3 border-t border-slate-200 bg-slate-50/60 p-5 lg:grid-cols-4">@csrf
                <label class="lg:col-span-2"><span class="cv-label">Zugehöriger Antrag</span><select class="cv-input" name="motion_id"><option value="">Ohne Antrag</option>@foreach($meeting->motions->where('status','submitted') as $motion)<option value="{{ $motion->id }}">{{ $motion->motion_number }} · {{ $motion->title }}</option>@endforeach</select></label>
                <label><span class="cv-label">TOP</span><select class="cv-input" name="agenda_item_id"><option value="">Ohne festen TOP</option>@foreach($meeting->agendaItems as $item)<option value="{{ $item->id }}">{{ $item->item_number }} · {{ $item->title }}</option>@endforeach</select></label>
                <label><span class="cv-label">Ergebnis *</span><select class="cv-input" name="decision_status"><option value="passed">Angenommen</option><option value="rejected">Abgelehnt</option><option value="recorded">Festgestellt</option></select></label>
                <label class="lg:col-span-2"><span class="cv-label">Titel *</span><input required class="cv-input" name="title"></label>
                <label><span class="cv-label">Abstimmung *</span><select class="cv-input" name="voting_method"><option value="open">Offen</option><option value="secret">Geheim</option><option value="unanimous">Einstimmig</option><option value="acclamation">Akklamation</option></select></label>
                <label><span class="cv-label">Wirksam ab</span><input type="date" class="cv-input" name="effective_date"></label>
                <label class="lg:col-span-4"><span class="cv-label">Beschlusstext *</span><textarea required class="cv-input min-h-28 py-3" name="resolution_text"></textarea></label>
                <label><span class="cv-label">Ja</span><input type="number" min="0" class="cv-input" name="votes_yes" value="0"></label><label><span class="cv-label">Nein</span><input type="number" min="0" class="cv-input" name="votes_no" value="0"></label><label><span class="cv-label">Enthaltungen</span><input type="number" min="0" class="cv-input" name="votes_abstain" value="0"></label><label><span class="cv-label">Ungültig</span><input type="number" min="0" class="cv-input" name="votes_invalid" value="0"></label>
                <div class="lg:col-span-4 flex justify-end"><button class="cv-button-primary">Beschluss speichern</button></div>
            </form>
        @endif
    </section>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Aufgaben aus Sitzung & Beschlüssen</h2><p class="text-sm text-slate-500">Nachverfolgung von Zuständigkeit, Frist und Erledigungsstatus.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($meeting->tasks as $task)
                <form method="post" action="{{ route('governance.tasks.update',$task) }}" class="grid gap-3 px-5 py-4 lg:grid-cols-[1fr_180px_160px_160px_auto] lg:items-end">@csrf @method('put')
                    <div><p class="font-semibold">{{ $task->title }}</p>@if($task->description)<p class="mt-1 text-sm text-slate-600">{{ $task->description }}</p>@endif<p class="mt-1 text-xs text-slate-500">{{ $task->assignedMember?->person?->display_name ?? 'nicht zugewiesen' }}@if($task->resolution) · {{ $task->resolution->resolution_number }}@endif</p></div>
                    <label><span class="cv-label">Status</span><select class="cv-input" name="status" @disabled(!$canDecide)><option value="open" @selected($task->status==='open')>Offen</option><option value="in_progress" @selected($task->status==='in_progress')>In Arbeit</option><option value="done" @selected($task->status==='done')>Erledigt</option><option value="cancelled" @selected($task->status==='cancelled')>Entfallen</option></select></label>
                    <label><span class="cv-label">Priorität</span><select class="cv-input" name="priority" @disabled(!$canDecide)><option value="low" @selected($task->priority==='low')>Niedrig</option><option value="normal" @selected($task->priority==='normal')>Normal</option><option value="high" @selected($task->priority==='high')>Hoch</option></select></label>
                    <label><span class="cv-label">Fällig</span><input type="date" class="cv-input" name="due_at" value="{{ $task->due_at?->toDateString() }}" @disabled(!$canDecide)></label>
                    @if($canDecide)<button class="cv-button border border-slate-300 bg-white text-slate-700">Aktualisieren</button>@endif
                </form>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">Noch keine Aufgaben aus dieser Sitzung.</div>
            @endforelse
        </div>
        @if($canDecide)
            <form method="post" action="{{ route('governance.tasks.store',$meeting) }}" class="grid gap-3 border-t border-slate-200 bg-slate-50/60 p-5 md:grid-cols-2 xl:grid-cols-5">@csrf
                <label class="xl:col-span-2"><span class="cv-label">Aufgabe *</span><input required class="cv-input" name="title"></label>
                <label><span class="cv-label">Beschluss</span><select class="cv-input" name="resolution_id"><option value="">Allgemeine Sitzungsaufgabe</option>@foreach($meeting->resolutions as $resolution)<option value="{{ $resolution->id }}">{{ $resolution->resolution_number }} · {{ $resolution->title }}</option>@endforeach</select></label>
                <label><span class="cv-label">Zuständig</span><select class="cv-input" name="assigned_member_id"><option value="">Noch offen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Fällig</span><input type="date" class="cv-input" name="due_at"></label>
                <label class="xl:col-span-3"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20 py-3" name="description"></textarea></label>
                <label><span class="cv-label">Priorität *</span><select class="cv-input" name="priority"><option value="normal">Normal</option><option value="high">Hoch</option><option value="low">Niedrig</option></select></label>
                <div class="flex items-end justify-end"><button class="cv-button-primary">Aufgabe anlegen</button></div>
            </form>
        @endif
    </section>
</x-layouts.app>
