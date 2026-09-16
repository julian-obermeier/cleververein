<x-layouts.app :title="$form->name">
    @php
        $fieldTypeLabels = [
            'text' => 'Text',
            'textarea' => 'Mehrzeiliger Text',
            'email' => 'E-Mail',
            'number' => 'Zahl',
            'date' => 'Datum',
            'select' => 'Auswahlliste',
            'radio' => 'Einzelauswahl',
            'checkbox' => 'Checkbox',
            'multiselect' => 'Mehrfachauswahl',
            'file' => 'Datei-Upload',
            'heading' => 'Überschrift',
            'info' => 'Hinweis',
        ];
        $statusLabels = [
            'draft' => 'Entwurf',
            'published' => 'Veröffentlicht',
            'archived' => 'Archiviert',
        ];
        $editable = $canManage && $form->status !== 'archived';
        $workflowEditable = $canWorkflow && $form->status !== 'archived';
    @endphp

    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
            <a href="{{ route('forms.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Formulare</a>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <h1 class="text-3xl font-bold tracking-tight">{{ $form->name }}</h1>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $form->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($form->status === 'archived' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800') }}">
                    {{ $statusLabels[$form->status] ?? $form->status }}
                </span>
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">Version {{ $form->version }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">
                {{ $form->form_type === 'public' ? 'Öffentliches Formular' : 'Internes Formular' }} · {{ $form->organizationUnit?->name ?: 'Mandantenweit' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($canSubmit && $form->status === 'published' && $form->form_type === 'internal')
                <a class="cv-button border border-slate-300 bg-white" href="{{ route('forms.fill', $form) }}">Formular ausfüllen</a>
            @endif

            @if($form->status === 'published' && $form->form_type === 'public' && $form->public_token)
                <a class="cv-button border border-slate-300 bg-white" href="{{ route('forms.public.show', $form->public_token) }}" target="_blank" rel="noopener">Öffentlichen Link öffnen ↗</a>
            @endif

            @if($editable)
                <form method="post" action="{{ route('forms.publish', $form) }}">
                    @csrf
                    <button class="cv-button-primary">{{ $form->status === 'published' ? 'Neu veröffentlichen' : 'Veröffentlichen' }}</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($form->status === 'archived')
        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            Dieses Formular ist archiviert und schreibgeschützt. Bestehende Einreichungen bleiben weiterhin im Formular-Eingang verfügbar.
        </div>
    @endif

    @if($canManage)
        <section class="cv-panel mt-6 p-5">
            <div>
                <h2 class="text-lg font-bold">Formulareinstellungen</h2>
                <p class="mt-1 text-sm text-slate-500">Änderungen an einem veröffentlichten Formular setzen es zurück auf Entwurf. Bestehende Einreichungen bleiben unverändert.</p>
            </div>

            <form method="post" action="{{ route('forms.update', $form) }}" class="mt-5 grid gap-4 lg:grid-cols-3">
                @csrf
                @method('PUT')

                <div><label class="cv-label">Name</label><input class="cv-input" name="name" value="{{ $form->name }}" required @disabled(!$editable)></div>
                <div><label class="cv-label">Slug</label><input class="cv-input" name="slug" value="{{ $form->slug }}" required @disabled(!$editable)></div>
                <div><label class="cv-label">Referenz-Präfix</label><input class="cv-input" name="submission_prefix" value="{{ $form->submission_prefix }}" maxlength="12" required @disabled(!$editable)></div>

                <div>
                    <label class="cv-label">Gliederung</label>
                    <select class="cv-input" name="organization_unit_id" @disabled(!$editable)>
                        <option value="">Mandantenweit</option>
                        @foreach($organizations as $organization)
                            <option value="{{ $organization->id }}" @selected($form->organization_unit_id === $organization->id)>{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="cv-label">Typ</label>
                    <select class="cv-input" name="form_type" @disabled(!$editable)>
                        <option value="internal" @selected($form->form_type === 'internal')>Intern</option>
                        <option value="public" @selected($form->form_type === 'public')>Öffentlich</option>
                    </select>
                </div>

                <div class="flex flex-col justify-end gap-2 pb-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_anonymous" value="1" @checked($form->allow_anonymous) @disabled(!$editable)><span>Anonyme öffentliche Einreichung</span></label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_member" value="1" @checked($form->require_member) @disabled(!$editable)><span>Mitglied intern verpflichtend</span></label>
                </div>

                <div class="lg:col-span-3"><label class="cv-label">Beschreibung</label><textarea class="cv-input min-h-24" name="description" @disabled(!$editable)>{{ $form->description }}</textarea></div>
                <div class="lg:col-span-3"><label class="cv-label">Erfolgsmeldung</label><textarea class="cv-input min-h-20" name="success_message" placeholder="Vielen Dank. Ihre Angaben wurden erfolgreich übermittelt." @disabled(!$editable)>{{ $form->success_message }}</textarea></div>

                @if($editable)
                    <div class="lg:col-span-3 text-right"><button class="cv-button-primary">Einstellungen speichern</button></div>
                @endif
            </form>
        </section>
    @endif

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.5fr)_430px]">
        <section class="cv-panel overflow-hidden">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-bold">Formularaufbau</h2>
                    <p class="mt-1 text-sm text-slate-500">Felder per Drag & Drop sortieren. Bedingungen werden beim Ausfüllen dynamisch und serverseitig ausgewertet.</p>
                </div>

                @if($editable && $form->fields->isNotEmpty())
                    <form method="post" action="{{ route('forms.fields.reorder', $form) }}" data-order-form="fields">
                        @csrf
                        <div data-order-inputs>
                            @foreach($form->fields as $field)
                                <input type="hidden" name="field_ids[]" value="{{ $field->id }}">
                            @endforeach
                        </div>
                        <button class="cv-button border border-slate-300 bg-white">Reihenfolge speichern</button>
                    </form>
                @endif
            </div>

            <div class="divide-y divide-slate-100" data-sort-list="fields">
                @forelse($form->fields as $field)
                    <article class="group bg-white p-5" draggable="{{ $editable ? 'true' : 'false' }}" data-sort-item data-id="{{ $field->id }}">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 select-none text-slate-300 group-hover:text-slate-500" title="Ziehen">⋮⋮</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold">{{ $field->label }}</h3>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $fieldTypeLabels[$field->field_type] ?? $field->field_type }}</span>
                                    @if($field->is_required)
                                        <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Pflicht</span>
                                    @endif
                                    @if($field->condition)
                                        <span class="rounded bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700">Bedingt</span>
                                    @endif
                                </div>
                                <p class="mt-1 font-mono text-xs text-slate-400">{{ $field->field_key }}</p>
                                @if($field->help_text)
                                    <p class="mt-2 text-sm text-slate-600">{{ $field->help_text }}</p>
                                @endif

                                @if($editable)
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-blue-700">Feld bearbeiten</summary>
                                        <form method="post" action="{{ route('forms.fields.update', [$form, $field]) }}" class="mt-3 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                                            @csrf
                                            @method('PUT')
                                            <div><label class="cv-label">Bezeichnung</label><input class="cv-input" name="label" value="{{ $field->label }}" required></div>
                                            <div><label class="cv-label">Feldschlüssel</label><input class="cv-input" name="field_key" value="{{ $field->field_key }}" required></div>
                                            <div><label class="cv-label">Typ</label><select class="cv-input" name="field_type">@foreach($fieldTypeLabels as $type => $label)<option value="{{ $type }}" @selected($field->field_type === $type)>{{ $label }}</option>@endforeach</select></div>
                                            <div><label class="cv-label">Platzhalter / Checkbox-Text</label><input class="cv-input" name="placeholder" value="{{ $field->placeholder }}"></div>
                                            <div class="sm:col-span-2"><label class="cv-label">Hilfetext</label><textarea class="cv-input" name="help_text">{{ $field->help_text }}</textarea></div>
                                            <div class="sm:col-span-2"><label class="cv-label">Optionen – eine pro Zeile</label><textarea class="cv-input min-h-24" name="options_text">{{ implode("\n", collect($field->options ?? [])->map(fn ($option) => is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option)->all()) }}</textarea></div>
                                            <div><label class="cv-label">Bedingung: Feld</label><select class="cv-input" name="condition_field_key"><option value="">Immer anzeigen</option>@foreach($form->fields->where('id', '!=', $field->id) as $other)<option value="{{ $other->field_key }}" @selected(($field->condition['field_key'] ?? null) === $other->field_key)>{{ $other->label }}</option>@endforeach</select></div>
                                            <div><label class="cv-label">Operator</label><select class="cv-input" name="condition_operator">@foreach(['equals' => 'ist gleich', 'not_equals' => 'ist nicht gleich', 'contains' => 'enthält', 'not_contains' => 'enthält nicht', 'filled' => 'ist ausgefüllt', 'empty' => 'ist leer'] as $operator => $label)<option value="{{ $operator }}" @selected(($field->condition['operator'] ?? 'equals') === $operator)>{{ $label }}</option>@endforeach</select></div>
                                            <div><label class="cv-label">Bedingungswert</label><input class="cv-input" name="condition_value" value="{{ $field->condition['value'] ?? '' }}"></div>
                                            <div><label class="cv-label">Datenbindung</label><select class="cv-input" name="binding"><option value="">Keine</option><option value="submitter_name" @selected(($field->settings['binding'] ?? null) === 'submitter_name')>Einreicher-Name</option><option value="submitter_email" @selected(($field->settings['binding'] ?? null) === 'submitter_email')>Einreicher-E-Mail</option></select></div>
                                            <div><label class="cv-label">Max. Zeichen</label><input type="number" class="cv-input" name="max_length" value="{{ $field->validation['max_length'] ?? '' }}"></div>
                                            <div><label class="cv-label">Upload max. KB</label><input type="number" class="cv-input" name="max_kb" value="{{ $field->validation['max_kb'] ?? '' }}"></div>
                                            <div class="sm:col-span-2"><label class="cv-label">Upload-Endungen, kommasepariert</label><input class="cv-input" name="extensions" value="{{ implode(',', $field->validation['extensions'] ?? []) }}"></div>
                                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" @checked($field->is_required)><span>Pflichtfeld</span></label>
                                            <div class="flex justify-end gap-2"><button class="cv-button-primary">Speichern</button></div>
                                        </form>
                                        <form method="post" action="{{ route('forms.fields.destroy', [$form, $field]) }}" class="mt-2 text-right" onsubmit="return confirm('Feld wirklich aus dem Entwurf entfernen?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-sm font-semibold text-red-700 hover:underline">Feld entfernen</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-sm text-slate-500">Noch keine Felder. Rechts kannst du den Formularaufbau beginnen.</div>
                @endforelse
            </div>
        </section>

        @if($editable)
            <aside class="cv-panel h-fit p-5">
                <h2 class="text-lg font-bold">Feld hinzufügen</h2>
                <form method="post" action="{{ route('forms.fields.store', $form) }}" class="mt-4 space-y-3">
                    @csrf
                    <div><label class="cv-label">Bezeichnung</label><input class="cv-input" name="label" required placeholder="z. B. E-Mail-Adresse"></div>
                    <div><label class="cv-label">Feldtyp</label><select class="cv-input" name="field_type">@foreach($fieldTypeLabels as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label class="cv-label">Feldschlüssel – optional</label><input class="cv-input" name="field_key" placeholder="wird automatisch erzeugt"></div>
                    <div><label class="cv-label">Platzhalter / Checkbox-Text</label><input class="cv-input" name="placeholder"></div>
                    <div><label class="cv-label">Hilfetext</label><textarea class="cv-input" name="help_text"></textarea></div>
                    <div><label class="cv-label">Optionen – eine pro Zeile</label><textarea class="cv-input min-h-24" name="options_text"></textarea></div>
                    <div><label class="cv-label">Nur anzeigen wenn Feld …</label><select class="cv-input" name="condition_field_key"><option value="">Immer anzeigen</option>@foreach($form->fields as $existing)<option value="{{ $existing->field_key }}">{{ $existing->label }}</option>@endforeach</select></div>
                    <div class="grid grid-cols-2 gap-2"><select class="cv-input" name="condition_operator"><option value="equals">ist gleich</option><option value="not_equals">ist nicht gleich</option><option value="contains">enthält</option><option value="not_contains">enthält nicht</option><option value="filled">ist ausgefüllt</option><option value="empty">ist leer</option></select><input class="cv-input" name="condition_value" placeholder="Wert"></div>
                    <div><label class="cv-label">Datenbindung</label><select class="cv-input" name="binding"><option value="">Keine</option><option value="submitter_name">Einreicher-Name</option><option value="submitter_email">Einreicher-E-Mail</option></select></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1"><span>Pflichtfeld</span></label>
                    <button class="cv-button-primary w-full">Feld hinzufügen</button>
                </form>
            </aside>
        @endif
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-bold">Workflows</h2>
            <p class="mt-1 text-sm text-slate-500">Für neue Einreichungen wird genau der aktive Workflow verwendet. Alte Vorgänge behalten ihre bereits erzeugten Bearbeitungsschritte.</p>
        </div>

        <div class="grid gap-5 p-5 xl:grid-cols-2">
            <div class="space-y-4">
                @forelse($form->workflows as $workflow)
                    <article class="rounded-xl border {{ $workflow->is_active ? 'border-emerald-300 bg-emerald-50/30' : 'border-slate-200' }} p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-bold">{{ $workflow->name }}</h3>
                                <p class="text-xs text-slate-500">{{ $workflow->steps->count() }} Schritte · {{ $workflow->is_active ? 'Aktiv für neue Einreichungen' : 'Inaktiv' }}</p>
                            </div>
                            @if($workflowEditable && !$workflow->is_active)
                                <form method="post" action="{{ route('forms.workflows.activate', [$form, $workflow]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="text-sm font-semibold text-blue-700 hover:underline">Aktivieren</button>
                                </form>
                            @endif
                        </div>

                        @if($workflow->steps->isNotEmpty())
                            @if($workflowEditable)
                                <form method="post" action="{{ route('forms.workflow-steps.reorder', [$form, $workflow]) }}" data-order-form="workflow-{{ $workflow->id }}" class="mt-3">
                                    @csrf
                                    <div data-order-inputs>
                                        @foreach($workflow->steps as $step)
                                            <input type="hidden" name="step_ids[]" value="{{ $step->id }}">
                                        @endforeach
                                    </div>
                                    <button class="text-xs font-semibold text-slate-600 hover:underline">Drag-&-Drop-Reihenfolge speichern</button>
                                </form>
                            @endif

                            <div class="mt-3 space-y-2" data-sort-list="workflow-{{ $workflow->id }}">
                                @foreach($workflow->steps as $step)
                                    <div class="rounded-lg border border-slate-200 bg-white p-3" draggable="{{ $workflowEditable ? 'true' : 'false' }}" data-sort-item data-id="{{ $step->id }}">
                                        <div class="flex gap-2">
                                            <span class="select-none text-slate-300">⋮⋮</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-semibold">{{ $step->position / 10 }}. {{ $step->name }}</p>
                                                <p class="text-xs text-slate-500">
                                                    {{ ['review' => 'Prüfung', 'approval' => 'Genehmigung', 'task' => 'Aufgabe'][$step->step_type] ?? $step->step_type }} ·
                                                    @if($step->assignedUser)
                                                        {{ $step->assignedUser->name }}
                                                    @elseif($step->assignedRole)
                                                        Rolle: {{ $step->assignedRole->name }}
                                                    @else
                                                        Alle berechtigten Bearbeiter
                                                    @endif
                                                    @if($step->due_days)
                                                        · Frist {{ $step->due_days }} Tage
                                                    @endif
                                                </p>

                                                @if($workflowEditable)
                                                    <details class="mt-2">
                                                        <summary class="cursor-pointer text-xs font-semibold text-blue-700">Bearbeiten</summary>
                                                        <form method="post" action="{{ route('forms.workflow-steps.update', [$form, $workflow, $step]) }}" class="mt-2 grid gap-2">
                                                            @csrf
                                                            @method('PUT')
                                                            <input class="cv-input" name="name" value="{{ $step->name }}" required>
                                                            <select class="cv-input" name="step_type"><option value="review" @selected($step->step_type === 'review')>Prüfung</option><option value="approval" @selected($step->step_type === 'approval')>Genehmigung</option><option value="task" @selected($step->step_type === 'task')>Aufgabe</option></select>
                                                            <select class="cv-input" name="assigned_user_id"><option value="">Kein direkter Benutzer</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($step->assigned_user_id === $user->id)>{{ $user->name }}</option>@endforeach</select>
                                                            <select class="cv-input" name="assigned_role_id"><option value="">Keine Rolle</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected($step->assigned_role_id === $role->id)>{{ $role->name }}</option>@endforeach</select>
                                                            <input class="cv-input" type="number" min="1" name="due_days" value="{{ $step->due_days }}" placeholder="Frist in Tagen">
                                                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="decision_required" value="1" @checked($step->decision_required)><span>Genehmigungsentscheidung erforderlich</span></label>
                                                            <button class="cv-button-primary">Speichern</button>
                                                        </form>
                                                        <form method="post" action="{{ route('forms.workflow-steps.destroy', [$form, $workflow, $step]) }}" class="mt-2 text-right" onsubmit="return confirm('Schritt entfernen?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="text-xs font-semibold text-red-700">Entfernen</button>
                                                        </form>
                                                    </details>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($workflowEditable)
                            <form method="post" action="{{ route('forms.workflow-steps.store', [$form, $workflow]) }}" class="mt-4 grid gap-2 border-t border-slate-200 pt-4">
                                @csrf
                                <input class="cv-input" name="name" required placeholder="Neuer Schritt, z. B. Prüfung Geschäftsstelle">
                                <select class="cv-input" name="step_type"><option value="review">Prüfung</option><option value="approval">Genehmigung</option><option value="task">Aufgabe</option></select>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <select class="cv-input" name="assigned_user_id"><option value="">Benutzer – optional</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                                    <select class="cv-input" name="assigned_role_id"><option value="">Rolle – optional</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
                                </div>
                                <input class="cv-input" type="number" min="1" name="due_days" placeholder="Frist in Tagen – optional">
                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="decision_required" value="1" checked><span>Entscheidung erforderlich</span></label>
                                <button class="cv-button border border-slate-300 bg-white">Schritt hinzufügen</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Noch kein Workflow vorhanden. Einreichungen landen ohne Workflow im Status „Eingereicht“ und können manuell abgeschlossen werden.</p>
                @endforelse
            </div>

            @if($workflowEditable)
                <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="font-bold">Neuen Workflow anlegen</h3>
                    <form method="post" action="{{ route('forms.workflows.store', $form) }}" class="mt-3 space-y-3">
                        @csrf
                        <input class="cv-input" name="name" required placeholder="z. B. Mitgliedsantrag Freigabe">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked><span>Sofort für neue Einreichungen aktivieren</span></label>
                        <button class="cv-button-primary w-full">Workflow anlegen</button>
                    </form>
                </aside>
            @endif
        </div>
    </section>

    @if($editable)
        <div class="mt-6 text-right">
            <form method="post" action="{{ route('forms.archive', $form) }}" onsubmit="return confirm('Formular archivieren? Öffentliche Links werden dadurch deaktiviert.')">
                @csrf
                @method('PATCH')
                <button class="text-sm font-semibold text-red-700 hover:underline">Formular archivieren</button>
            </form>
        </div>
    @endif

    <script>
    (() => {
        document.querySelectorAll('[data-sort-list]').forEach(list => {
            let dragging = null;
            list.addEventListener('dragstart', event => {
                dragging = event.target.closest('[data-sort-item]');
                if (!dragging) return;
                dragging.classList.add('opacity-50');
                event.dataTransfer.effectAllowed = 'move';
            });
            list.addEventListener('dragover', event => {
                event.preventDefault();
                if (!dragging) return;
                const target = event.target.closest('[data-sort-item]');
                if (!target || target === dragging || target.parentElement !== list) return;
                const box = target.getBoundingClientRect();
                list.insertBefore(dragging, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
            });
            list.addEventListener('dragend', () => {
                if (!dragging) return;
                dragging.classList.remove('opacity-50');
                dragging = null;
                const key = list.dataset.sortList;
                const form = document.querySelector(`[data-order-form="${CSS.escape(key)}"]`);
                const container = form?.querySelector('[data-order-inputs]');
                if (!container) return;
                container.innerHTML = '';
                list.querySelectorAll('[data-sort-item]').forEach(item => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key === 'fields' ? 'field_ids[]' : 'step_ids[]';
                    input.value = item.dataset.id;
                    container.appendChild(input);
                });
            });
        });
    })();
    </script>
</x-layouts.app>
