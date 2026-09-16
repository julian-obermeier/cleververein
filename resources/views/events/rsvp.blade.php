<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Teilnahme · {{ $registration->event->title }} · cleververein</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-10">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6"><p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Einladung</p><h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $registration->event->title }}</h1><p class="mt-2 text-slate-500">Hallo {{ $registration->member?->person?->display_name ?: $registration->guest_name }}, bitte gib uns deine Rückmeldung.</p></div>

            @if(session('success'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <dl class="grid gap-4 rounded-xl bg-slate-50 p-5 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Termin</dt><dd class="mt-1 font-semibold">{{ $registration->event->starts_at->format('d.m.Y H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ort</dt><dd class="mt-1 font-semibold">{{ $registration->event->location ?: 'Noch nicht festgelegt' }}</dd></div>
                @if($registration->event->registration_deadline)<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rückmeldung bis</dt><dd class="mt-1 font-semibold">{{ $registration->event->registration_deadline->format('d.m.Y H:i') }}</dd></div>@endif
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aktueller Status</dt><dd class="mt-1 font-semibold">{{ ['invited'=>'Noch offen','registered'=>'Zugesagt','waitlisted'=>'Warteliste','declined'=>'Abgesagt'][$registration->status] ?? $registration->status }}</dd></div>
            </dl>

            @if($registration->event->description)<div class="mt-6 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $registration->event->description }}</div>@endif

            <div class="mt-7 grid gap-3 sm:grid-cols-2">
                <form method="post" action="{{ route('events.rsvp.update', $registration->response_token) }}">@csrf<input type="hidden" name="response" value="registered"><button class="cv-button-primary w-full py-3">Ich nehme teil</button></form>
                <form method="post" action="{{ route('events.rsvp.update', $registration->response_token) }}">@csrf<input type="hidden" name="response" value="declined"><button class="cv-button w-full border border-slate-300 bg-white py-3 text-slate-700">Ich kann nicht teilnehmen</button></form>
            </div>
            @if($registration->status === 'waitlisted')<p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Die Veranstaltung ist derzeit voll. Deine Zusage wurde auf die Warteliste gesetzt; bei einem frei werdenden Platz wirst du automatisch nachgezogen.</p>@endif

            <p class="mt-8 text-center text-xs text-slate-400">Dieser persönliche Link dient ausschließlich deiner Teilnahme-Rückmeldung.</p>
        </section>
    </main>
</body>
</html>
