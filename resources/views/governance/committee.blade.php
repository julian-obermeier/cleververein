<x-layouts.app title="{{ $committee->name }}">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('governance.index') }}" class="text-sm font-semibold text-blue-700">← Gremien & Sitzungen</a>
            <div class="mt-2 flex flex-wrap items-center gap-2"><h1 class="text-3xl font-bold tracking-tight">{{ $committee->name }}</h1>@if($committee->short_name)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $committee->short_name }}</span>@endif</div>
            <p class="mt-2 text-sm text-slate-500">{{ $committee->organizationUnit?->name ?? 'Mandantenweit' }} · {{ match($committee->committee_type){'board'=>'Vorstand','committee'=>'Ausschuss','assembly'=>'Versammlung','working_group'=>'Arbeitsgruppe','advisory'=>'Beirat',default=>'Sonstiges'} }}</p>
            @if($committee->description)<p class="mt-3 max-w-3xl text-sm text-slate-600">{{ $committee->description }}</p>@endif
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $committee->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $committee->status === 'active' ? 'aktiv' : 'inaktiv' }}</span>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Gremienmitglieder</h2><p class="text-sm text-slate-500">Historische Zugehörigkeiten bleiben erhalten und werden nicht gelöscht.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($committee->members as $assignment)
                    <div class="px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold">{{ $assignment->member?->person?->display_name ?? 'Mitglied nicht mehr vorhanden' }}</p>@if($assignment->is_chair)<span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Vorsitz</span>@endif @if($assignment->has_voting_right)<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">stimmberechtigt</span>@endif</div><p class="mt-1 text-sm text-slate-500">{{ $assignment->role_name ?: 'Mitglied' }} · {{ $assignment->starts_at?->format('d.m.Y') ?: 'Beginn offen' }} – {{ $assignment->ends_at?->format('d.m.Y') ?: 'offen' }}</p></div>
                            <div class="flex items-center gap-2"><span class="text-xs font-semibold {{ $assignment->status === 'active' ? 'text-emerald-700' : 'text-slate-500' }}">{{ $assignment->status === 'active' ? 'aktiv' : 'beendet' }}</span>@if($canManage && $assignment->status === 'active')<form method="post" action="{{ route('governance.committees.members.end',[$committee,$assignment]) }}">@csrf @method('patch')<button class="text-xs font-semibold text-red-700">Zugehörigkeit beenden</button></form>@endif</div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Noch keine Mitglieder im Gremium.</div>
                @endforelse
            </div>
        </section>

        @if($canManage)
        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Mitglied hinzufügen</h2>
            <form method="post" action="{{ route('governance.committees.members.store',$committee) }}" class="mt-4 grid gap-3">@csrf
                <label><span class="cv-label">Mitglied *</span><select required class="cv-input" name="member_id"><option value="">Bitte wählen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Rolle / Bezeichnung</span><input class="cv-input" name="role_name" placeholder="z. B. Vorsitz, Beisitz, Schriftführung"></label>
                <div class="grid gap-3 sm:grid-cols-2"><label><span class="cv-label">Beginn</span><input type="date" class="cv-input" name="starts_at" value="{{ now()->toDateString() }}"></label><label><span class="cv-label">Ende</span><input type="date" class="cv-input" name="ends_at"></label></div>
                <div class="grid gap-2 sm:grid-cols-2"><label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3"><input type="hidden" name="is_chair" value="0"><input type="checkbox" name="is_chair" value="1"><span class="text-sm font-medium">Vorsitz</span></label><label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3"><input type="hidden" name="has_voting_right" value="0"><input type="checkbox" name="has_voting_right" value="1" checked><span class="text-sm font-medium">Stimmberechtigt</span></label></div>
                <div class="flex justify-end"><button class="cv-button-primary">Mitglied hinzufügen</button></div>
            </form>
        </section>
        @endif
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Sitzungen dieses Gremiums</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse($committee->meetings as $meeting)
                <a href="{{ route('governance.meetings.show',$meeting) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold text-blue-700">{{ $meeting->title }}</p><p class="text-sm text-slate-500">{{ $meeting->starts_at->format('d.m.Y H:i') }} · {{ $meeting->location ?: 'Ort offen' }}</p></div><div class="text-xs font-semibold text-slate-500">{{ match($meeting->status){'planned'=>'geplant','in_progress'=>'läuft','closed'=>'abgeschlossen','cancelled'=>'abgesagt',default=>$meeting->status} }} · Protokoll {{ match($meeting->minutes_status){'approved'=>'freigegeben','review'=>'in Prüfung',default=>'Entwurf'} }}</div></a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500">Noch keine Sitzung für dieses Gremium.</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
