<x-layouts.app title="Mitglied anlegen">
    <div class="flex items-center justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitglieder</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Mitglied anlegen</h1><p class="mt-1 text-sm text-slate-500">Person, Mitgliedsstammdaten und optional die erste Organisationszuordnung erfassen.</p></div>
        <a class="cv-button border border-slate-300 bg-white text-slate-700" href="{{ route('members.index') }}">Zurück</a>
    </div>

    <form method="post" action="{{ route('members.store') }}" class="mt-6">
        @csrf
        @include('members._form', ['includeInitialMembership' => true])
        <div class="mt-6 flex justify-end gap-3"><a href="{{ route('members.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Abbrechen</a><button class="cv-button-primary" type="submit">Mitglied speichern</button></div>
    </form>
</x-layouts.app>
