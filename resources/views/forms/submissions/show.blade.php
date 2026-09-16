<x-layouts.app :title="$submission->reference_number">
    @php
        $statusLabels = ['submitted'=>'Eingereicht','in_review'=>'In Bearbeitung','approved'=>'Genehmigt','rejected'=>'Abgelehnt','completed'=>'Abgeschlossen'];
        $stepLabels = ['pending'=>'Ausstehend','active'=>'Aktiv','approved'=>'Genehmigt','rejected'=>'Abgelehnt','completed'=>'Erledigt','skipped'=>'Übersprungen'];
        $snapshotFields = collect($submission->metadata['field_snapshot'] ?? []);
        $answersByKey = $submission->answers->keyBy('field_key');
        $attachmentsByKey = $submission->attachments->groupBy('field_key');
    @endphp
    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div><a href="{{ route('forms.submissions.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Einreichungen</a><div class="mt-2 flex flex-wrap items-center gap-2"><h1 class="text-3xl font-bold tracking-tight">{{ $submission->reference_number }}</h1><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $statusLabels[$submission->status] ?? $submission->status }}</span></div><p class="mt-2 text-sm text-slate-500">{{ $submission->metadata['form_name'] ?? $submission->form?->name }} · Formularversion {{ $submission->metadata['form_version'] ?? '?' }} · Eingang {{ $submission->submitted_at?->format('d.m.Y H:i') }}</p></div>
        <a href="{{ route('forms.edit', $submission->form) }}" class="cv-button border border-slate-300 bg-white">Formular öffnen</a>
    </div>
    @if(session('success'))<div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-6 grid gap-5 2xl:grid-cols-[minmax(0,1.45fr)_440px]">
        <div class="space-y-5">
            <section class="cv-panel p-5"><h2 class="text-lg font-bold">Einreicher</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Name / Mitglied</p><p class="mt-1 font-semibold">{{ $submission->member?->person?->display_name ?: ($submission->submitter_name ?: 'Anonym') }}</p>@if($submission->member)<p class="text-xs text-slate-500">{{ $submission->member->member_number }}</p>@endif</div><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">E-Mail</p><p class="mt-1">{{ $submission->submitter_email ?: $submission->member?->person?->email ?: '–' }}</p></div></div></section>

            <section class="cv-panel overflow-hidden"><div class="border-b border-slate-200 p-5"><h2 class="text-lg font-bold">Antworten & Anlagen</h2><p class="mt-1 text-sm text-slate-500">Die Bezeichnungen stammen aus dem bei Einreichung gespeicherten Formular-Snapshot.</p></div><div class="divide-y divide-slate-100">
                @forelse($snapshotFields as $field)
                    @php($answer = $answersByKey->get($field['key'] ?? ''))
                    @php($files = $attachmentsByKey->get($field['key'] ?? '', collect()))
                    @if(!in_array($field['type'] ?? '', ['heading','info'], true) && ($answer || $files->isNotEmpty()))
                        <div class="p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $field['label'] ?? $field['key'] }}</p>
                            @if($answer)<div class="mt-2 whitespace-pre-wrap text-sm text-slate-800">@if($answer->value_json){{ implode(', ', $answer->value_json) }}@elseif(($field['type']??null)==='checkbox'){{ $answer->value_text === '1' ? 'Ja' : 'Nein' }}@else{{ $answer->value_text ?: '–' }}@endif</div>@endif
                            @if($files->isNotEmpty())<div class="mt-2 flex flex-wrap gap-2">@foreach($files as $file)<a class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-white" href="{{ route('forms.attachments.download', [$submission,$file]) }}">{{ $file->original_name }} <span class="font-normal text-slate-400">({{ number_format($file->size/1024, 0, ',', '.') }} KB)</span></a>@endforeach</div>@endif
                        </div>
                    @endif
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">Keine Antwortdaten vorhanden.</div>
                @endforelse
            </div></section>

            <section class="cv-panel overflow-hidden"><div class="border-b border-slate-200 p-5"><h2 class="text-lg font-bold">Vorgangshistorie</h2></div><div class="divide-y divide-slate-100">@forelse($submission->events as $event)<div class="flex gap-4 p-4"><div class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"></div><div class="min-w-0 flex-1"><div class="flex flex-wrap justify-between gap-2"><p class="font-semibold">{{ str_replace('_',' ',ucfirst($event->event_type)) }}</p><time class="text-xs text-slate-400">{{ $event->occurred_at?->format('d.m.Y H:i') }}</time></div><p class="mt-1 text-xs text-slate-500">{{ $event->user?->name ?: 'System / öffentliche Einreichung' }}</p>@if($event->data)<p class="mt-2 text-sm text-slate-600">@foreach($event->data as $key=>$value)@if(is_scalar($value) && filled($value))<span class="mr-3"><span class="font-semibold">{{ str_replace('_',' ',$key) }}:</span> {{ $value }}</span>@endif @endforeach</p>@endif</div></div>@empty<div class="p-8 text-center text-sm text-slate-500">Noch keine Ereignisse.</div>@endforelse</div></section>
        </div>

        <aside class="space-y-5">
            <section class="cv-panel p-5"><h2 class="text-lg font-bold">Workflow</h2>
                @if($submission->steps->isEmpty())<p class="mt-2 text-sm text-slate-500">Für diese Einreichung wurde kein Workflow gestartet.</p>@if($canProcess && !in_array($submission->status,['completed','approved','rejected'],true))<form method="post" action="{{ route('forms.submissions.complete',$submission) }}" class="mt-4">@csrf<textarea class="cv-input" name="comment" placeholder="Abschlussnotiz – optional"></textarea><button class="cv-button-primary mt-2 w-full">Vorgang abschließen</button></form>@endif
                @else
                    <div class="mt-4 space-y-3">@foreach($submission->steps as $step)<div class="rounded-xl border {{ $step->status==='active' ? 'border-blue-300 bg-blue-50/40' : 'border-slate-200' }} p-4"><div class="flex items-center justify-between gap-2"><p class="font-semibold">{{ $step->workflowStep?->name }}</p><span class="text-xs font-semibold">{{ $stepLabels[$step->status] ?? $step->status }}</span></div><p class="mt-1 text-xs text-slate-500">{{ ['review'=>'Prüfung','approval'=>'Genehmigung','task'=>'Aufgabe'][$step->workflowStep?->step_type] ?? $step->workflowStep?->step_type }}@if($step->assignedUser) · {{ $step->assignedUser->name }}@elseif($step->workflowStep?->assignedRole) · Rolle {{ $step->workflowStep->assignedRole->name }}@endif @if($step->due_at)· Frist {{ $step->due_at->format('d.m.Y') }}@endif</p>@if($step->comment)<p class="mt-2 rounded-lg bg-slate-50 p-2 text-sm text-slate-600">{{ $step->comment }}</p>@endif</div>@endforeach</div>
                @endif
            </section>

            @if($canProcess && $submission->currentStep)
                <section class="cv-panel p-5"><h2 class="text-lg font-bold">Aktueller Schritt</h2><p class="mt-1 text-sm text-slate-500">{{ $submission->currentStep->workflowStep?->name }}</p>
                    @if($canActOnCurrentStep)
                        <form method="post" action="{{ route('forms.submissions.process',[$submission,$submission->currentStep]) }}" class="mt-4 space-y-3">@csrf<textarea class="cv-input min-h-24" name="comment" placeholder="Bearbeitungsnotiz – optional"></textarea>@if($submission->currentStep->workflowStep?->decision_required)<div class="grid grid-cols-2 gap-2"><button class="cv-button-primary" name="action" value="approve">Genehmigen</button><button class="cv-button border border-red-300 bg-red-50 text-red-800" name="action" value="reject" onclick="return confirm('Einreichung wirklich ablehnen?')">Ablehnen</button></div>@else<button class="cv-button-primary w-full" name="action" value="complete">Schritt erledigen</button>@endif</form>
                    @else<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Dieser Schritt ist einem anderen Bearbeiter oder einer anderen Rolle zugeordnet.</div>@endif
                    <form method="post" action="{{ route('forms.submissions.reassign',$submission) }}" class="mt-4 border-t border-slate-200 pt-4">@csrf @method('PATCH')<label class="cv-label">Direkt zuweisen</label><div class="flex gap-2"><select class="cv-input" name="assigned_user_id"><option value="">Workflow-Zuordnung verwenden</option>@foreach($tenantUsers as $user)<option value="{{ $user->id }}" @selected($submission->currentStep->assigned_user_id===$user->id)>{{ $user->name }}</option>@endforeach</select><button class="cv-button border border-slate-300 bg-white">Speichern</button></div></form>
                </section>
            @endif

            <section class="cv-panel p-5"><h2 class="text-lg font-bold">Metadaten</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs uppercase tracking-wide text-slate-400">Quelle</dt><dd class="mt-1 font-medium">{{ ($submission->metadata['source']??'internal') === 'public' ? 'Öffentliches Formular' : 'Interne Einreichung' }}</dd></div><div><dt class="text-xs uppercase tracking-wide text-slate-400">Formular</dt><dd class="mt-1 font-medium">{{ $submission->metadata['form_name'] ?? $submission->form?->name }}</dd></div><div><dt class="text-xs uppercase tracking-wide text-slate-400">Erfasst durch</dt><dd class="mt-1 font-medium">{{ $submission->submittedBy?->name ?: 'Öffentlich / System' }}</dd></div></dl></section>
        </aside>
    </div>
</x-layouts.app>
