<x-layouts.guest title="Anmelden · cleververein">
    <section class="grid w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60 lg:grid-cols-[1fr_1.05fr]">
        <div class="hidden bg-brand-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <x-logo class="text-2xl" />
            <div><h1 class="max-w-sm text-4xl font-bold leading-tight">Vereinsarbeit, die Menschen zusammenbringt.</h1><p class="mt-4 max-w-md text-slate-300">Mitglieder, Gliederungen und Entscheidungen in einem sicheren Arbeitsbereich organisieren.</p></div>
            <p class="text-xs text-slate-400">Mandantenfähig · nachvollziehbar · in Deutschland betreibbar</p>
        </div>
        <div class="p-7 sm:p-12">
            <x-logo class="mb-12 text-xl text-brand-950 lg:hidden" />
            <p class="text-sm font-semibold text-blue-700">Willkommen zurück</p><h2 class="mt-2 text-3xl font-bold tracking-tight">Bei cleververein anmelden</h2><p class="mt-2 text-sm text-slate-500">Verwenden Sie Ihr persönliches Benutzerkonto.</p>
            <form method="post" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
                <div><label for="email" class="cv-label">E-Mail-Adresse</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" class="cv-input" required autofocus>@error('email')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><div class="flex justify-between"><label for="password" class="cv-label">Passwort</label><a href="{{ route('password.request') }}" class="text-sm font-semibold text-blue-700 hover:underline">Vergessen?</a></div><input id="password" name="password" type="password" autocomplete="current-password" class="cv-input" required></div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-600"> Angemeldet bleiben</label>
                <button class="cv-button-primary w-full">Sicher anmelden</button>
            </form>
        </div>
    </section>
</x-layouts.guest>
