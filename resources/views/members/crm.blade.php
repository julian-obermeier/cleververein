<x-layouts.app title="Mitglied · CRM">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliederverwaltung</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $member->person->display_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $member->member_number }} · CRM, Dokumente, Kommunikation und Zusatzdaten</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('members.show', $member) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Stammdaten</a>
            <a href="{{ route('documents.index', ['member_id' => $member->id]) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Dokument erzeugen</a>
            @if($canHistory)<a href="{{ route('members.history', $member) }}" class="cv-button border border-slate-300 bg-white text-slate-700">Änderungsverlauf</a>@endif
            <a href="{{ route('members.edit', $member) }}" class="cv-button-primary">Mitglied bearbeiten</a>
        </div>
    </div>

    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
        <section class="cv-panel p-5 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="text-lg font-bold">Tags & Kennzeichnungen</h2><p class="mt-1 text-sm text-slate-500">Schnelle Klassifizierung für Filter, Segmente und Massenaktionen.</p></div>
                <a href="{{ route('members.segments.index') }}" class="text-sm font-semibold text-blue-700">Segmente verwalten</a>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($member->tags as $tag)
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium">
                        {{ $tag->name }}
                        @if($canTags)<form method="post" action="{{ route('members.crm.tags.destroy', [$member, $tag]) }}">@csrf @method('delete')<button class="text-slate-400 hover:text-red-600" title="Tag entfernen">×</button></form>@endif
                    </span>
                @empty
                    <span class="text-sm text-slate-500">Noch keine Tags zugewiesen.</span>
                @endforelse
            </div>
            @if($canTags)
                <form method="post" action="{{ route('members.crm.tags.store', $member) }}" class="mt-5 flex flex-col gap-3 sm:flex-row">@csrf
                    <select class="cv-input sm:max-w-sm" name="member_tag_id" required><option value="">Tag auswählen …</option>@foreach($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select>
                    <button class="cv-button-primary">Tag zuweisen</button>
                </form>
            @endif
        </section>

        <section class="cv-panel p-5">
            <h2 class="text-lg font-bold">Kontakt kompakt</h2>
            @php($contact = $member->person->contact_data ?? [])
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-slate-500">E-Mail</dt><dd class="font-medium">{{ $member->person->email ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Telefon</dt><dd class="font-medium">{{ $contact['phone'] ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Mobil</dt><dd class="font-medium">{{ $contact['mobile'] ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ ucfirst($member->status) }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="cv-panel mt-5 p-5">
        <div><h2 class="text-lg font-bold">Benutzerdefinierte Felder</h2><p class="mt-1 text-sm text-slate-500">Mandantenspezifische Zusatzinformationen ohne Änderung des festen Datenmodells.</p></div>
        @if($customFields->isEmpty())
            <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">Noch keine Zusatzfelder definiert. <a class="font-semibold text-blue-700" href="{{ route('members.settings') }}">In den Stammdaten anlegen</a>.</div>
        @else
            <form method="post" action="{{ route('members.crm.custom-fields.update', $member) }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf @method('put')
                @foreach($customFields as $field)
                    @php($current = old('custom_fields.'.$field->id, $customValues[$field->id]->value ?? null))
                    <label class="{{ $field->field_type === 'textarea' ? 'md:col-span-2' : '' }}">
                        <span class="cv-label">{{ $field->name }}@if($field->is_required) *@endif</span>
                        @if($field->field_type === 'textarea')
                            <textarea class="cv-input min-h-28 py-3" name="custom_fields[{{ $field->id }}]" @required($field->is_required)>{{ $current }}</textarea>
                        @elseif($field->field_type === 'select')
                            <select class="cv-input" name="custom_fields[{{ $field->id }}]" @required($field->is_required)><option value="">Bitte wählen …</option>@foreach($field->options ?? [] as $option)<option value="{{ $option }}" @selected((string)$current === (string)$option)>{{ $option }}</option>@endforeach</select>
                        @elseif($field->field_type === 'checkbox')
                            <input type="hidden" name="custom_fields[{{ $field->id }}]" value="0">
                            <span class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3"><input type="checkbox" name="custom_fields[{{ $field->id }}]" value="1" @checked((bool)$current) @required($field->is_required)><span class="text-sm">Ja</span></span>
                        @else
                            <input class="cv-input" type="{{ $field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text') }}" name="custom_fields[{{ $field->id }}]" value="{{ $current }}" @required($field->is_required)>
                        @endif
                    </label>
                @endforeach
                @if($canUpdate)<div class="md:col-span-2 flex justify-end"><button class="cv-button-primary">Zusatzfelder speichern</button></div>@endif
            </form>
        @endif
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="cv-panel p-5">
            <div><h2 class="text-lg font-bold">Dokumente</h2><p class="mt-1 text-sm text-slate-500">Privat gespeichert; Download nur nach Berechtigungsprüfung.</p></div>
            @if($canDocuments)
                <form method="post" action="{{ route('members.crm.documents.store', $member) }}" enctype="multipart/form-data" class="mt-5 grid gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">@csrf
                    <div class="grid gap-3 sm:grid-cols-2"><label><span class="cv-label">Titel *</span><input required class="cv-input bg-white" name="title"></label><label><span class="cv-label">Kategorie</span><input class="cv-input bg-white" name="category" placeholder="z. B. Antrag, Vertrag"></label></div>
                    <div class="grid gap-3 sm:grid-cols-2"><label><span class="cv-label">Dokumentdatum</span><input type="date" class="cv-input bg-white" name="document_date"></label><label><span class="cv-label">Datei * (max. 15 MB)</span><input required type="file" class="cv-input bg-white" name="file"></label></div>
                    <label><span class="cv-label">Notiz</span><textarea class="cv-input min-h-20 bg-white py-3" name="notes"></textarea></label>
                    <div class="flex justify-end"><button class="cv-button-primary">Dokument hochladen</button></div>
                </form>
            @endif
            <div class="mt-5 divide-y divide-slate-100 border-t border-slate-200">
                @forelse($member->documents->sortByDesc('created_at') as $document)
                    <div class="flex items-start justify-between gap-4 py-4"><div class="min-w-0"><p class="font-semibold">{{ $document->title }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $document->category ?: 'Ohne Kategorie' }} · {{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} KB</p><p class="mt-1 text-xs text-slate-400">{{ $document->document_date?->format('d.m.Y') ?: $document->created_at->format('d.m.Y H:i') }}</p></div><div class="flex shrink-0 gap-3"><a class="text-sm font-semibold text-blue-700" href="{{ route('members.crm.documents.download', [$member, $document]) }}">Download</a>@if($canDocuments)<form method="post" action="{{ route('members.crm.documents.destroy', [$member, $document]) }}">@csrf @method('delete')<button class="text-sm font-semibold text-red-600">Archivieren</button></form>@endif</div></div>
                @empty
                    <p class="py-5 text-sm text-slate-500">Noch keine Dokumente vorhanden.</p>
                @endforelse
            </div>
        </section>

        <section class="cv-panel p-5">
            <div><h2 class="text-lg font-bold">Kommunikationshistorie</h2><p class="mt-1 text-sm text-slate-500">Telefonate, E-Mails, persönliche Gespräche und weitere Kontakte nachvollziehbar dokumentieren.</p></div>
            @if($canCommunications)
                <form method="post" action="{{ route('members.crm.communications.store', $member) }}" class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">@csrf
                    <div class="grid gap-3 sm:grid-cols-3"><label><span class="cv-label">Kanal *</span><select required class="cv-input bg-white" name="channel"><option value="email">E-Mail</option><option value="phone">Telefon</option><option value="mobile">Mobil</option><option value="post">Post</option><option value="meeting">Gespräch</option><option value="other">Sonstiges</option></select></label><label><span class="cv-label">Richtung *</span><select required class="cv-input bg-white" name="direction"><option value="outbound">Ausgehend</option><option value="inbound">Eingehend</option><option value="internal">Intern</option></select></label><label><span class="cv-label">Zeitpunkt *</span><input required type="datetime-local" class="cv-input bg-white" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label></div>
                    <label><span class="cv-label">Betreff</span><input class="cv-input bg-white" name="subject"></label>
                    <label><span class="cv-label">Inhalt / Gesprächsnotiz *</span><textarea required class="cv-input min-h-28 bg-white py-3" name="body"></textarea></label>
                    <label><span class="cv-label">Ergebnis / Nächster Schritt</span><input class="cv-input bg-white" name="outcome"></label>
                    <div class="flex justify-end"><button class="cv-button-primary">Kontakt dokumentieren</button></div>
                </form>
            @endif
            <div class="mt-5 space-y-3">
                @forelse($member->communications->sortByDesc('occurred_at') as $communication)
                    <article class="rounded-xl border border-slate-200 p-4"><div class="flex items-start justify-between gap-4"><div><p class="font-semibold">{{ $communication->subject ?: ucfirst($communication->channel) }}</p><p class="mt-1 text-xs text-slate-500">{{ $communication->occurred_at->format('d.m.Y H:i') }} · {{ ucfirst($communication->direction) }} · {{ $communication->user?->name ?: 'System' }}</p></div>@if($canCommunications)<form method="post" action="{{ route('members.crm.communications.destroy', [$member, $communication]) }}">@csrf @method('delete')<button class="text-sm font-semibold text-red-600">Entfernen</button></form>@endif</div><p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $communication->body }}</p>@if($communication->outcome)<p class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-900"><strong>Ergebnis:</strong> {{ $communication->outcome }}</p>@endif</article>
                @empty
                    <p class="text-sm text-slate-500">Noch keine Kommunikation dokumentiert.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
