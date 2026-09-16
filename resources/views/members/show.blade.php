<x-layouts.app title="Mitglied">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitglied {{ $member->member_number }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $member->person->display_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Mitglied seit {{ $member->joined_at?->format('d.m.Y') ?: 'unbekannt' }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('members.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Zur Liste</a><a href="{{ route('members.edit', $member) }}" class="cv-button-primary">Bearbeiten</a></div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @php($contact = $member->person->contact_data ?? [])
    @php($meta = $member->meta ?? [])
    <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
        <section class="cv-panel p-5">
            <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Stammdaten</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ ['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben'][$member->status] ?? $member->status }}</span></div>
            <dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2 xl:grid-cols-3 text-sm">
                <div><dt class="text-slate-500">Mitgliedsnummer</dt><dd class="mt-1 font-semibold">{{ $member->member_number }}</dd></div>
                <div><dt class="text-slate-500">Geburtsdatum</dt><dd class="mt-1 font-semibold">{{ $member->person->birth_date?->format('d.m.Y') ?: '–' }}</dd></div>
                <div><dt class="text-slate-500">Geschlecht</dt><dd class="mt-1 font-semibold">{{ $contact['gender'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Nationalität</dt><dd class="mt-1 font-semibold">{{ $contact['nationality'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Beruf / Tätigkeit</dt><dd class="mt-1 font-semibold">{{ $contact['occupation'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Bevorzugte Sprache</dt><dd class="mt-1 font-semibold">{{ $contact['preferred_language'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">E-Mail</dt><dd class="mt-1 font-semibold">{{ $member->person->email ?: '–' }}</dd></div>
                <div><dt class="text-slate-500">Telefon</dt><dd class="mt-1 font-semibold">{{ $contact['phone'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Mobil</dt><dd class="mt-1 font-semibold">{{ $contact['mobile'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Bevorzugter Kontakt</dt><dd class="mt-1 font-semibold">{{ ['email'=>'E-Mail','phone'=>'Telefon','mobile'=>'Mobil','post'=>'Post'][$contact['communication_preference'] ?? ''] ?? '–' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Adresse</dt><dd class="mt-1 font-semibold">{{ trim(($contact['street'] ?? '').', '.($contact['postal_code'] ?? '').' '.($contact['city'] ?? '').' '.($contact['country'] ?? ''), ', ') ?: '–' }}</dd></div>
                <div><dt class="text-slate-500">Austritt</dt><dd class="mt-1 font-semibold">{{ $member->left_at?->format('d.m.Y') ?: '–' }}</dd></div>
                <div><dt class="text-slate-500">Notfallkontakt</dt><dd class="mt-1 font-semibold">{{ $contact['emergency_contact'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Notfall-Telefon</dt><dd class="mt-1 font-semibold">{{ $contact['emergency_phone'] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Newsletter</dt><dd class="mt-1 font-semibold">{{ ($meta['newsletter'] ?? false) ? 'Ja' : 'Nein' }}</dd></div>
            </dl>
            @if(!empty($meta['tags']))<div class="mt-5 flex flex-wrap gap-2">@foreach($meta['tags'] as $tag)<span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $tag }}</span>@endforeach</div>@endif
            @if($member->notes)<div class="mt-6 border-t border-slate-200 pt-5"><p class="text-sm font-semibold">Interne Notizen</p><p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $member->notes }}</p></div>@endif
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Aktionen</h2>
            <div class="mt-4 grid gap-2"><a href="{{ route('members.edit', $member) }}" class="cv-button border border-slate-300 bg-white text-center text-slate-700">Stammdaten bearbeiten</a><a href="{{ route('members.settings') }}" class="cv-button border border-slate-300 bg-white text-center text-slate-700">Mitglieder-Stammdaten</a></div>
            <p class="mt-5 text-sm text-slate-500">Austritt und Archivierung bleiben im Audit-Log nachvollziehbar.</p>
            <form method="post" action="{{ route('members.archive', $member) }}" class="mt-4" onsubmit="return confirm('Mitglied wirklich archivieren?');">@csrf @method('delete')<button class="cv-button w-full border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">Mitglied archivieren</button></form>
        </section>
    </div>

    <section class="cv-panel mt-4 overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-bold">Mitgliedschaften</h2><p class="text-sm text-slate-500">Eine Person kann mehreren Gliederungen gleichzeitig zugeordnet sein.</p></div><span class="text-sm text-slate-500">{{ $member->memberships->count() }} Zuordnung(en)</span></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[780px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Organisation</th><th class="px-5 py-3">Art</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Zeitraum</th><th class="px-5 py-3">Primär</th><th class="px-5 py-3 text-right"></th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($member->memberships as $membership)<tr><td class="px-5 py-4"><p class="font-semibold">{{ $membership->organizationUnit?->name ?? 'Keine Organisation' }}</p><p class="text-xs text-slate-500">{{ $membership->organizationUnit?->type?->name }}</p></td><td class="px-5 py-4">{{ $membership->memberType?->name ?? $membership->membership_type }}</td><td class="px-5 py-4">{{ ['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','ended'=>'Beendet'][$membership->status] ?? $membership->status }}</td><td class="px-5 py-4">{{ $membership->starts_at?->format('d.m.Y') ?: '–' }} – {{ $membership->ends_at?->format('d.m.Y') ?: 'offen' }}</td><td class="px-5 py-4">{{ $membership->is_primary ? 'Ja' : 'Nein' }}</td><td class="px-5 py-4 text-right"><form method="post" action="{{ route('members.memberships.destroy', [$member, $membership]) }}" onsubmit="return confirm('Zuordnung entfernen?');">@csrf @method('delete')<button class="font-semibold text-red-700 hover:underline">Entfernen</button></form></td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Noch keine Organisationszuordnung.</td></tr>@endforelse</tbody></table></div>
        <form method="post" action="{{ route('members.memberships.store', $member) }}" class="grid gap-4 border-t border-slate-200 p-5 md:grid-cols-2 xl:grid-cols-6">@csrf
            <label class="xl:col-span-2"><span class="cv-label">Organisation *</span><select required class="cv-input" name="organization_unit_id"><option value="">Bitte wählen</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->type?->name }} · {{ $organization->name }}</option>@endforeach</select></label>
            <label><span class="cv-label">Mitgliedsart</span><select class="cv-input" name="member_type_id"><option value="">Freie / alte Angabe</option>@foreach($memberTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
            <label><span class="cv-label">Status *</span><select class="cv-input" name="status"><option value="active">Aktiv</option><option value="pending">Vorgemerkt</option><option value="inactive">Inaktiv</option><option value="ended">Beendet</option></select></label>
            <label><span class="cv-label">Beginn</span><input type="date" class="cv-input" name="starts_at" value="{{ now()->format('Y-m-d') }}"></label>
            <label><span class="cv-label">Primär</span><select class="cv-input" name="is_primary"><option value="0">Nein</option><option value="1">Ja</option></select></label>
            <div class="md:col-span-2 xl:col-span-6 flex justify-end"><button class="cv-button-primary" type="submit">Zuordnung hinzufügen</button></div>
        </form>
    </section>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Ämter & Funktionen</h2><p class="text-sm text-slate-500">Funktionen können organisationsbezogen und zeitlich begrenzt vergeben werden.</p></div>
            <div class="divide-y divide-slate-100">@forelse($member->functionAssignments as $assignment)<div class="flex items-start justify-between gap-4 px-5 py-4"><div><p class="font-semibold">{{ $assignment->definition?->name }}</p><p class="text-sm text-slate-500">{{ $assignment->organizationUnit?->name ?? 'Mandantenweit' }} · {{ $assignment->starts_at?->format('d.m.Y') ?: 'ohne Beginn' }} – {{ $assignment->ends_at?->format('d.m.Y') ?: 'offen' }}</p></div><form method="post" action="{{ route('members.functions.destroy', [$member, $assignment]) }}" onsubmit="return confirm('Funktionszuweisung entfernen?');">@csrf @method('delete')<button class="text-sm font-semibold text-red-700">Entfernen</button></form></div>@empty<div class="px-5 py-6 text-sm text-slate-500">Noch keine Funktion zugewiesen.</div>@endforelse</div>
            <form method="post" action="{{ route('members.functions.store', $member) }}" class="grid gap-3 border-t border-slate-200 p-5 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Funktion *</span><select required class="cv-input" name="function_definition_id"><option value="">Bitte wählen</option>@foreach($functions as $function)<option value="{{ $function->id }}">{{ $function->category ? $function->category.' · ' : '' }}{{ $function->name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Organisation</span><select class="cv-input" name="organization_unit_id"><option value="">Mandantenweit</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Beginn</span><input type="date" class="cv-input" name="starts_at"></label>
                <label><span class="cv-label">Ende</span><input type="date" class="cv-input" name="ends_at"></label>
                <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Funktion zuweisen</button></div>
            </form>
        </section>

        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Haushalt / Familie</h2><p class="text-sm text-slate-500">Mehrere Mitglieder können zu einem gemeinsamen Haushalt zusammengefasst werden.</p></div>
            <div class="divide-y divide-slate-100">@forelse($member->households as $household)<div class="flex items-start justify-between gap-4 px-5 py-4"><div><p class="font-semibold">{{ $household->name }}</p><p class="text-sm text-slate-500">{{ $household->members->count() }} Mitglied(er) · {{ $household->pivot->relationship ?: 'keine Beziehung angegeben' }}{{ $household->pivot->is_primary_contact ? ' · Hauptkontakt' : '' }}</p><p class="mt-1 text-xs text-slate-400">{{ $household->members->pluck('person.display_name')->filter()->implode(', ') }}</p></div><form method="post" action="{{ route('members.households.destroy', [$member, $household]) }}" onsubmit="return confirm('Haushaltszuordnung entfernen?');">@csrf @method('delete')<button class="text-sm font-semibold text-red-700">Entfernen</button></form></div>@empty<div class="px-5 py-6 text-sm text-slate-500">Noch keinem Haushalt zugeordnet.</div>@endforelse</div>
            <form method="post" action="{{ route('members.households.store', $member) }}" class="grid gap-3 border-t border-slate-200 p-5 sm:grid-cols-2">@csrf
                <label><span class="cv-label">Bestehender Haushalt</span><select class="cv-input" name="household_id"><option value="">Neuen Haushalt anlegen</option>@foreach($households as $household)<option value="{{ $household->id }}">{{ $household->name }}</option>@endforeach</select></label>
                <label><span class="cv-label">Name bei Neuanlage</span><input class="cv-input" name="name" placeholder="z. B. Familie Müller"></label>
                <label><span class="cv-label">Beziehung</span><input class="cv-input" name="relationship" placeholder="z. B. Elternteil, Kind, Partner"></label>
                <label><span class="cv-label">Hauptkontakt</span><select class="cv-input" name="is_primary_contact"><option value="0">Nein</option><option value="1">Ja</option></select></label>
                <div class="sm:col-span-2 flex justify-end"><button class="cv-button-primary">Haushalt zuordnen</button></div>
            </form>
        </section>
    </div>
</x-layouts.app>
