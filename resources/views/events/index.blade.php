<x-layouts.app title="Veranstaltungen">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Kalender</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Veranstaltungen & Termine</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Vereinstermine, Schulungen, Versammlungen und Serienveranstaltungen zentral planen und verwalten.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('events.index', ['month' => $month->copy()->subMonth()->format('Y-m-d')]) }}" class="cv-button border border-slate-300 bg-white">← {{ $month->copy()->subMonth()->translatedFormat('M Y') }}</a>
            <a href="{{ route('events.index', ['month' => now()->startOfMonth()->format('Y-m-d')]) }}" class="cv-button border border-slate-300 bg-white">Heute</a>
            <a href="{{ route('events.index', ['month' => $month->copy()->addMonth()->format('Y-m-d')]) }}" class="cv-button border border-slate-300 bg-white">{{ $month->copy()->addMonth()->translatedFormat('M Y') }} →</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.7fr)_minmax(360px,.65fr)]">
        <section class="cv-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div><h2 class="text-xl font-bold">{{ $month->translatedFormat('F Y') }}</h2><p class="mt-1 text-sm text-slate-500">{{ $events->count() }} Termin(e) in diesem Monat</p></div>
                <a href="{{ route('communications.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">Kommunikationszentrale</a>
            </div>
            @php
                $calendarStart = $month->copy()->startOfMonth()->startOfWeek();
                $calendarEnd = $month->copy()->endOfMonth()->endOfWeek();
                $days = \Carbon\CarbonPeriod::create($calendarStart, $calendarEnd);
                $eventsByDay = $events->groupBy(fn($event) => $event->starts_at->format('Y-m-d'));
            @endphp
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-bold uppercase tracking-wide text-slate-500">
                @foreach(['Mo','Di','Mi','Do','Fr','Sa','So'] as $day)<div class="px-2 py-3">{{ $day }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7">
                @foreach($days as $day)
                    @php($dayEvents = $eventsByDay[$day->format('Y-m-d')] ?? collect())
                    <div class="min-h-32 border-b border-r border-slate-100 p-2 {{ $day->month !== $month->month ? 'bg-slate-50/70' : 'bg-white' }}">
                        <div class="mb-2 flex items-center justify-between"><span class="grid h-7 w-7 place-items-center rounded-full text-xs font-bold {{ $day->isToday() ? 'bg-blue-700 text-white' : ($day->month !== $month->month ? 'text-slate-300' : 'text-slate-600') }}">{{ $day->day }}</span></div>
                        <div class="space-y-1.5">
                            @foreach($dayEvents as $event)
                                <a href="{{ route('events.show', $event) }}" class="block rounded-md border border-blue-100 bg-blue-50 px-2 py-1.5 text-xs transition hover:border-blue-300 hover:bg-blue-100">
                                    <span class="block font-bold text-blue-950">{{ $event->starts_at->format('H:i') }} · {{ $event->title }}</span>
                                    <span class="mt-0.5 block truncate text-blue-700">{{ $event->location ?: 'Ohne Ortsangabe' }} · {{ $event->registered_count }} Zusagen</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if($canManage)
            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Neue Veranstaltung</h2>
                <p class="mt-1 text-sm text-slate-500">Einzel- oder Serientermin anlegen.</p>
                <form method="post" action="{{ route('events.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <label class="block"><span class="text-sm font-semibold">Titel</span><input class="cv-input mt-1" name="title" value="{{ old('title') }}" required></label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label><span class="text-sm font-semibold">Typ</span><select class="cv-input mt-1" name="event_type"><option value="event">Veranstaltung</option><option value="meeting">Termin/Sitzung</option><option value="training">Schulung</option><option value="celebration">Feier</option><option value="trip">Ausflug</option><option value="assembly">Versammlung</option><option value="other">Sonstiges</option></select></label>
                        <label><span class="text-sm font-semibold">Gliederung</span><select class="cv-input mt-1" name="organization_unit_id"><option value="">Mandantenweit</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
                    </div>
                    <label class="block"><span class="text-sm font-semibold">Beschreibung</span><textarea class="cv-input mt-1 min-h-24" name="description">{{ old('description') }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label><span class="text-sm font-semibold">Beginn</span><input class="cv-input mt-1" type="datetime-local" name="starts_at" required></label>
                        <label><span class="text-sm font-semibold">Ende</span><input class="cv-input mt-1" type="datetime-local" name="ends_at"></label>
                    </div>
                    <label class="block"><span class="text-sm font-semibold">Ort</span><input class="cv-input mt-1" name="location"></label>
                    <label class="block"><span class="text-sm font-semibold">Online-Link</span><input class="cv-input mt-1" type="url" name="online_url"></label>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="font-semibold">Anmeldung</p>
                        <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="registration_enabled" value="1" checked> Anmeldung erlauben</label>
                        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="waitlist_enabled" value="1" checked> Warteliste bei voller Kapazität</label>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2"><label><span class="text-xs font-semibold">Kapazität</span><input class="cv-input mt-1" type="number" min="1" name="capacity"></label><label><span class="text-xs font-semibold">Anmeldeschluss</span><input class="cv-input mt-1" type="datetime-local" name="registration_deadline"></label></div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="font-semibold">Serientermin</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <label><span class="text-xs font-semibold">Wiederholung</span><select class="cv-input mt-1" name="recurrence_type"><option value="none">Keine</option><option value="daily">Täglich</option><option value="weekly">Wöchentlich</option><option value="monthly">Monatlich</option></select></label>
                            <label><span class="text-xs font-semibold">Intervall</span><input class="cv-input mt-1" type="number" min="1" max="52" name="recurrence_interval" value="1"></label>
                            <label><span class="text-xs font-semibold">Anzahl</span><input class="cv-input mt-1" type="number" min="1" max="104" name="recurrence_count"></label>
                        </div>
                        <label class="mt-3 block"><span class="text-xs font-semibold">Optional bis</span><input class="cv-input mt-1" type="date" name="recurrence_until"></label>
                    </div>
                    <button class="cv-button-primary w-full">Veranstaltung anlegen</button>
                </form>
            </section>
        @endif
    </div>
</x-layouts.app>
