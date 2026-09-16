<x-layouts.app title="Formulare & Workflows">
    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div><p class="text-sm font-semibold text-blue-700">Prozesse & Anträge</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Formulare & Workflows</h1><p class="mt-2 max-w-3xl text-sm text-slate-500">Interne und öffentliche Formulare erstellen, veröffentlichen und mit nachvollziehbaren Bearbeitungs- und Genehmigungsschritten verbinden.</p></div>
        <div class="flex flex-wrap gap-2">@if($canViewSubmissions)<a href="{{ route('forms.submissions.index') }}" class="cv-button border border-slate-300 bg-white">Einreichungen</a>@endif</div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.5fr)_420px]">
        <section class="cv-panel overflow-hidden">
            <div class="border-b border-slate-200 p-5">
                <form method="get" class="grid gap-3 sm:grid-cols-[1fr_180px_auto]">
                    <input class="cv-input" name="q" value="{{ request('q') }}" placeholder="Formular suchen …">
                    <select class="cv-input" name="status"><option value="">Alle Status</option><option value="published" @selected(request('status')==='published')>Veröffentlicht</option><option value="draft" @selected(request('status')==='draft')>Entwurf</option><option value="archived" @selected(request('status')==='archived')>Archiviert</option></select>
                    <button class="cv-button border border-slate-300 bg-white">Filtern</button>
                </form>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($forms as $form)
                    <article class="p-5 hover:bg-slate-50/70">
                        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-start">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-bold"><a href="{{ route('forms.edit', $form) }}" class="hover:text-blue-700">{{ $form->name }}</a></h2><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $form->status==='published' ? 'bg-emerald-100 text-emerald-800' : ($form->status==='archived' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800') }}">{{ ['published'=>'Veröffentlicht','draft'=>'Entwurf','archived'=>'Archiviert'][$form->status] ?? $form->status }}</span><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">{{ $form->form_type === 'public' ? 'Öffentlich' : 'Intern' }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $form->organizationUnit?->name ?: 'Mandantenweit' }} · Version {{ $form->version }} · {{ $form->fields_count }} Felder · {{ $form->submissions_count }} Einreichungen</p>@if($form->description)<p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $form->description }}</p>@endif</div>
                            <div class="flex shrink-0 flex-wrap gap-2"><a class="cv-button border border-slate-300 bg-white" href="{{ route('forms.edit', $form) }}">Bearbeiten</a>@if($canSubmit && $form->status==='published' && $form->form_type==='internal')<a class="cv-button-primary" href="{{ route('forms.fill', $form) }}">Ausfüllen</a>@endif</div>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-sm text-slate-500">Noch keine Formulare vorhanden.</div>
                @endforelse
            </div>
        </section>

        @if($canManage)
            <aside class="cv-panel h-fit p-5"><h2 class="text-lg font-bold">Neues Formular</h2><p class="mt-1 text-sm text-slate-500">Startet als Entwurf und wird erst nach Prüfung veröffentlicht.</p>
                <form method="post" action="{{ route('forms.store') }}" class="mt-5 space-y-4">@csrf
                    <div><label class="cv-label">Name</label><input class="cv-input" name="name" required maxlength="180" placeholder="z. B. Mitgliedsantrag"></div>
                    <div><label class="cv-label">Gliederung</label><select class="cv-input" name="organization_unit_id"><option value="">Mandantenweit</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div>
                    <div><label class="cv-label">Typ</label><select class="cv-input" name="form_type"><option value="internal">Intern – nur angemeldete Benutzer</option><option value="public">Öffentlich – über sicheren Link</option></select></div>
                    <div><label class="cv-label">Referenz-Präfix</label><input class="cv-input" name="submission_prefix" value="FM" maxlength="12"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_anonymous" value="1"><span>Öffentlich anonyme Einreichungen erlauben</span></label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_member" value="1"><span>Intern Mitgliedsbezug verpflichtend</span></label>
                    <div><label class="cv-label">Beschreibung</label><textarea class="cv-input min-h-24" name="description"></textarea></div>
                    <button class="cv-button-primary w-full">Formular anlegen</button>
                </form>
            </aside>
        @endif
    </div>
</x-layouts.app>
