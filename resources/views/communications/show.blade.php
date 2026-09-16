<x-layouts.app title="Kampagne">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <a href="{{ route('communications.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Kommunikationszentrale</a>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $campaign->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $campaign->subject }}</p>
        </div>
        <span class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $campaign->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($campaign->status === 'draft' ? 'bg-slate-100 text-slate-700' : 'bg-blue-100 text-blue-800') }}">{{ ['draft'=>'Entwurf','prepared'=>'Vorbereitet','sending'=>'Versand läuft','completed'=>'Abgeschlossen'][$campaign->status] ?? $campaign->status }}</span>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Empfänger</p><p class="mt-2 text-3xl font-bold">{{ $campaign->recipient_count }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Erfolgreich</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ $campaign->sent_count }}</p></div>
        <div class="cv-panel p-5"><p class="text-sm text-slate-500">Fehlgeschlagen</p><p class="mt-2 text-3xl font-bold {{ $campaign->failed_count ? 'text-red-700' : '' }}">{{ $campaign->failed_count }}</p></div>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,.7fr)]">
        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-bold">Empfängersnapshot</h2><p class="mt-1 text-sm text-slate-500">Nach Vorbereitung bleibt diese Liste unverändert, auch wenn sich Mitgliedsdaten später ändern.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Empfänger</th><th class="px-5 py-3">E-Mail</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Versuche</th><th class="px-5 py-3">Fehler</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($campaign->recipients as $recipient)
                    <tr><td class="px-5 py-4 font-semibold">{{ $recipient->recipient_name }}</td><td class="px-5 py-4 text-slate-600">{{ $recipient->recipient_email }}</td><td class="px-5 py-4">{{ ['pending'=>'offen','sent'=>'gesendet','failed'=>'fehlgeschlagen'][$recipient->status] ?? $recipient->status }}</td><td class="px-5 py-4">{{ $recipient->attempts }}</td><td class="max-w-xs px-5 py-4 text-xs text-red-700">{{ $recipient->error_message ?: '–' }}</td></tr>
                @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Noch kein Empfängersnapshot erstellt.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <aside class="space-y-5">
            <section class="cv-panel p-5">
                <h2 class="font-bold">Versandsteuerung</h2>
                <p class="mt-2 text-sm text-slate-500">Der Versand erfolgt synchron in kleinen Batches und benötigt keinen Queue-Worker.</p>
                @if($campaign->status === 'draft' && $canManage)
                    <form method="post" action="{{ route('communications.campaigns.prepare', $campaign) }}" class="mt-4">@csrf<button class="cv-button-primary w-full">Empfänger einfrieren</button></form>
                @elseif(in_array($campaign->status, ['prepared','sending']) && $canSend)
                    <form method="post" action="{{ route('communications.campaigns.send', $campaign) }}" class="mt-4 space-y-3">@csrf<label class="block"><span class="text-xs font-semibold">Batchgröße</span><select class="cv-input mt-1" name="limit"><option value="20">20</option><option value="50" selected>50</option><option value="100">100</option></select></label><button class="cv-button-primary w-full">Nächsten Batch senden</button></form>
                @elseif($campaign->status === 'completed')
                    <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">Versand abgeschlossen.</div>
                @endif
            </section>

            <section class="cv-panel p-5">
                <h2 class="font-bold">Kampagnendaten</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Zielgruppe</dt><dd class="text-right font-semibold">{{ ['all_active'=>'Alle aktiven','segment'=>'Segment','organization'=>'Gliederung','event_registrations'=>'Veranstaltungseinladungen'][$campaign->target_type] ?? $campaign->target_type }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Veranstaltung</dt><dd class="text-right font-semibold">{{ $campaign->event?->title ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Segment</dt><dd class="text-right font-semibold">{{ $campaign->segment?->name ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Gliederung</dt><dd class="text-right font-semibold">{{ $campaign->organizationUnit?->name ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Vorbereitet</dt><dd class="font-semibold">{{ $campaign->prepared_at?->format('d.m.Y H:i') ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Abgeschlossen</dt><dd class="font-semibold">{{ $campaign->completed_at?->format('d.m.Y H:i') ?: '–' }}</dd></div>
                </dl>
            </section>

            <section class="cv-panel p-5"><h2 class="font-bold">Nachricht</h2><p class="mt-3 text-sm font-semibold">{{ $campaign->subject }}</p><pre class="mt-3 whitespace-pre-wrap font-sans text-sm text-slate-600">{{ $campaign->body }}</pre></section>
        </aside>
    </div>
</x-layouts.app>
