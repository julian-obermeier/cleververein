<x-layouts.app title="Gremien & Sitzungen">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Verbandsarbeit</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Gremien & Sitzungen</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Vorstände, Ausschüsse und Versammlungen mit Tagesordnungen, Teilnahmen, Anträgen, Beschlüssen, Protokollen und daraus entstehenden Aufgaben.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $committees->where('status','active')->count() }} aktive Gremien</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $meetings->count() }} Sitzungen im Verlauf</span>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ $tasks->count() }} offene Aufgaben</span>
        </div>
    </div>

    @if(session('success'))
        <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <section class="cv-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div><h2 class="text-lg font-bold">Gremien</h2><p class="text-sm text-slate-500">Vorstände, Ausschüsse, Arbeitsgruppen und Versammlungen.</p></div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($committees as $committee)
                    <a href="{{ route('governance.committees.show',$committee) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-semibold text-slate-950">{{ $committee->name }}</p>@if($committee->short_name)<span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $committee->short_name }}</span>@endif</div>
                                <p class="mt-1 text-sm text-slate-500">{{ $committee->organizationUnit?->name ?? 'Mandantenweit' }} · {{ match($committee->committee_type){'board'=>'Vorstand','committee'=>'Ausschuss','assembly'=>'Versammlung','working_group'=>'Arbeitsgruppe','advisory'=>'Beirat',default=>'Sonstiges'} }}</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="text-sm font-semibold">{{ $committee->members_count }} Mitglieder</p><span class="text-xs {{ $committee->status === 'active' ? 'text-emerald-700' : 'text-slate-500' }}">{{ $committee->status === 'active' ? 'aktiv' : 'inaktiv' }}</span></div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Noch kein Gremium angelegt.</div>
                @endforelse
            </div>
        </section>

        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Offene Beschlussaufgaben</h2><p class="text-sm text-slate-500">Aufgaben aus Sitzungen und Beschlüssen.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($tasks as $task)
                    <div class="px-5 py-4">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $task->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $task->assignedMember?->person?->display_name ?? 'nicht zugewiesen' }}@if($task->resolution) · {{ $task->resolution->resolution_number }}@endif</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $task->priority === 'high' ? 'bg-red-50 text-red-700' : ($task->priority === 'low' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700') }}">{{ $task->priority === 'high' ? 'hoch' : ($task->priority === 'low' ? 'niedrig' : 'normal') }}</span></div>
                        @if($task->due_at)<p class="mt-2 text-xs {{ $task->due_at->isPast() ? 'font-semibold text-red-700' : 'text-slate-500' }}">Fällig {{ $task->due_at->format('d.m.Y') }}</p>@endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Keine offenen Aufgaben.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Sitzungsverlauf</h2><p class="text-sm text-slate-500">Geplante und vergangene Sitzungen aller Gremien.</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Termin</th><th class="px-4 py-3">Sitzung</th><th class="px-4 py-3">Gremium / Gliederung</th><th class="px-4 py-3">Teilnehmer</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Protokoll</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($meetings as $meeting)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap"><a class="font-semibold text-blue-700" href="{{ route('governance.meetings.show',$meeting) }}">{{ $meeting->starts_at->format('d.m.Y H:i') }}</a></td>
                            <td class="px-4 py-3"><p class="font-medium text-slate-950">{{ $meeting->title }}</p><p class="text-xs text-slate-500">{{ $meeting->location ?: ($meeting->online_url ? 'Online' : 'Ort offen') }}</p></td>
                            <td class="px-4 py-3">{{ $meeting->committee?->name ?? $meeting->organizationUnit?->name ?? 'mandantenweit' }}</td>
                            <td class="px-4 py-3">{{ $meeting->participants_count }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ match($meeting->status){'planned'=>'geplant','in_progress'=>'läuft','closed'=>'abgeschlossen','cancelled'=>'abgesagt',default=>$meeting->status} }}</span></td>
                            <td class="px-4 py-3"><span class="text-xs font-semibold {{ $meeting->minutes_status === 'approved' ? 'text-emerald-700' : 'text-slate-500' }}">{{ match($meeting->minutes_status){'approved'=>'freigegeben','review'=>'zur Prüfung',default=>'Entwurf'} }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Noch keine Sitzung angelegt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($canManage)
        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Gremium anlegen</h2>
                <form method="post" action="{{ route('governance.committees.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    <label class="sm:col-span-2"><span class="cv-label">Name *</span><input required class="cv-input" name="name" value="{{ old('name') }}" placeholder="z. B. Vorstand"></label>
                    <label><span class="cv-label">Kurzname</span><input class="cv-input" name="short_name" value="{{ old('short_name') }}" placeholder="Vorstand"></label>
                    <label><span class="cv-label">Typ *</span><select class="cv-input" name="committee_type"><option value="board">Vorstand</option><option value="committee">Ausschuss</option><option value="assembly">Versammlung</option><option value="working_group">Arbeitsgruppe</option><option value="advisory">Beirat</option><option value="other">Sonstiges</option></select></label>
                    <label class="sm:col-span-2"><span class="cv-label">Gliederung</span><select class="cv-input" name="organization_unit_id"><option value="">Mandantenweit</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Beginn</span><input type="date" class="cv-input" name="starts_at"></label><label><span class="cv-label">Ende</span><input type="date" class="cv-input" name="ends_at"></label>
                    <label class="sm:col-span-2"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-24 py-3" name="description"></textarea></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Gremium anlegen</button></div>
                </form>
            </section>

            <section class="cv-panel p-5">
                <h2 class="text-lg font-bold">Sitzung anlegen</h2>
                <form method="post" action="{{ route('governance.meetings.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    <label class="sm:col-span-2"><span class="cv-label">Titel *</span><input required class="cv-input" name="title" placeholder="Vorstandssitzung September"></label>
                    <label><span class="cv-label">Gremium</span><select class="cv-input" name="committee_id"><option value="">Kein festes Gremium</option>@foreach($committees->where('status','active') as $committee)<option value="{{ $committee->id }}">{{ $committee->name }}</option>@endforeach</select></label>
                    <label><span class="cv-label">Sitzungsart *</span><select class="cv-input" name="meeting_type"><option value="meeting">Sitzung</option><option value="board_meeting">Vorstandssitzung</option><option value="general_assembly">Mitgliederversammlung</option><option value="delegate_assembly">Delegiertenversammlung</option><option value="working_session">Arbeitssitzung</option><option value="other">Sonstiges</option></select></label>
                    <label><span class="cv-label">Beginn *</span><input required type="datetime-local" class="cv-input" name="starts_at"></label><label><span class="cv-label">Ende</span><input type="datetime-local" class="cv-input" name="ends_at"></label>
                    <label><span class="cv-label">Ort</span><input class="cv-input" name="location"></label><label><span class="cv-label">Online-Link</span><input type="url" class="cv-input" name="online_url"></label>
                    <label><span class="cv-label">Beschlussfähig ab</span><input type="number" min="1" class="cv-input" name="quorum_required" placeholder="Anzahl Stimmberechtigte"></label>
                    <label class="flex items-end gap-2 pb-2"><input type="hidden" name="seed_committee_members" value="0"><input type="checkbox" name="seed_committee_members" value="1" checked><span class="text-sm font-medium">Gremienmitglieder einladen</span></label>
                    <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Sitzung anlegen</button></div>
                </form>
            </section>
        </div>
    @endif
</x-layouts.app>
