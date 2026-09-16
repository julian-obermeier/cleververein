@php
    $person = $member->person ?? null;
    $contact = $person?->contact_data ?? [];
@endphp

@if($errors->any())
    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold">Bitte prüfen Sie die markierten Angaben.</p>
        <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-6 xl:grid-cols-2">
    <section class="cv-panel p-5">
        <h2 class="text-lg font-bold">Person</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label><span class="cv-label">Anrede</span><input class="cv-input" name="salutation" value="{{ old('salutation', $person?->salutation) }}" placeholder="z. B. Herr, Frau"></label>
            <label><span class="cv-label">Titel</span><input class="cv-input" name="title" value="{{ old('title', $person?->title) }}" placeholder="optional"></label>
            <label><span class="cv-label">Vorname *</span><input required class="cv-input" name="first_name" value="{{ old('first_name', $person?->first_name) }}"></label>
            <label><span class="cv-label">Nachname *</span><input required class="cv-input" name="last_name" value="{{ old('last_name', $person?->last_name) }}"></label>
            <label><span class="cv-label">E-Mail</span><input type="email" class="cv-input" name="email" value="{{ old('email', $person?->email) }}"></label>
            <label><span class="cv-label">Geburtsdatum</span><input type="date" class="cv-input" name="birth_date" value="{{ old('birth_date', $person?->birth_date?->format('Y-m-d')) }}"></label>
            <label><span class="cv-label">Telefon</span><input class="cv-input" name="phone" value="{{ old('phone', $contact['phone'] ?? '') }}"></label>
            <label><span class="cv-label">Mobil</span><input class="cv-input" name="mobile" value="{{ old('mobile', $contact['mobile'] ?? '') }}"></label>
            <label class="sm:col-span-2"><span class="cv-label">Straße / Hausnummer</span><input class="cv-input" name="street" value="{{ old('street', $contact['street'] ?? '') }}"></label>
            <label><span class="cv-label">PLZ</span><input class="cv-input" name="postal_code" value="{{ old('postal_code', $contact['postal_code'] ?? '') }}"></label>
            <label><span class="cv-label">Ort</span><input class="cv-input" name="city" value="{{ old('city', $contact['city'] ?? '') }}"></label>
        </div>
    </section>

    <section class="cv-panel p-5">
        <h2 class="text-lg font-bold">Mitgliedsdaten</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label><span class="cv-label">Mitgliedsnummer</span><input class="cv-input" name="member_number" value="{{ old('member_number', $member->member_number ?? '') }}" placeholder="wird sonst automatisch vergeben"></label>
            <label><span class="cv-label">Status *</span><select required class="cv-input" name="status">@foreach(['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','resigned'=>'Ausgetreten','deceased'=>'Verstorben'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $member->status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="cv-label">Eintritt</span><input type="date" class="cv-input" name="joined_at" value="{{ old('joined_at', isset($member) ? $member->joined_at?->format('Y-m-d') : now()->format('Y-m-d')) }}"></label>
            <label><span class="cv-label">Austritt</span><input type="date" class="cv-input" name="left_at" value="{{ old('left_at', $member->left_at?->format('Y-m-d') ?? '') }}"></label>
            <label class="sm:col-span-2"><span class="cv-label">Interne Notizen</span><textarea class="cv-input min-h-28 py-3" name="notes">{{ old('notes', $member->notes ?? '') }}</textarea></label>
        </div>
    </section>
</div>

@if($includeInitialMembership ?? false)
<section class="cv-panel mt-6 p-5">
    <h2 class="text-lg font-bold">Erste Organisationszuordnung</h2>
    <p class="mt-1 text-sm text-slate-500">Optional. Weitere Mitgliedschaften können später im Mitgliedsprofil ergänzt werden.</p>
    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <label><span class="cv-label">Organisationseinheit</span><select class="cv-input" name="organization_unit_id"><option value="">Noch keine Zuordnung</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string) old('organization_unit_id', $preselectedOrganizationId ?? '') === (string) $organization->id)>{{ $organization->type?->name }} · {{ $organization->name }}</option>@endforeach</select></label>
        <label><span class="cv-label">Mitgliedschaftsart</span><input class="cv-input" name="membership_type" value="{{ old('membership_type', 'Ordentliches Mitglied') }}"></label>
        <label><span class="cv-label">Status</span><select class="cv-input" name="membership_status">@foreach(['active'=>'Aktiv','pending'=>'Vorgemerkt','inactive'=>'Inaktiv','ended'=>'Beendet'] as $value=>$label)<option value="{{ $value }}" @selected(old('membership_status', 'active') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span class="cv-label">Beginn</span><input type="date" class="cv-input" name="membership_starts_at" value="{{ old('membership_starts_at', old('joined_at', now()->format('Y-m-d'))) }}"></label>
        <label><span class="cv-label">Ende</span><input type="date" class="cv-input" name="membership_ends_at" value="{{ old('membership_ends_at') }}"></label>
    </div>
</section>
@endif
