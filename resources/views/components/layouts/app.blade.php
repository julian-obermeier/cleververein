<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Übersicht' }} · cleververein</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Zum Inhalt springen</a>
<div data-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-slate-950/40 lg:hidden"></div>
<aside data-sidebar class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-brand-950 text-slate-100 transition-transform lg:translate-x-0" aria-label="Hauptnavigation">
    <div class="flex h-16 items-center border-b border-white/10 px-5"><x-logo class="text-xl text-white" /></div>
    <div class="mx-3 mt-4 rounded-lg border border-white/15 bg-white/5 p-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth()->user()->currentTenant->name }}</p><p class="text-xs text-slate-400">{{ ucfirst(auth()->user()->currentTenant->plan) }}</p></div>
            <span aria-hidden="true">⌄</span>
        </div>
    </div>
    @php($items = [['Übersicht','dashboard','M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm8 0h8V11h-8v9Zm0-16v5h8V4h-8Z']])
    <nav class="mt-4 flex-1 px-2">
        @foreach($items as [$label,$route,$path])
            <a href="{{ route($route) }}" aria-current="{{ request()->routeIs($route) ? 'page' : 'false' }}" class="flex items-center gap-3 rounded-lg border-l-2 px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) ? 'border-blue-400 bg-blue-600/35 text-white' : 'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>{{ $label }}
            </a>
        @endforeach
    </nav>
    <div class="border-t border-white/10 p-3 text-sm text-slate-400">Hilfe & Support<br><span class="text-xs">Version 0.1.0 · Fundament</span></div>
</aside>

<div class="min-h-screen lg:pl-64">
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
        <button data-sidebar-toggle aria-expanded="false" aria-controls="app-sidebar" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"><span class="sr-only">Navigation öffnen</span>☰</button>
        <div class="hidden min-w-0 items-center gap-2 text-sm text-slate-500 sm:flex"><span>{{ auth()->user()->currentTenant->name }}</span><span aria-hidden="true">›</span><span class="font-medium text-slate-900">Übersicht</span></div>
        <label class="relative mx-auto hidden w-full max-w-lg md:block"><span class="sr-only">Globale Suche</span><input disabled class="cv-input bg-slate-50 pl-10" placeholder="Suche wird mit den Fachmodulen aktiviert"><span class="absolute left-3 top-3 text-slate-400" aria-hidden="true">⌕</span></label>
        <div class="ml-auto flex items-center gap-3"><div class="grid h-9 w-9 place-items-center rounded-full bg-blue-700 text-sm font-bold text-white">{{ strtoupper(substr(auth()->user()->person?->first_name ?? 'B',0,1).substr(auth()->user()->person?->last_name ?? 'N',0,1)) }}</div><div class="hidden sm:block"><p class="text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs text-slate-500">Administrator</p></div><form method="post" action="{{ route('logout') }}">@csrf<button class="text-sm text-slate-500 hover:text-slate-900">Abmelden</button></form></div>
    </header>
    @if(app(App\Support\Tenancy\TenantContext::class)->isSupportMode())
        <div class="bg-amber-100 px-4 py-2 text-center text-sm font-semibold text-amber-950">Supportmodus aktiv – alle Zugriffe werden protokolliert.</div>
    @endif
    <main id="main" class="p-4 sm:p-6 lg:p-7">{{ $slot }}</main>
</div>
</body>
</html>
