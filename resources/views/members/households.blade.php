<x-layouts.app title="Haushalte & Familien">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Haushalte & Familien</h1>
            <p class="mt-1 text-sm text-slate-500">Mitglieder zu gemeinsamen Haushalten zusammenfassen, Ansprechpartner kennzeichnen und Kontaktdaten zentral pflegen.</p>
        </div>
        <a href="{{ route('members.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zur Mitgliederliste</a>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 xl:grid-cols-[1.6fr_1fr]">
        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 p-5">
                <form method="get" class="flex gap-2">
                    <input class="cv-input" name="q" value="{{ $search }}" placeholder="Haushalt oder Mitglied suchen …">
                    <button class="cv-button-primary">Suchen</button>
                    @if($search)<a href="{{ route('members.households.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zurücksetzen</a>@endif
                </form>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($households as $household)
                    @php($contact = $household->contact_data ?? [])
                    <article class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-bold">{{ $household->name }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $household->members_count }} Mitglieder</span></div>
                                <p class="mt-1 text-sm text-slate-500">{{ $contact['street'] ?? 'Keine Straße' }}{{ isset($contact['postal_code']) || isset($contact['city']) ? ' · '.trim(($contact['postal_code'] ?? '').' '.($contact['city'] ?? '')) : '' }}</p>
                                @if(isset($contact['email']) || isset($contact['phone']))<p class="mt-1 text-xs text-slate-500">{{ $contact['email'] ?? '' }}{{ isset($contact['email']) && isset($contact['phone']) ? ' · ' : '' }}{{ $contact['phone'] ?? '' }}</p>@endif
                            </div>
                            <form method="post" action="{{ route('members.households.archive', $household) }}" onsubmit="return confirm('Haushalt wirklich archivieren?')">@csrf @method('delete')<button class="text-sm font-semibold text-red-700">Archivieren</button></form>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse($household->members as $member)
                                <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm">
                                    <a href="{{ route('members.show', $member) }}" class="font-semibold text-slate-800 hover:text-blue-700">{{ $member->person->display_name }}</a>
                                    @if($member->pivot->relationship)<span class="text-xs text-slate-500">{{ $member->pivot->relationship }}</span>@endif
                                    @if($member->pivot->is_primary_contact)<span title="Hauptansprechpartner" class="text-amber-600">★</span>@endif
                                    <form method="post" action="{{ route('members.households.members.destroy', [$household, $member]) }}">@csrf @method('delete')<button class="text-slate-400 hover:text-red-600" title="Zuordnung entfernen">×</button></form>
                                </div>
                            @empty
                                <span class="text-sm text-slate-400">Noch keine Mitglieder zugeordnet.</span>
                            @endforelse
                        </div>

                        <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Haushalt bearbeiten / Mitglied hinzufügen</summary>
                            <div class="mt-4 grid gap-5 lg:grid-cols-2">
                                <form method="post" action="{{ route('members.households.update', $household) }}" class="grid gap-3 sm:grid-cols-2">@csrf @method('put')
                                    <label class="sm:col-span-2"><span class="cv-label">Name *</span><input required class="cv-input bg-white" name="name" value="{{ $household->name }}"></label>
                                    <label><span class="cv-label">E-Mail</span><input type="email" class="cv-input bg-white" name="email" value="{{ $contact['email'] ?? '' }}"></label>
                                    <label><span class="cv-label">Telefon</span><input class="cv-input bg-white" name="phone" value="{{ $contact['phone'] ?? '' }}"></label>
                                    <label class="sm:col-span-2"><span class="cv-label">Straße</span><input class="cv-input bg-white" name="street" value="{{ $contact['street'] ?? '' }}"></label>
                                    <label><span class="cv-label">PLZ</span><input class="cv-input bg-white" name="postal_code" value="{{ $contact['postal_code'] ?? '' }}"></label>
                                    <label><span class="cv-label">Ort</span><input class="cv-input bg-white" name="city" value="{{ $contact['city'] ?? '' }}"></label>
                                    <label class="sm:col-span-2"><span class="cv-label">Notizen</span><textarea class="cv-input min-h-20 bg-white py-3" name="notes">{{ $household->notes }}</textarea></label>
                                    <div class="sm:col-span-2"><button class="cv-button-primary">Änderungen speichern</button></div>
                                </form>
                                <form method="post" action="{{ route('members.households.members.store', $household) }}" class="grid content-start gap-3">@csrf
                                    <label><span class="cv-label">Mitglied *</span><select required class="cv-input bg-white" name="member_id"><option value="">Bitte wählen</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select></label>
                                    <label><span class="cv-label">Beziehung</span><input class="cv-input bg-white" name="relationship" placeholder="z. B. Ehepartner, Kind"></label>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_primary_contact" value="1"> Hauptansprechpartner</label>
                                    <div><button class="cv-button-primary">Mitglied hinzufügen</button></div>
                                </form>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">Noch keine Haushalte vorhanden.</div>
                @endforelse
            </div>
            @if($households->hasPages())<div class="border-t border-slate-200 p-4">{{ $households->links() }}</div>@endif
        </section>

        <aside class="cv-panel h-fit p-5">
            <h2 class="text-lg font-bold">Neuen Haushalt anlegen</h2>
            <p class="mt-1 text-sm text-slate-500">Optional kann direkt ein erstes Mitglied zugeordnet werden.</p>
            <form method="post" action="{{ route('members.households.central.store') }}" class="mt-5 grid gap-3">@csrf
                <label><span class="cv-label">Haushaltsname *</span><input required class="cv-input" name="name" placeholder="z. B. Familie Mustermann"></label>
                <label><span class="cv-label">E-Mail</span><input type="email" class="cv-input" name="email"></label>
                <label><span class="cv-label">Telefon</span><input class="cv-input" name="phone"></label>
                <label><span class="cv-label">Straße</span><input class="cv-input" name="street"></label>
                <div class="grid grid-cols-[.7fr_1.3fr] gap-3"><label><span class="cv-label">PLZ</span><input class="cv-input" name="postal_code"></label><label><span class="cv-label">Ort</span><input class="cv-input" name="city"></label></div>
                <label><span class="cv-label">Erstes Mitglied</span><select class="cv-input" name="member_id"><option value="">Keines</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Beziehung</span><input class="cv-input" name="relationship"></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_primary_contact" value="1"> Hauptansprechpartner</label>
                <label><span class="cv-label">Notizen</span><textarea class="cv-input min-h-20 py-3" name="notes"></textarea></label>
                <button class="cv-button-primary">Haushalt anlegen</button>
            </form>
        </aside>
    </div>
</x-layouts.app>
