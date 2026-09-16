<?php

namespace App\Http\Controllers;

use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormWorkflow;
use App\Models\FormWorkflowStep;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FormController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize($request, 'forms.view');
        $query = FormDefinition::query()->with('organizationUnit')->withCount(['fields', 'submissions']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $q = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')->toString()).'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $q)->orWhere('slug', 'like', $q));
        }

        return view('forms.index', [
            'forms' => $query->orderByRaw("CASE WHEN status = 'published' THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END")->orderBy('name')->get(),
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'canManage' => $this->can($request, 'forms.manage'),
            'canSubmit' => $this->can($request, 'forms.submit'),
            'canViewSubmissions' => $this->can($request, 'forms.submissions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request, 'forms.manage');
        $data = $request->validate([
            'organization_unit_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'alpha_dash', 'max:120'],
            'description' => ['nullable', 'string', 'max:10000'],
            'form_type' => ['required', Rule::in(['internal', 'public'])],
            'allow_anonymous' => ['nullable', 'boolean'],
            'require_member' => ['nullable', 'boolean'],
            'submission_prefix' => ['nullable', 'alpha_num', 'max:12'],
        ]);
        $organization = $this->organization($data['organization_unit_id'] ?? null);
        $this->authorize($request, 'forms.manage', $organization?->id);
        if ($data['form_type'] === 'public' && ($data['require_member'] ?? false)) {
            throw ValidationException::withMessages(['require_member' => 'Öffentliche Formulare können in dieser Version keine Mitgliedsanmeldung erzwingen.']);
        }
        $baseSlug = Str::slug($data['slug'] ?? $data['name']) ?: 'formular';
        $slug = $this->uniqueSlug($baseSlug);
        $form = FormDefinition::query()->create([
            'public_id' => Str::uuid(),
            'organization_unit_id' => $organization?->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'form_type' => $data['form_type'],
            'status' => 'draft',
            'public_token' => $data['form_type'] === 'public' ? Str::random(48) : null,
            'allow_anonymous' => (bool) ($data['allow_anonymous'] ?? false),
            'require_member' => (bool) ($data['require_member'] ?? false),
            'submission_prefix' => strtoupper($data['submission_prefix'] ?? 'FM'),
            'version' => 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->record('forms.created', $form, new: $form->only(['name', 'slug', 'form_type', 'organization_unit_id']));

        return redirect()->route('forms.edit', $form)->with('success', 'Formular wurde angelegt.');
    }

    public function edit(Request $request, FormDefinition $form): View
    {
        $this->authorize($request, 'forms.view', $form->organization_unit_id);
        $form->load([
            'organizationUnit',
            'fields',
            'workflows.steps.assignedUser.person',
            'workflows.steps.assignedRole',
        ]);

        return view('forms.edit', [
            'form' => $form,
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'users' => $this->tenantUsers(),
            'roles' => Role::query()->orderBy('name')->get(),
            'canManage' => $this->can($request, 'forms.manage', $form->organization_unit_id),
            'canWorkflow' => $this->can($request, 'forms.workflows', $form->organization_unit_id),
            'canSubmit' => $this->can($request, 'forms.submit', $form->organization_unit_id),
        ]);
    }

    public function update(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        $data = $request->validate([
            'organization_unit_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => [
                'required', 'alpha_dash', 'max:120',
                Rule::unique('form_definitions', 'slug')->where(fn ($query) => $query->where('tenant_id', $this->tenant->id()))->ignore($form->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'form_type' => ['required', Rule::in(['internal', 'public'])],
            'allow_anonymous' => ['nullable', 'boolean'],
            'require_member' => ['nullable', 'boolean'],
            'submission_prefix' => ['required', 'alpha_num', 'max:12'],
            'success_message' => ['nullable', 'string', 'max:3000'],
        ]);
        $organization = $this->organization($data['organization_unit_id'] ?? null);
        $this->authorize($request, 'forms.manage', $organization?->id);
        if ($data['form_type'] === 'public' && ($data['require_member'] ?? false)) {
            throw ValidationException::withMessages(['require_member' => 'Öffentliche Formulare können in dieser Version keine Mitgliedsanmeldung erzwingen.']);
        }
        $old = $form->only(['name', 'slug', 'form_type', 'status', 'organization_unit_id']);
        $wasPublished = $form->status === 'published';
        $form->update([
            'organization_unit_id' => $organization?->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'form_type' => $data['form_type'],
            'public_token' => $data['form_type'] === 'public' ? ($form->public_token ?: Str::random(48)) : null,
            'allow_anonymous' => (bool) ($data['allow_anonymous'] ?? false),
            'require_member' => (bool) ($data['require_member'] ?? false),
            'submission_prefix' => strtoupper($data['submission_prefix']),
            'success_message' => $data['success_message'] ?? null,
            'status' => $wasPublished ? 'draft' : $form->status,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->record('forms.updated', $form, old: $old, new: $form->only(['name', 'slug', 'form_type', 'status', 'organization_unit_id']));

        return back()->with('success', $wasPublished ? 'Formular gespeichert und wegen der Änderung auf Entwurf gesetzt.' : 'Formular wurde gespeichert.');
    }

    public function publish(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        $form->load('fields');
        if (! $form->fields->contains(fn (FormField $field) => ! in_array($field->field_type, ['heading', 'info'], true))) {
            throw ValidationException::withMessages(['form' => 'Vor der Veröffentlichung muss mindestens ein Eingabefeld vorhanden sein.']);
        }
        if ($form->form_type === 'public' && $form->require_member) {
            throw ValidationException::withMessages(['form' => 'Öffentliche Formulare dürfen keine interne Mitgliedsanmeldung voraussetzen.']);
        }
        $keys = $form->fields->pluck('field_key')->all();
        foreach ($form->fields as $field) {
            $conditionKey = $field->condition['field_key'] ?? null;
            if ($conditionKey && ! in_array($conditionKey, $keys, true)) {
                throw ValidationException::withMessages(['form' => "Bedingung von „{$field->label}“ verweist auf ein nicht mehr vorhandenes Feld."]);
            }
        }
        $version = $form->published_at ? $form->version + 1 : $form->version;
        $form->update([
            'status' => 'published',
            'version' => $version,
            'published_at' => now(),
            'public_token' => $form->form_type === 'public' ? ($form->public_token ?: Str::random(48)) : null,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->record('forms.published', $form, new: ['version' => $form->version, 'form_type' => $form->form_type]);

        return back()->with('success', "Formular Version {$form->version} wurde veröffentlicht.");
    }

    public function archive(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        $form->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        $this->audit->record('forms.archived', $form, new: ['status' => 'archived']);

        return redirect()->route('forms.index')->with('success', 'Formular wurde archiviert. Bestehende Einreichungen bleiben erhalten.');
    }

    public function storeField(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        $data = $this->validateField($request, $form);
        $key = $this->uniqueFieldKey($form, $data['field_key'] ?: Str::slug($data['label'], '_'));
        $field = $form->fields()->create($this->fieldPayload($data, $key, ((int) $form->fields()->max('position')) + 10));
        $this->markDraft($form, $request->user()->id);
        $this->audit->record('forms.field_created', $field, new: $field->only(['field_key', 'label', 'field_type', 'position']));

        return back()->with('success', 'Feld wurde hinzugefügt.');
    }

    public function updateField(Request $request, FormDefinition $form, FormField $field): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        abort_unless($field->form_definition_id === $form->id, 404);
        $data = $this->validateField($request, $form, $field);
        $key = $data['field_key'] ?: $field->field_key;
        if ($key !== $field->field_key && $form->fields()->where('field_key', $key)->whereKeyNot($field->id)->exists()) {
            throw ValidationException::withMessages(['field_key' => 'Dieser Feldschlüssel ist bereits vergeben.']);
        }
        $old = $field->only(['field_key', 'label', 'field_type', 'position', 'is_required']);
        $field->update($this->fieldPayload($data, $key, $field->position));
        $this->markDraft($form, $request->user()->id);
        $this->audit->record('forms.field_updated', $field, old: $old, new: $field->only(['field_key', 'label', 'field_type', 'position', 'is_required']));

        return back()->with('success', 'Feld wurde aktualisiert.');
    }

    public function destroyField(Request $request, FormDefinition $form, FormField $field): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        abort_unless($field->form_definition_id === $form->id, 404);
        $snapshot = $field->only(['field_key', 'label', 'field_type']);
        $field->delete();
        $this->markDraft($form, $request->user()->id);
        $this->audit->record('forms.field_deleted', $form, old: $snapshot);

        return back()->with('success', 'Feld wurde aus dem Entwurf entfernt. Historische Einreichungen behalten ihren Feld-Snapshot.');
    }

    public function reorderFields(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.manage', $form->organization_unit_id);
        $data = $request->validate(['field_ids' => ['required', 'array'], 'field_ids.*' => ['integer']]);
        $expected = $form->fields()->pluck('id')->sort()->values()->all();
        $received = collect($data['field_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($expected !== $received) {
            throw ValidationException::withMessages(['field_ids' => 'Die Feldreihenfolge enthält fremde oder fehlende Felder.']);
        }
        foreach ($data['field_ids'] as $index => $id) {
            $form->fields()->whereKey($id)->update(['position' => ($index + 1) * 10]);
        }
        $this->markDraft($form, $request->user()->id);
        $this->audit->record('forms.fields_reordered', $form, new: ['field_ids' => array_map('intval', $data['field_ids'])]);

        return back()->with('success', 'Feldreihenfolge wurde gespeichert.');
    }

    public function storeWorkflow(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.workflows', $form->organization_unit_id);
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'is_active' => ['nullable', 'boolean']]);
        if ($data['is_active'] ?? false) {
            $form->workflows()->update(['is_active' => false]);
        }
        $workflow = $form->workflows()->create([
            'public_id' => Str::uuid(),
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $this->audit->record('forms.workflow_created', $workflow, new: $workflow->only(['name', 'is_active']));

        return back()->with('success', 'Workflow wurde angelegt.');
    }

    public function activateWorkflow(Request $request, FormDefinition $form, FormWorkflow $workflow): RedirectResponse
    {
        $this->authorize($request, 'forms.workflows', $form->organization_unit_id);
        abort_unless($workflow->form_definition_id === $form->id, 404);
        $form->workflows()->update(['is_active' => false]);
        $workflow->update(['is_active' => true]);
        $this->audit->record('forms.workflow_activated', $workflow, new: ['is_active' => true]);

        return back()->with('success', 'Workflow ist jetzt für neue Einreichungen aktiv.');
    }

    public function storeWorkflowStep(Request $request, FormDefinition $form, FormWorkflow $workflow): RedirectResponse
    {
        $this->authorize($request, 'forms.workflows', $form->organization_unit_id);
        abort_unless($workflow->form_definition_id === $form->id, 404);
        $data = $this->validateWorkflowStep($request);
        $step = $workflow->steps()->create([
            ...$this->workflowStepPayload($data),
            'position' => ((int) $workflow->steps()->max('position')) + 10,
        ]);
        $this->audit->record('forms.workflow_step_created', $step, new: $step->only(['name', 'step_type', 'position', 'assigned_user_id', 'assigned_role_id']));

        return back()->with('success', 'Workflow-Schritt wurde hinzugefügt.');
    }

    public function updateWorkflowStep(Request $request, FormDefinition $form, FormWorkflow $workflow, FormWorkflowStep $step): RedirectResponse
    {
        $this->authorize($request, 'forms.workflows', $form->organization_unit_id);
        abort_unless($workflow->form_definition_id === $form->id && $step->form_workflow_id === $workflow->id, 404);
        $data = $this->validateWorkflowStep($request);
        $step->update($this->workflowStepPayload($data));
        $this->audit->record('forms.workflow_step_updated', $step, new: $step->only(['name', 'step_type', 'assigned_user_id', 'assigned_role_id', 'due_days']));

        return back()->with('success', 'Workflow-Schritt wurde aktualisiert.');
    }

    public function destroyWorkflowStep(Request $request, FormDefinition $form, FormWorkflow $workflow, FormWorkflowStep $step): RedirectResponse
    {
        $this->authorize($request, 'forms.workflows', $form->organization_unit_id);
        abort_unless($workflow->form_definition_id === $form->id && $step->form_workflow_id === $workflow->id, 404);
        if ($step->submissionSteps()->exists()) {
            throw ValidationException::withMessages(['step' => 'Dieser Schritt wurde bereits in Einreichungen verwendet und kann nicht gelöscht werden.']);
        }
        $step->delete();
        $this->audit->record('forms.workflow_step_deleted', $workflow, old: ['step_id' => $step->id, 'name' => $step->name]);

        return back()->with('success', 'Workflow-Schritt wurde entfernt.');
    }

    public function reorderWorkflowSteps(Request $request, FormDefinition $form, FormWorkflow $workflow): RedirectResponse
    {
        $this->authorize($request, 'forms.workflows', $form->organization_unit_id);
        abort_unless($workflow->form_definition_id === $form->id, 404);
        $data = $request->validate(['step_ids' => ['required', 'array'], 'step_ids.*' => ['integer']]);
        $expected = $workflow->steps()->pluck('id')->sort()->values()->all();
        $received = collect($data['step_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($expected !== $received) {
            throw ValidationException::withMessages(['step_ids' => 'Die Reihenfolge enthält fremde oder fehlende Workflow-Schritte.']);
        }
        foreach ($data['step_ids'] as $index => $id) {
            $workflow->steps()->whereKey($id)->update(['position' => ($index + 1) * 10]);
        }
        $this->audit->record('forms.workflow_steps_reordered', $workflow, new: ['step_ids' => array_map('intval', $data['step_ids'])]);

        return back()->with('success', 'Workflow-Reihenfolge wurde gespeichert.');
    }

    private function validateField(Request $request, FormDefinition $form, ?FormField $field = null): array
    {
        return $request->validate([
            'field_key' => ['nullable', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/', 'max:100'],
            'label' => ['required', 'string', 'max:180'],
            'field_type' => ['required', Rule::in(['text', 'textarea', 'email', 'number', 'date', 'select', 'radio', 'checkbox', 'multiselect', 'file', 'heading', 'info'])],
            'is_required' => ['nullable', 'boolean'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:3000'],
            'options_text' => ['nullable', 'string', 'max:10000'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'max_kb' => ['nullable', 'integer', 'min:1', 'max:20480'],
            'extensions' => ['nullable', 'string', 'max:255'],
            'condition_field_key' => ['nullable', 'string', 'max:100'],
            'condition_operator' => ['nullable', Rule::in(['equals', 'not_equals', 'contains', 'not_contains', 'filled', 'empty'])],
            'condition_value' => ['nullable', 'string', 'max:1000'],
            'binding' => ['nullable', Rule::in(['submitter_name', 'submitter_email'])],
        ]);
    }

    private function fieldPayload(array $data, string $key, int $position): array
    {
        $options = collect(preg_split('/\R/', (string) ($data['options_text'] ?? '')) ?: [])
            ->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
        $condition = null;
        if (filled($data['condition_field_key'] ?? null)) {
            $condition = [
                'field_key' => $data['condition_field_key'],
                'operator' => $data['condition_operator'] ?? 'equals',
                'value' => $data['condition_value'] ?? null,
            ];
        }
        $extensions = collect(explode(',', (string) ($data['extensions'] ?? '')))
            ->map(fn ($value) => strtolower(trim($value)))->filter()->unique()->values()->all();

        return [
            'field_key' => $key,
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'position' => $position,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'placeholder' => $data['placeholder'] ?? null,
            'help_text' => $data['help_text'] ?? null,
            'options' => $options ?: null,
            'validation' => array_filter([
                'max_length' => $data['max_length'] ?? null,
                'max_kb' => $data['max_kb'] ?? null,
                'extensions' => $extensions ?: null,
            ], fn ($value) => $value !== null),
            'condition' => $condition,
            'settings' => filled($data['binding'] ?? null) ? ['binding' => $data['binding']] : null,
        ];
    }

    private function validateWorkflowStep(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'step_type' => ['required', Rule::in(['review', 'approval', 'task'])],
            'assigned_user_id' => ['nullable', 'integer'],
            'assigned_role_id' => ['nullable', 'integer'],
            'due_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'decision_required' => ['nullable', 'boolean'],
        ]);
        if (($data['assigned_user_id'] ?? null) && ($data['assigned_role_id'] ?? null)) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Bitte entweder Benutzer oder Rolle zuweisen, nicht beides.']);
        }
        if ($data['assigned_user_id'] ?? null) {
            $this->tenantUser((int) $data['assigned_user_id']);
        }
        if ($data['assigned_role_id'] ?? null) {
            Role::query()->whereKey($data['assigned_role_id'])->firstOrFail();
        }

        return $data;
    }

    private function workflowStepPayload(array $data): array
    {
        return [
            'name' => $data['name'],
            'step_type' => $data['step_type'],
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'assigned_role_id' => $data['assigned_role_id'] ?? null,
            'due_days' => $data['due_days'] ?? null,
            'decision_required' => $data['step_type'] === 'task' ? (bool) ($data['decision_required'] ?? false) : true,
        ];
    }

    private function markDraft(FormDefinition $form, int $userId): void
    {
        $form->update(['status' => $form->status === 'published' ? 'draft' : $form->status, 'updated_by' => $userId]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::limit($base, 110, '');
        $candidate = $slug;
        $counter = 2;
        while (FormDefinition::query()->where('slug', $candidate)->exists()) {
            $candidate = Str::limit($slug, 105, '').'-'.$counter++;
        }

        return $candidate;
    }

    private function uniqueFieldKey(FormDefinition $form, string $base): string
    {
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $base) ?: 'feld';
        if (! preg_match('/^[a-zA-Z]/', $key)) {
            $key = 'feld_'.$key;
        }
        $key = substr($key, 0, 90);
        $candidate = $key;
        $counter = 2;
        while ($form->fields()->where('field_key', $candidate)->exists()) {
            $candidate = substr($key, 0, 85).'_'.$counter++;
        }

        return $candidate;
    }

    private function organization(?int $id): ?OrganizationUnit
    {
        return $id ? OrganizationUnit::query()->whereKey($id)->firstOrFail() : null;
    }

    private function tenantUsers()
    {
        return User::query()
            ->with('person')
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $this->tenant->id())->where('tenant_user.status', 'active'))
            ->orderBy('email')
            ->get();
    }

    private function tenantUser(int $id): User
    {
        return User::query()
            ->whereKey($id)
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $this->tenant->id())->where('tenant_user.status', 'active'))
            ->firstOrFail();
    }

    private function can(Request $request, string $permission, ?int $organizationUnitId = null): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission, $organizationUnitId);
    }

    private function authorize(Request $request, string $permission, ?int $organizationUnitId = null): void
    {
        abort_unless($this->can($request, $permission, $organizationUnitId), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
