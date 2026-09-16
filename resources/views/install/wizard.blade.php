<x-layouts.guest title="Installation · cleververein">
    <section class="w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <header class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"><x-logo class="text-xl text-brand-950" /><div><p class="text-sm font-semibold text-slate-900">Einrichtung</p><p class="text-xs text-slate-500">Schritt {{ array_search($step, $steps, true) + 1 }} von {{ count($steps) }}</p></div></header>
        <div class="h-1 bg-slate-100"><div class="h-full bg-blue-600 transition-all" style="width: {{ ((array_search($step, $steps, true)+1)/count($steps))*100 }}%"></div></div>
        <div class="p-6 sm:p-10">
            @if($errors->any())<div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><p class="font-bold">Bitte prüfen Sie Ihre Eingaben.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @if($step === 'requirements')
                <h1 class="text-2xl font-bold">Systemvoraussetzungen</h1><p class="mt-2 text-sm text-slate-500">cleververein prüft PHP, Erweiterungen und Schreibrechte, bevor Daten gespeichert werden.</p>
                <ul class="mt-7 divide-y divide-slate-100 rounded-xl border border-slate-200">@foreach($requirements as $requirement)<li class="flex items-center justify-between gap-3 px-4 py-3"><span class="text-sm font-medium">{{ $requirement['label'] }}</span><span class="text-sm {{ $requirement['ok'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $requirement['ok'] ? '✓' : '✕' }} {{ $requirement['value'] }}</span></li>@endforeach</ul>
                <form method="post" action="{{ route('install.store', $step) }}" class="mt-7 text-right">@csrf<button class="cv-button-primary" @disabled(!collect($requirements)->every('ok'))>Weiter zur Datenbank</button></form>
            @elseif($step === 'database')
                <h1 class="text-2xl font-bold">Datenbank verbinden</h1><p class="mt-2 text-sm text-slate-500">Die Verbindung wird geprüft, bevor Zugangsdaten gespeichert werden.</p>
                <form method="post" action="{{ route('install.store', $step) }}" class="mt-7 grid gap-5 sm:grid-cols-2">@csrf
                    <div><label class="cv-label" for="db_host">Server</label><input class="cv-input" id="db_host" name="db_host" value="{{ old('db_host','localhost') }}" required></div><div><label class="cv-label" for="db_port">Port</label><input class="cv-input" id="db_port" name="db_port" type="number" value="{{ old('db_port','3306') }}" required></div>
                    <div class="sm:col-span-2"><label class="cv-label" for="db_database">Datenbankname</label><input class="cv-input" id="db_database" name="db_database" value="{{ old('db_database') }}" required></div><div><label class="cv-label" for="db_username">Benutzername</label><input class="cv-input" id="db_username" name="db_username" autocomplete="off" required></div><div><label class="cv-label" for="db_password">Passwort</label><input class="cv-input" id="db_password" name="db_password" type="password" autocomplete="new-password"></div>
                    <div class="sm:col-span-2 text-right"><button class="cv-button-primary">Verbindung prüfen</button></div>
                </form>
            @elseif($step === 'application')
                <h1 class="text-2xl font-bold">Anwendung konfigurieren</h1><p class="mt-2 text-sm text-slate-500">Domain, Produktname und Absender für Systemnachrichten.</p>
                <form method="post" action="{{ route('install.store', $step) }}" class="mt-7 grid gap-5 sm:grid-cols-2">@csrf
                    <div><label class="cv-label" for="app_name">Anwendungsname</label><input class="cv-input" id="app_name" name="app_name" value="{{ old('app_name','cleververein') }}" required></div><div><label class="cv-label" for="app_url">Hauptdomain</label><input class="cv-input" id="app_url" name="app_url" type="url" value="{{ old('app_url', request()->getSchemeAndHttpHost()) }}" required></div>
                    <div><label class="cv-label" for="mail_from_address">Absenderadresse</label><input class="cv-input" id="mail_from_address" name="mail_from_address" type="email" value="{{ old('mail_from_address') }}" required></div><div><label class="cv-label" for="mail_from_name">Absendername</label><input class="cv-input" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name','cleververein') }}" required></div>
                    <div class="sm:col-span-2 text-right"><button class="cv-button-primary">Weiter zum Administratorkonto</button></div>
                </form>
            @elseif($step === 'account')
                <h1 class="text-2xl font-bold">Ersten Mandanten anlegen</h1><p class="mt-2 text-sm text-slate-500">Dieses Konto erhält die globale Administration. Verwenden Sie ein einzigartiges, starkes Passwort.</p>
                <form method="post" action="{{ route('install.store', $step) }}" class="mt-7 grid gap-5 sm:grid-cols-2">@csrf
                    <div><label class="cv-label" for="first_name">Vorname</label><input class="cv-input" id="first_name" name="first_name" value="{{ old('first_name') }}" required></div><div><label class="cv-label" for="last_name">Nachname</label><input class="cv-input" id="last_name" name="last_name" value="{{ old('last_name') }}" required></div>
                    <div class="sm:col-span-2"><label class="cv-label" for="tenant_name">Name des Vereins oder Verbands</label><input class="cv-input" id="tenant_name" name="tenant_name" value="{{ old('tenant_name') }}" required></div><div class="sm:col-span-2"><label class="cv-label" for="email">E-Mail-Adresse</label><input class="cv-input" id="email" name="email" type="email" value="{{ old('email') }}" required></div>
                    <div><label class="cv-label" for="password">Passwort</label><input class="cv-input" id="password" name="password" type="password" minlength="12" required></div><div><label class="cv-label" for="password_confirmation">Passwort wiederholen</label><input class="cv-input" id="password_confirmation" name="password_confirmation" type="password" minlength="12" required></div>
                    <div class="sm:col-span-2 text-right"><button class="cv-button-primary">Installation abschließen</button></div>
                </form>
            @else
                <div class="py-10 text-center"><div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700">✓</div><h1 class="mt-5 text-2xl font-bold">Installation abgeschlossen</h1><p class="mt-2 text-sm text-slate-500">Der Installer wurde gesperrt. Sie können sich jetzt anmelden.</p><a href="{{ route('login') }}" class="cv-button-primary mt-7">Zur Anmeldung</a></div>
            @endif
        </div>
    </section>
</x-layouts.guest>
