<?php

namespace App\Services\Forms;

use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionEvent;
use App\Models\FormSubmissionStep;
use App\Models\Member;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class FormEngineService
{
    public function __construct(private TenantContext $tenant) {}

    public function validateSubmission(FormDefinition $form, array $answers, array $files): array
    {
        $form->loadMissing('fields');
        $rules = [];
        $attributes = [];

        foreach ($form->fields as $field) {
            if (in_array($field->field_type, ['heading', 'info'], true) || ! $this->fieldVisible($field, $answers)) {
                continue;
            }

            $attributes['answers.'.$field->field_key] = $field->label;
            $attributes['files.'.$field->field_key] = $field->label;
            $required = $field->is_required ? 'required' : 'nullable';
            $validation = $field->validation ?? [];

            if ($field->field_type === 'file') {
                $maxKb = min(20480, max(1, (int) ($validation['max_kb'] ?? 10240)));
                $extensions = $validation['extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
                $extensions = array_values(array_intersect($extensions, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']));
                $rules['files.'.$field->field_key] = [$required, 'file', 'max:'.$maxKb, 'mimes:'.implode(',', $extensions ?: ['pdf'])];

                continue;
            }

            $fieldRules = [$required];
            switch ($field->field_type) {
                case 'email':
                    $fieldRules[] = 'email:rfc';
                    $fieldRules[] = 'max:255';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    if (isset($validation['min'])) {
                        $fieldRules[] = 'min:'.(float) $validation['min'];
                    }
                    if (isset($validation['max'])) {
                        $fieldRules[] = 'max:'.(float) $validation['max'];
                    }
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'checkbox':
                    $fieldRules = $field->is_required ? ['accepted'] : ['nullable', 'boolean'];
                    break;
                case 'select':
                case 'radio':
                    $fieldRules[] = Rule::in($this->optionValues($field));
                    break;
                case 'multiselect':
                    $fieldRules[] = 'array';
                    $rules['answers.'.$field->field_key.'.*'] = [Rule::in($this->optionValues($field))];
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:'.min(50000, max(1, (int) ($validation['max_length'] ?? ($field->field_type === 'textarea' ? 10000 : 1000))));
            }
            $rules['answers.'.$field->field_key] = $fieldRules;
        }

        $validated = Validator::make(
            ['answers' => $answers, 'files' => $files],
            $rules,
            [],
            $attributes,
        )->validate();

        return [
            'answers' => $validated['answers'] ?? [],
            'files' => $validated['files'] ?? [],
        ];
    }

    public function submit(
        FormDefinition $form,
        array $answers,
        array $files,
        ?User $user = null,
        ?Member $member = null,
        ?string $submitterName = null,
        ?string $submitterEmail = null,
        array $metadata = [],
    ): FormSubmission {
        $validated = $this->validateSubmission($form, $answers, $files);
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($form, $validated, $user, $member, $submitterName, $submitterEmail, $metadata, &$storedPaths): FormSubmission {
                $form->loadMissing(['fields', 'workflows.steps']);
                $snapshot = $form->fields->map(fn (FormField $field) => [
                    'key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->field_type,
                    'options' => $field->options,
                    'required' => $field->is_required,
                ])->values()->all();

                foreach ($form->fields as $field) {
                    $binding = $field->settings['binding'] ?? null;
                    $value = $validated['answers'][$field->field_key] ?? null;
                    if ($binding === 'submitter_name' && filled($value)) {
                        $submitterName = (string) $value;
                    }
                    if ($binding === 'submitter_email' && filled($value)) {
                        $submitterEmail = (string) $value;
                    }
                }

                $submission = FormSubmission::query()->create([
                    'public_id' => Str::uuid(),
                    'form_definition_id' => $form->id,
                    'member_id' => $member?->id,
                    'submitted_by_user_id' => $user?->id,
                    'reference_number' => $this->nextReference($form),
                    'submitter_name' => $submitterName,
                    'submitter_email' => $submitterEmail,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'metadata' => [
                        ...$metadata,
                        'form_version' => $form->version,
                        'form_name' => $form->name,
                        'field_snapshot' => $snapshot,
                    ],
                ]);

                foreach ($form->fields as $field) {
                    if (in_array($field->field_type, ['heading', 'info', 'file'], true) || ! $this->fieldVisible($field, $validated['answers'])) {
                        continue;
                    }
                    if (! array_key_exists($field->field_key, $validated['answers'])) {
                        continue;
                    }
                    $value = $validated['answers'][$field->field_key];
                    $submission->answers()->create([
                        'form_field_id' => $field->id,
                        'field_key' => $field->field_key,
                        'value_text' => is_array($value) ? null : $this->scalarValue($value),
                        'value_json' => is_array($value) ? array_values($value) : null,
                    ]);
                }

                foreach ($form->fields->where('field_type', 'file') as $field) {
                    $file = $validated['files'][$field->field_key] ?? null;
                    if (! $file instanceof UploadedFile || ! $this->fieldVisible($field, $validated['answers'])) {
                        continue;
                    }
                    $path = $file->store("form-submissions/{$this->tenant->id()}/{$submission->public_id}", 'local');
                    $storedPaths[] = $path;
                    $submission->attachments()->create([
                        'public_id' => Str::uuid(),
                        'form_field_id' => $field->id,
                        'field_key' => $field->field_key,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize() ?: 0,
                        'disk' => 'local',
                        'path' => $path,
                        'created_by_user_id' => $user?->id,
                    ]);
                }

                $this->event($submission, 'submitted', $user, ['reference_number' => $submission->reference_number]);
                $this->startWorkflow($submission, $form);

                return $submission->fresh(['answers', 'attachments', 'steps.workflowStep', 'currentStep.workflowStep']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function processStep(FormSubmission $submission, FormSubmissionStep $step, User $user, string $action, ?string $comment = null): FormSubmission
    {
        return DB::transaction(function () use ($submission, $step, $user, $action, $comment): FormSubmission {
            $submission = FormSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $step = FormSubmissionStep::query()->whereKey($step->id)->lockForUpdate()->with('workflowStep')->firstOrFail();
            if ($step->form_submission_id !== $submission->id || $submission->current_step_id !== $step->id || $step->status !== 'active') {
                throw ValidationException::withMessages(['step' => 'Dieser Workflow-Schritt ist nicht mehr aktiv.']);
            }
            if (! $this->userCanActOnStep($submission, $step, $user)) {
                throw ValidationException::withMessages(['step' => 'Dieser Workflow-Schritt ist einem anderen Bearbeiter oder einer anderen Rolle zugeordnet.']);
            }
            if (! in_array($action, ['approve', 'reject', 'complete'], true)) {
                throw ValidationException::withMessages(['action' => 'Ungültige Workflow-Aktion.']);
            }
            if ($step->workflowStep->decision_required && $action === 'complete') {
                throw ValidationException::withMessages(['action' => 'Dieser Schritt benötigt eine Genehmigungsentscheidung.']);
            }

            $newStatus = $action === 'reject' ? 'rejected' : ($action === 'complete' ? 'completed' : 'approved');
            $step->update([
                'status' => $newStatus,
                'decided_at' => now(),
                'decision_by_user_id' => $user->id,
                'comment' => $comment,
            ]);
            $this->event($submission, 'workflow_'.$newStatus, $user, [
                'step' => $step->workflowStep->name,
                'comment' => $comment,
            ]);

            if ($action === 'reject') {
                $submission->update(['status' => 'rejected', 'current_step_id' => null, 'completed_at' => now()]);

                return $submission->fresh();
            }

            $next = $submission->steps()
                ->where('status', 'pending')
                ->where('id', '>', $step->id)
                ->orderBy('id')
                ->with('workflowStep')
                ->first();

            if ($next) {
                $next->update([
                    'status' => 'active',
                    'started_at' => now(),
                    'due_at' => $next->workflowStep->due_days ? now()->addDays($next->workflowStep->due_days) : null,
                ]);
                $submission->update(['status' => 'in_review', 'current_step_id' => $next->id]);
                $this->event($submission, 'workflow_step_started', null, ['step' => $next->workflowStep->name]);
            } else {
                $submission->update(['status' => 'approved', 'current_step_id' => null, 'completed_at' => now()]);
                $this->event($submission, 'workflow_completed', $user);
            }

            return $submission->fresh(['currentStep.workflowStep']);
        });
    }

    public function completeWithoutWorkflow(FormSubmission $submission, User $user, ?string $comment = null): FormSubmission
    {
        if ($submission->current_step_id) {
            throw ValidationException::withMessages(['submission' => 'Diese Einreichung besitzt einen aktiven Workflow.']);
        }
        if (in_array($submission->status, ['approved', 'rejected', 'completed'], true)) {
            return $submission;
        }
        $submission->update(['status' => 'completed', 'completed_at' => now()]);
        $this->event($submission, 'completed', $user, ['comment' => $comment]);

        return $submission->fresh();
    }

    public function userCanActOnStep(FormSubmission $submission, FormSubmissionStep $step, User $user): bool
    {
        if ($user->is_super_admin) {
            return true;
        }
        $step->loadMissing('workflowStep');
        if ($step->assigned_user_id) {
            return $step->assigned_user_id === $user->id;
        }
        $definition = $step->workflowStep;
        if (! $definition->assigned_role_id) {
            return true;
        }

        return DB::table('role_assignments')
            ->where('tenant_id', $this->tenant->id())
            ->where('user_id', $user->id)
            ->where('role_id', $definition->assigned_role_id)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->get()
            ->contains(function ($assignment) use ($submission): bool {
                if ($assignment->organization_unit_id === null) {
                    return true;
                }
                $organizationId = $submission->form?->organization_unit_id;
                if (! $organizationId) {
                    return false;
                }
                if ((int) $assignment->organization_unit_id === (int) $organizationId) {
                    return true;
                }

                return (bool) $assignment->include_descendants && DB::table('organization_closure')
                    ->where('tenant_id', $this->tenant->id())
                    ->where('ancestor_id', $assignment->organization_unit_id)
                    ->where('descendant_id', $organizationId)
                    ->exists();
            });
    }

    public function fieldVisible(FormField $field, array $answers): bool
    {
        $condition = $field->condition;
        if (! $condition) {
            return true;
        }

        return $this->evaluateCondition($condition, $answers);
    }

    public function event(FormSubmission $submission, string $type, ?User $user = null, array $data = []): FormSubmissionEvent
    {
        return $submission->events()->create([
            'event_type' => $type,
            'user_id' => $user?->id,
            'data' => $data ?: null,
            'occurred_at' => now(),
        ]);
    }

    private function startWorkflow(FormSubmission $submission, FormDefinition $form): void
    {
        $workflow = $form->workflows->first(fn ($workflow) => $workflow->is_active && $workflow->steps->isNotEmpty());
        if (! $workflow) {
            return;
        }

        $created = collect();
        foreach ($workflow->steps as $definition) {
            $created->push($submission->steps()->create([
                'form_workflow_step_id' => $definition->id,
                'status' => 'pending',
                'assigned_user_id' => $definition->assigned_user_id,
            ]));
        }
        $first = $created->first();
        if (! $first) {
            return;
        }
        $firstDefinition = $workflow->steps->first();
        $first->update([
            'status' => 'active',
            'started_at' => now(),
            'due_at' => $firstDefinition->due_days ? now()->addDays($firstDefinition->due_days) : null,
        ]);
        $submission->update(['status' => 'in_review', 'current_step_id' => $first->id]);
        $this->event($submission, 'workflow_started', null, ['workflow' => $workflow->name, 'step' => $firstDefinition->name]);
    }

    private function nextReference(FormDefinition $form): string
    {
        $year = (int) now()->format('Y');
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $form->submission_prefix ?: 'FM')) ?: 'FM';
        $prefix = substr($prefix, 0, 12);

        return DB::transaction(function () use ($prefix, $year): string {
            $key = 'submission:'.$prefix;
            $row = DB::table('form_sequences')
                ->where('tenant_id', $this->tenant->id())
                ->where('sequence_key', $key)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            if (! $row) {
                DB::table('form_sequences')->insert([
                    'tenant_id' => $this->tenant->id(),
                    'sequence_key' => $key,
                    'year' => $year,
                    'next_value' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $value = 1;
            } else {
                $value = (int) $row->next_value;
                DB::table('form_sequences')->where('id', $row->id)->update(['next_value' => $value + 1, 'updated_at' => now()]);
            }

            return sprintf('%s-%d-%06d', $prefix, $year, $value);
        });
    }

    private function evaluateCondition(array $condition, array $answers): bool
    {
        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $child) {
                if (! is_array($child) || ! $this->evaluateCondition($child, $answers)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $child) {
                if (is_array($child) && $this->evaluateCondition($child, $answers)) {
                    return true;
                }
            }

            return false;
        }

        $key = (string) ($condition['field_key'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'equals');
        $expected = $condition['value'] ?? null;
        $actual = $answers[$key] ?? null;

        return match ($operator) {
            'not_equals' => (string) $actual !== (string) $expected,
            'contains' => is_array($actual) ? in_array((string) $expected, array_map('strval', $actual), true) : str_contains((string) $actual, (string) $expected),
            'not_contains' => is_array($actual) ? ! in_array((string) $expected, array_map('strval', $actual), true) : ! str_contains((string) $actual, (string) $expected),
            'filled' => filled($actual),
            'empty' => blank($actual),
            default => (string) $actual === (string) $expected,
        };
    }

    private function optionValues(FormField $field): array
    {
        return collect($field->options ?? [])->map(function ($option): string {
            if (is_array($option)) {
                return (string) ($option['value'] ?? $option['label'] ?? '');
            }

            return (string) $option;
        })->filter(fn ($value) => $value !== '')->values()->all();
    }

    private function scalarValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
