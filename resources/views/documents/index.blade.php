<x-layouts.app title="Dokumente">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Dokumenten-Generator</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Vorlagen & erzeugte Dokumente</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">Erstellen Sie wiederverwendbare Vorlagen, füllen Sie Mitgliedsdaten automatisch über Platzhalter ein und erzeugen Sie revisionssicher abgelegte PDFs.</p>
        </div>
        <div class="flex gap-2">
            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700">{{ $templates->count() }} Vorlage(n)</span>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ $documents->count() }} letzte PDF(s)</span>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($canManage)
        <section class="cv-panel mt-6 p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-lg font-bold">Neue Vorlage</h2><p class="text-sm text-slate-500">Nach dem Anlegen öffnet sich direkt der visuelle Editor.</p></div>
            </div>
            <form method="post" action="{{ route('documents.templates.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-6">@csrf
                <label class="xl:col-span-2"><span class="cv-label">Name *</span><input required class="cv-input" name="name" value="{{ old('name') }}" placeholder="z. B. Mitgliedsbescheinigung"></label>
                <label><span class="cv-label">Kategorie</span><input class="cv-input" name="category" value="{{ old('category') }}" placeholder="Bescheinigung"></label>
                <label><span class="cv-label">Format</span><select class="cv-input" name="page_size"><option>A4</option><option>A5</option><option>Letter</option></select></label>
                <label><span class="cv-label">Ausrichtung</span><select class="cv-input" name="orientation"><option value="portrait">Hochformat</option><option value="landscape">Querformat</option></select></label>
                <div class="flex items-end"><button class="cv-button-primary w-full">Vorlage anlegen</button></div>
                <label class="md:col-span-2 xl:col-span-6"><span class="cv-label">Beschreibung</span><textarea class="cv-input min-h-20" name="description" placeholder="Interne Beschreibung und Einsatzzweck">{{ old('description') }}</textarea></label>
            </form>
        </section>
    @endif

    <section class="mt-6">
        <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-bold">Vorlagen</h2><span class="text-sm text-slate-500">Aktive Vorlagen können unmittelbar verwendet werden.</span></div>
        <div class="grid gap-4 xl:grid-cols-2">
            @forelse($templates as $template)
                <article class="cv-panel p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-lg font-bold">{{ $template->name }}</h3><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $template->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $template->is_active ? 'Aktiv' : 'Deaktiviert' }}</span></div>
                            <p class="mt-1 text-sm text-slate-500">{{ $template->category ?: 'Ohne Kategorie' }} · {{ $template->page_size }} · {{ $template->orientation === 'landscape' ? 'Querformat' : 'Hochformat' }}</p>
                            @if($template->description)<p class="mt-3 text-sm text-slate-600">{{ $template->description }}</p>@endif
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2 text-right text-xs text-slate-500"><div>{{ count($template->layout['blocks'] ?? []) }} Element(e)</div><div>{{ $template->updated_at->format('d.m.Y H:i') }}</div></div>
                    </div>

                    @if($canGenerate)
                        <form method="post" action="{{ route('documents.templates.generate', $template) }}" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">@csrf
                            <label><span class="cv-label">Mitglied</span><select class="cv-input" name="member_id"><option value="">Ohne Mitgliedsdaten</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) request('member_id') === (string) $member->id)>{{ $member->member_number }} · {{ $member->person->display_name }}</option>@endforeach</select></label>
                            <label><span class="cv-label">Dokumenttitel</span><input class="cv-input" name="title" placeholder="optional"></label>
                            <div class="flex items-end"><button class="cv-button-primary w-full">PDF erzeugen</button></div>
                        </form>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if($canManage)<a href="{{ route('documents.templates.edit', $template) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Editor öffnen</a>@endif
                        @if($canGenerate)<a target="_blank" href="{{ route('documents.templates.preview', [$template, 'member_id' => request('member_id')]) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Vorschau</a>@endif
                        @if($canManage)
                            <form method="post" action="{{ route('documents.templates.duplicate', $template) }}">@csrf<button class="cv-button border border-slate-300 bg-white text-slate-700">Duplizieren</button></form>
                            <form method="post" action="{{ route('documents.templates.toggle', $template) }}">@csrf @method('patch')<button class="cv-button border border-slate-300 bg-white text-slate-700">{{ $template->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="cv-panel p-8 text-center text-sm text-slate-500 xl:col-span-2">Noch keine Dokumentvorlage angelegt.</div>
            @endforelse
        </div>
    </section>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Erzeugte Dokumente</h2><p class="text-sm text-slate-500">Die letzten 50 PDFs werden privat gespeichert und sind nur nach Berechtigungsprüfung abrufbar.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Dokument</th><th class="px-5 py-3">Vorlage</th><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Erzeugt</th><th class="px-5 py-3">Benutzer</th><th class="px-5 py-3 text-right"></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($documents as $document)<tr><td class="px-5 py-4"><p class="font-semibold">{{ $document->title }}</p><p class="text-xs text-slate-500">{{ number_format($document->size / 1024, 1, ',', '.') }} KB</p></td><td class="px-5 py-4">{{ $document->template?->name ?? 'Gelöschte Vorlage' }}</td><td class="px-5 py-4">{{ $document->member?->person?->display_name ?? '–' }}</td><td class="px-5 py-4">{{ $document->generated_at?->format('d.m.Y H:i') }}</td><td class="px-5 py-4">{{ $document->generator?->name ?? 'System' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('documents.generated.download', $document) }}" class="font-semibold text-blue-700 hover:underline">Herunterladen</a></td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Noch keine PDFs erzeugt.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
