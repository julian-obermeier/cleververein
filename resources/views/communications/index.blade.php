<x-layouts.app title="Kommunikation">
    @php
        $selectedTemplate = request('template') ? $templates->firstWhere('id', (int) request('template')) : null;
        $selectedEvent = request('event') ? $events->firstWhere('id', (int) request('event')) : null;
    @endphp
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Kommunikationszentrale</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">E-Mail-Vorlagen & Kampagnen</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Empfänger zuerst einfrieren, anschließend kontrolliert in Batches versenden. Erfolgreich versendete Nachrichten werden nicht doppelt verschickt.</p>
        </div>
        <a href="{{ route('events.index') }}" class="cv-button border border-slate-300 bg-white">Zum Veranstaltungskalender</a>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.45fr)_minmax(380px,.55fr)]">
        <div class="space-y-5">
            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Kampagnen</h2><p class="mt-1 text-sm text-slate-500">Versandstatus und eingefrorene Empfängerlisten.</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse($campaigns as $campaign)
                        <a href="{{ route('communications.campaigns.show', $campaign) }}" class="grid gap-3 px-5 py-4 transition hover:bg-slate-50 lg:grid-cols-[minmax(0,1fr)_140px_220px] lg:items-center">
                            <div><p class="font-semibold">{{ $campaign->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $campaign->event?->title ?: ($campaign->segment?->name ?: ($campaign->organizationUnit?->name ?: 'Alle aktiven Mitglieder')) }}</p></div>
                            <div><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $campaign->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($campaign->status === 'draft' ? 'bg-slate-100 text-slate-700' : 'bg-blue-100 text-blue-800') }}">{{ ['draft'=>'Entwurf','prepared'=>'Vorbereitet','sending'=>'Versand läuft','completed'=>'Abgeschlossen'][$campaign->status] ?? $campaign->status }}</span></div>
                            <div class="text-sm text-slate-500"><span class="font-semibold text-slate-900">{{ $campaign->sent_count }}</span> gesendet · {{ $campaign->failed_count }} Fehler · {{ $campaign->recipient_count }} Empfänger</div>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center text-slate-500">Noch keine Kampagne angelegt.</div>
                    @endforelse
                </div>
            </section>

            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">E-Mail-Vorlagen</h2><p class="mt-1 text-sm text-slate-500">Wiederverwendbare Inhalte mit dynamischen Platzhaltern.</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse($templates as $template)
                        <div class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
                            <div><p class="font-semibold">{{ $template->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $template->subject }}</p></div>
                            <div class="flex gap-2"><a href="{{ route('communications.index', ['template' => $template->id, 'event' => request('event')]) }}" class="text-sm font-semibold text-blue-700 hover:underline">Für Kampagne verwenden</a>@if($canManage)<form method="post" action="{{ route('communications.templates.toggle', $template) }}">@csrf @method('PATCH')<button class="text-sm text-slate-500 hover:underline">{{ $template->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form>@endif</div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-slate-500">Noch keine Vorlagen vorhanden.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            @if($canManage)
                <section class="cv-panel p-5">
                    <h2 class="font-bold">Neue Kampagne</h2>
                    @if($selectedEvent)<p class="mt-2 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800">Veranstaltung vorausgewählt: <strong>{{ $selectedEvent->title }}</strong></p>@endif
                    <form method="post" action="{{ route('communications.campaigns.store') }}" class="mt-4 space-y-3">@csrf
                        <label class="block"><span class="text-sm font-semibold">Interner Name</span><input class="cv-input mt-1" name="name" value="{{ $selectedEvent ? 'Einladung · '.$selectedEvent->title : '' }}" required></label>
                        <input type="hidden" name="template_id" value="{{ $selectedTemplate?->id }}">
                        <label class="block"><span class="text-sm font-semibold">Zielgruppe</span><select class="cv-input mt-1" name="target_type"><option value="event_registrations" @selected($selectedEvent)>Eingeladene einer Veranstaltung</option><option value="all_active" @selected(!$selectedEvent)>Alle aktiven Mitglieder</option><option value="segment">Segment</option><option value="organization">Gliederung</option></select></label>
                        <label class="block"><span class="text-xs font-semibold">Veranstaltung</span><select class="cv-input mt-1" name="event_id"><option value="">–</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected($selectedEvent?->id === $event->id)>{{ $event->starts_at->format('d.m.Y') }} · {{ $event->title }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-xs font-semibold">Segment</span><select class="cv-input mt-1" name="member_segment_id"><option value="">–</option>@foreach($segments as $segment)<option value="{{ $segment->id }}">{{ $segment->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-xs font-semibold">Gliederung</span><select class="cv-input mt-1" name="organization_unit_id"><option value="">–</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-sm font-semibold">Betreff</span><input class="cv-input mt-1" name="subject" value="{{ $selectedTemplate?->subject ?: ($selectedEvent ? 'Einladung: {{veranstaltung.titel}}' : '') }}" required></label>
                        <label class="block"><span class="text-sm font-semibold">Nachricht</span><textarea class="cv-input mt-1 min-h-56" name="body" required>{{ $selectedTemplate?->body ?: ($selectedEvent ? "Hallo {{mitglied.vorname}},\n\nwir laden dich zu {{veranstaltung.titel}} am {{veranstaltung.datum}} ein.\nOrt: {{veranstaltung.ort}}\n\nBitte gib uns hier deine Rückmeldung:\n{{anmeldung.link}}\n\nViele Grüße" : '') }}</textarea></label>
                        <div class="rounded-md bg-slate-50 p-3 text-xs text-slate-600"><strong>Platzhalter:</strong><br>{{ '{{mitglied.name}}' }}, {{ '{{mitglied.vorname}}' }}, {{ '{{mitglied.nummer}}' }}, {{ '{{veranstaltung.titel}}' }}, {{ '{{veranstaltung.datum}}' }}, {{ '{{veranstaltung.ort}}' }}, {{ '{{anmeldung.link}}' }}</div>
                        <button class="cv-button-primary w-full">Kampagne als Entwurf anlegen</button>
                    </form>
                </section>

                <section class="cv-panel p-5">
                    <h2 class="font-bold">Neue Vorlage</h2>
                    <form method="post" action="{{ route('communications.templates.store') }}" class="mt-4 space-y-3">@csrf
                        <input class="cv-input" name="name" placeholder="Vorlagenname" required>
                        <input class="cv-input" name="subject" placeholder="Betreff" required>
                        <textarea class="cv-input min-h-40" name="body" placeholder="Nachrichtentext" required></textarea>
                        <button class="cv-button border border-slate-300 bg-white w-full">Vorlage speichern</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
