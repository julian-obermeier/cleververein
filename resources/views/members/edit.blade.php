<x-layouts.app title="Mitglied bearbeiten">
    <div class="flex items-center justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitglieder</p><h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $member->person->display_name }} bearbeiten</h1><p class="mt-1 text-sm text-slate-500">Personen- und Mitgliedsstammdaten aktualisieren.</p></div>
        <a class="cv-button border border-slate-300 bg-white text-slate-700" href="{{ route('members.show', $member) }}">Zurück</a>
    </div>

    <form method="post" action="{{ route('members.update', $member) }}" class="mt-6">
        @csrf
        @method('put')
        @include('members._form', ['includeInitialMembership' => false])
        <div class="mt-6 flex justify-end gap-3"><a href="{{ route('members.show', $member) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Abbrechen</a><button class="cv-button-primary" type="submit">Änderungen speichern</button></div>
    </form>
</x-layouts.app>
