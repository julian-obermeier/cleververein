<x-layouts.app title="Veranstaltung">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <a href="{{ route('events.index', ['month' => $event->starts_at->startOfMonth()->format('Y-m-d')]) }}" class="text-sm font-semibold text-blue-700 hover:underline">← Kalender</a>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $event->title }}</h1>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                <span>{{ $event->starts_at->format('d.m.Y · H:i') }}@if($event->ends_at) – {{ $event->ends_at->format($event->ends_at->isSameDay($event->starts_at) ? 'H:i' : 'd.m.Y H:i') }}@endif</span>
                <span>{{ $event->location ?: 'Kein Ort hinterlegt' }}</span>
                @if($event->organizationUnit)<span>{{ $event->organizationUnit->name }}</span>@endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('events.ics', $event) }}" class="cv-button border border-slate-300 bg-white">Kalenderdatei (.ics)</a>
            @if($canRegistrations)<a href="{{ route('events.participants.csv', $event) }}" class="cv-button border border-slate-300 bg-white">Teilnehmer CSV</a>@endif
            <a href="{{ route('communications.index', ['event' => $event->id]) }}" class="cv-button-primary">Einladung versenden</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @php
        $registered = $event->registrations->where('status', 'registered')->count();
        $waitlisted = $event->registrations->where('status', 'waitlisted')->count();
        $invited = $event->registrations->where('status', 'invited')->count();
        $declined = $event->registrations->where('status', 'declined')->count();
        $present = $event->registrations->where('attendance_status', 'present')->count();
    @endphp

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Zusagen</p><p class="mt-2 text-3xl font-bold">{{ $registered }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Warteliste</p><p class="mt-2 text-3xl font-bold">{{ $waitlisted }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Offene Einladungen</p><p class="mt-2 text-3xl font-bold">{{ $invited }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Absagen</p><p class="mt-2 text-3xl font-bold">{{ $declined }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Anwesend</p><p class="mt-2 text-3xl font-bold">{{ $present }}</p></div>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.45fr)_minmax(360px,.55fr)]">
        <div class="space-y-5">
            <section class="cv-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Teilnehmer & Einladungen</h2><p class="mt-1 text-sm text-slate-500">Zusage, Warteliste und Anwesenheit werden getrennt geführt.</p></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Person</th><th class="px-5 py-3">E-Mail</th><th class="px-5 py-3">Teilnahme</th><th class="px-5 py-3">Anwesenheit</th><th class="px-5 py-3">Aktionen</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($event->registrations as $registration)
                                @php($name = $registration->member?->person?->display_name ?: $registration->guest_name)
                                @php($email = $registration->member?->person?->email ?: $registration->guest_email)
                                <tr>
                                    <td class="px-5 py-4"><p class="font-semibold">{{ $name }}</p><p class="mt-1 text-xs text-slate-400">{{ $registration->member ? 'Mitglied '.$registration->member->member_number : 'Externer Gast' }}</p></td>
                                    <td class="px-5 py-4 text-slate-600">{{ $email ?: '–' }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $registration->status === 'registered' ? 'bg-emerald-100 text-emerald-800' : ($registration->status === 'waitlisted' ? 'bg-amber-100 text-amber-800' : ($registration->status === 'declined' ? 'bg-slate-100 text-slate-600' : 'bg-blue-100 text-blue-800')) }}">{{ ['registered'=>'zugesagt','waitlisted'=>'Warteliste','declined'=>'abgesagt','invited'=>'eingeladen'][$registration->status] ?? $registration->status }}</span></td>
                                    <td class="px-5 py-4">{{ ['unknown'=>'offen','present'=>'anwesend','absent'=>'fehlend','excused'=>'entschuldigt'][$registration->attendance_status] ?? $registration->attendance_status }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @if($canRegistrations)
                                                <form method="post" action="{{ route('events.registrations.respond', [$event, $registration]) }}">@csrf<input type="hidden" name="response" value="registered"><button class="text-xs font-semibold text-emerald-700 hover:underline">Zusage</button></form>
                                                <form method="post" action="{{ route('events.registrations.respond', [$event, $registration]) }}">@csrf<input type="hidden" name="response" value="declined"><button class="text-xs font-semibold text-slate-600 hover:underline">Absage</button></form>
                                            @endif
                                            @if($canAttendance)
                                                <form method="post" action="{{ route('events.registrations.attendance', [$event, $registration]) }}" class="flex items-center gap-1">@csrf @method('PATCH')<select name="attendance_status" class="rounded-md border border-slate-300 px-2 py-1 text-xs"><option value="unknown">offen</option><option value="present" @selected($registration->attendance_status==='present')>anwesend</option><option value="absent" @selected($registration->attendance_status==='absent')>fehlend</option><option value="excused" @selected($registration->attendance_status==='excused')>entschuldigt</option></select><button class="text-xs font-semibold text-blue-700">Speichern</button></form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Noch keine Einladungen oder Anmeldungen vorhanden.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if($canManage)
                <section class="cv-panel p-5">
                    <h2 class="text-lg font-bold">Veranstaltung bearbeiten</h2>
                    <form method="post" action="{{ route('events.update', $event) }}" class="mt-4 grid gap-4 lg:grid-cols-2">@csrf @method('PUT')
                        <label class="lg:col-span-2"><span class="text-sm font-semibold">Titel</span><input class="cv-input mt-1" name="title" value="{{ $event->title }}" required></label>
                        <label><span class="text-sm font-semibold">Beginn</span><input class="cv-input mt-1" type="datetime-local" name="starts_at" value="{{ $event->starts_at->format('Y-m-d\TH:i') }}" required></label>
                        <label><span class="text-sm font-semibold">Ende</span><input class="cv-input mt-1" type="datetime-local" name="ends_at" value="{{ $event->ends_at?->format('Y-m-d\TH:i') }}"></label>
                        <label><span class="text-sm font-semibold">Ort</span><input class="cv-input mt-1" name="location" value="{{ $event->location }}"></label>
                        <label><span class="text-sm font-semibold">Online-Link</span><input class="cv-input mt-1" type="url" name="online_url" value="{{ $event->online_url }}"></label>
                        <label><span class="text-sm font-semibold">Status</span><select class="cv-input mt-1" name="status"><option value="scheduled" @selected($event->status==='scheduled')>Geplant</option><option value="completed" @selected($event->status==='completed')>Abgeschlossen</option><option value="cancelled" @selected($event->status==='cancelled')>Abgesagt</option></select></label>
                        <label><span class="text-sm font-semibold">Kapazität</span><input class="cv-input mt-1" type="number" min="1" name="capacity" value="{{ $event->capacity }}"></label>
                        <label><span class="text-sm font-semibold">Anmeldeschluss</span><input class="cv-input mt-1" type="datetime-local" name="registration_deadline" value="{{ $event->registration_deadline?->format('Y-m-d\TH:i') }}"></label>
                        <div class="flex items-end gap-4 pb-2"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="registration_enabled" value="1" @checked($event->registration_enabled)> Anmeldung</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="waitlist_enabled" value="1" @checked($event->waitlist_enabled)> Warteliste</label></div>
                        <label class="lg:col-span-2"><span class="text-sm font-semibold">Beschreibung</span><textarea class="cv-input mt-1 min-h-28" name="description">{{ $event->description }}</textarea></label>
                        <label class="lg:col-span-2"><span class="text-sm font-semibold">Interne Notizen</span><textarea class="cv-input mt-1 min-h-24" name="notes">{{ $event->notes }}</textarea></label>
                        <div class="lg:col-span-2"><button class="cv-button-primary">Änderungen speichern</button></div>
                    </form>
                </section>
            @endif
        </div>

        <aside class="space-y-5">
            @if($canRegistrations)
                <section class="cv-panel p-5">
                    <h2 class="font-bold">Mitglieder einladen</h2>
                    <p class="mt-1 text-sm text-slate-500">Empfänger auswählen; E-Mail-Versand erfolgt danach über die Kommunikationszentrale.</p>
                    <form method="post" action="{{ route('events.invite', $event) }}" class="mt-4 space-y-3">@csrf
                        <label class="block"><span class="text-sm font-semibold">Zielgruppe</span><select class="cv-input mt-1" name="target_type"><option value="all_active">Alle aktiven Mitglieder</option><option value="segment">Segment</option><option value="organization">Gliederung</option><option value="member">Einzelmitglied</option></select></label>
                        <label class="block"><span class="text-xs font-semibold">Segment</span><select class="cv-input mt-1" name="member_segment_id"><option value="">–</option>@foreach($segments as $segment)<option value="{{ $segment->id }}">{{ $segment->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-xs font-semibold">Gliederung</span><select class="cv-input mt-1" name="organization_unit_id"><option value="">–</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-xs font-semibold">Einzelmitglied</span><select class="cv-input mt-1" name="member_id"><option value="">–</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
                        <button class="cv-button-primary w-full">Einladungen vormerken</button>
                    </form>
                </section>

                <section class="cv-panel p-5">
                    <h2 class="font-bold">Externen Gast hinzufügen</h2>
                    <form method="post" action="{{ route('events.guests.store', $event) }}" class="mt-4 space-y-3">@csrf
                        <input class="cv-input" name="guest_name" placeholder="Name" required>
                        <input class="cv-input" type="email" name="guest_email" placeholder="E-Mail" required>
                        <button class="cv-button border border-slate-300 bg-white w-full">Gast hinzufügen</button>
                    </form>
                </section>
            @endif

            <section class="cv-panel p-5">
                <h2 class="font-bold">Anmeldung</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Aktiv</dt><dd class="font-semibold">{{ $event->registration_enabled ? 'Ja' : 'Nein' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Kapazität</dt><dd class="font-semibold">{{ $event->capacity ?: 'Unbegrenzt' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Freie Plätze</dt><dd class="font-semibold">{{ $event->capacity ? max(0, $event->capacity - $registered) : '∞' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Anmeldeschluss</dt><dd class="font-semibold">{{ $event->registration_deadline?->format('d.m.Y H:i') ?: 'Keiner' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Serie</dt><dd class="font-semibold">{{ $event->series?->recurrence_type ? ucfirst($event->series->recurrence_type) : 'Einzeltermin' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</x-layouts.app>
