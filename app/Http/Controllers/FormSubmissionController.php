<?php

namespace App\Http\Controllers;

use App\Models\FormAttachment;
use App\Models\FormDefinition;
use App\Models\FormSubmission;
use App\Models\FormSubmissionStep;
use App\Models\Member;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Forms\FormEngineService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormSubmissionController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FormEngineService $engine,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize($request, 'forms.submissions');
        $query = FormSubmission::query()->with(['form', 'member.person', 'currentStep.workflowStep'])->orderByDesc('submitted_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('form_id')) {
            $query->where('form_definition_id', $request->integer('form_id'));
        }
        if ($request->filled('q')) {
            $q = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')->toString()).'%';
            $query->where(function ($builder) use ($q): void {
                $builder->where('reference_number', 'like', $q)
                    ->orWhere('submitter_name', 'like', $q)
                    ->orWhere('submitter_email', 'like', $q)
                    ->orWhereHas('member.person', fn ($person) => $person->where('first_name', 'like', $q)->orWhere('last_name', 'like', $q));
            });
        }

        return view('forms.submissions.index', [
            'submissions' => $query->paginate(30)->withQueryString(),
            'forms' => FormDefinition::query()->orderBy('name')->get(),
            'canProcess' => $this->can($request, 'forms.process'),
        ]);
    }

    public function fill(Request $request, FormDefinition $form): View
    {
        $this->authorize($request, 'forms.submit', $form->organization_unit_id);
        abort_unless($form->form_type === 'internal' && $form->status === 'published', 404);
        $form->load('fields');

        return view('forms.fill', [
            'form' => $form,
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(2000)->get(),
        ]);
    }

    public function store(Request $request, FormDefinition $form): RedirectResponse
    {
        $this->authorize($request, 'forms.submit', $form->organization_unit_id);
        abort_unless($form->form_type === 'internal' && $form->status === 'published', 404);
        $data = $request->validate([
            'member_id' => [$form->require_member ? 'required' : 'nullable', 'integer'],
            'submitter_name' => ['nullable', 'string', 'max:180'],
            'submitter_email' => ['nullable', 'email:rfc', 'max:255'],
            'answers' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
        ]);
        $member = isset($data['member_id']) ? Member::query()->whereKey($data['member_id'])->firstOrFail() : null;
        $submission = $this->engine->submit(
            $form,
            $request->input('answers', []),
            $request->file('files', []),
            $request->user(),
            $member,
            $data['submitter_name'] ?? $member?->person?->display_name,
            $data['submitter_email'] ?? $member?->person?->email,
            ['source' => 'internal'],
        );
        $this->audit->record('forms.submission_created', $submission, new: ['reference_number' => $submission->reference_number, 'form_id' => $form->id]);

        return redirect()->route('forms.submissions.show', $submission)->with('success', "Einreichung {$submission->reference_number} wurde gespeichert.");
    }

    public function show(Request $request, FormSubmission $submission): View
    {
        $submission->loadMissing('form');
        $this->authorize($request, 'forms.submissions', $submission->form->organization_unit_id);
        $submission->load([
            'form.organizationUnit', 'member.person', 'submittedBy.person',
            'answers.field', 'attachments.field',
            'steps.workflowStep.assignedRole', 'steps.assignedUser.person', 'steps.decisionBy.person',
            'currentStep.workflowStep.assignedRole', 'events.user.person',
        ]);

        return view('forms.submissions.show', [
            'submission' => $submission,
            'canProcess' => $this->can($request, 'forms.process', $submission->form->organization_unit_id),
            'tenantUsers' => $this->tenantUsers(),
            'canActOnCurrentStep' => $submission->currentStep
                ? $this->engine->userCanActOnStep($submission, $submission->currentStep, $request->user())
                : false,
        ]);
    }

    public function process(Request $request, FormSubmission $submission, FormSubmissionStep $step): RedirectResponse
    {
        $submission->loadMissing('form');
        $this->authorize($request, 'forms.process', $submission->form->organization_unit_id);
        abort_unless($step->form_submission_id === $submission->id, 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'complete'])],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $old = $submission->only(['status', 'current_step_id']);
        $updated = $this->engine->processStep($submission, $step, $request->user(), $data['action'], $data['comment'] ?? null);
        $this->audit->record('forms.submission_step_processed', $updated, old: $old, new: ['status' => $updated->status, 'action' => $data['action']]);

        return back()->with('success', 'Workflow-Schritt wurde verarbeitet.');
    }

    public function reassign(Request $request, FormSubmission $submission): RedirectResponse
    {
        $submission->loadMissing(['form', 'currentStep']);
        $this->authorize($request, 'forms.process', $submission->form->organization_unit_id);
        if (! $submission->currentStep) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Diese Einreichung hat keinen aktiven Workflow-Schritt.']);
        }
        $data = $request->validate(['assigned_user_id' => ['nullable', 'integer']]);
        $user = isset($data['assigned_user_id']) ? $this->tenantUser((int) $data['assigned_user_id']) : null;
        $submission->currentStep->update(['assigned_user_id' => $user?->id]);
        $this->engine->event($submission, 'workflow_reassigned', $request->user(), [
            'assigned_user_id' => $user?->id,
            'assigned_user_name' => $user?->name,
        ]);
        $this->audit->record('forms.submission_reassigned', $submission, new: ['assigned_user_id' => $user?->id]);

        return back()->with('success', $user ? "Vorgang wurde {$user->name} zugewiesen." : 'Direkte Benutzerzuweisung wurde aufgehoben; die Workflow-Rolle gilt wieder.');
    }

    public function complete(Request $request, FormSubmission $submission): RedirectResponse
    {
        $submission->loadMissing('form');
        $this->authorize($request, 'forms.process', $submission->form->organization_unit_id);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:10000']]);
        $this->engine->completeWithoutWorkflow($submission, $request->user(), $data['comment'] ?? null);
        $this->audit->record('forms.submission_completed', $submission, new: ['status' => 'completed']);

        return back()->with('success', 'Einreichung wurde abgeschlossen.');
    }

    public function downloadAttachment(Request $request, FormSubmission $submission, FormAttachment $attachment): BinaryFileResponse
    {
        $submission->loadMissing('form');
        $this->authorize($request, 'forms.submissions', $submission->form->organization_unit_id);
        abort_unless($attachment->form_submission_id === $submission->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->download(Storage::disk($attachment->disk)->path($attachment->path), $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
