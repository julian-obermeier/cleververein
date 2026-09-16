<?php

namespace Tests\Feature\Forms;

use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormWorkflow;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Forms\FormEngineService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormWorkflowModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('app/installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed'));
        parent::tearDown();
    }

    public function test_super_admin_can_open_forms_workspace(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('forms.index'))
            ->assertOk()
            ->assertSeeText('Formulare & Workflows');
    }

    public function test_conditional_required_field_is_enforced_server_side(): void
    {
        [, $user] = $this->tenantUser();
        $form = $this->form($user);
        $this->field($form, 'antwort', 'Antwort', 'select', false, ['Ja', 'Nein']);
        $this->field($form, 'details', 'Details', 'textarea', true, null, [
            'field_key' => 'antwort',
            'operator' => 'equals',
            'value' => 'Ja',
        ]);

        try {
            app(FormEngineService::class)->submit($form, ['antwort' => 'Ja'], [], $user);
            $this->fail('Die bedingte Pflichtfeldprüfung hätte fehlschlagen müssen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.details', $exception->errors());
        }

        $submission = app(FormEngineService::class)->submit($form, ['antwort' => 'Nein'], [], $user);
        $this->assertSame('submitted', $submission->status);
        $this->assertDatabaseMissing('form_answers', ['form_submission_id' => $submission->id, 'field_key' => 'details']);
    }

    public function test_public_form_resolves_its_own_tenant_and_accepts_submission(): void
    {
        [$tenantA, $userA] = $this->tenantUser('formular-a');
        $form = $this->form($userA, 'public');
        $form->update(['status' => 'published', 'public_token' => Str::random(48), 'allow_anonymous' => false, 'published_at' => now()]);
        $this->field($form, 'nachricht', 'Nachricht', 'textarea', true);

        app(TenantContext::class)->clear();
        $this->tenantUser('formular-b');
        app(TenantContext::class)->clear();

        $this->post(route('forms.public.store', $form->public_token), [
            'submitter_name' => 'Öffentliche Person',
            'submitter_email' => 'public@example.test',
            'answers' => ['nachricht' => 'Bitte bearbeiten'],
            'website' => '',
        ])->assertRedirect(route('forms.public.show', $form->public_token));

        $this->assertDatabaseHas('form_submissions', [
            'tenant_id' => $tenantA->id,
            'form_definition_id' => $form->id,
            'submitter_email' => 'public@example.test',
        ]);
        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_upload_is_private_and_linked_to_submission(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        $form = $this->form($user);
        $field = $this->field($form, 'anlage', 'Anlage', 'file', true);

        $submission = app(FormEngineService::class)->submit(
            $form,
            [],
            ['anlage' => UploadedFile::fake()->create('nachweis.pdf', 120, 'application/pdf')],
            $user,
        );

        $attachment = $submission->attachments()->firstOrFail();
        $this->assertSame($field->id, $attachment->form_field_id);
        $this->assertSame('local', $attachment->disk);
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($user)->get(route('forms.attachments.download', [$submission, $attachment]))->assertOk();
    }

    public function test_two_step_workflow_advances_and_finishes(): void
    {
        [, $user] = $this->tenantUser();
        $form = $this->form($user);
        $this->field($form, 'grund', 'Grund', 'text', true);
        $workflow = FormWorkflow::query()->create([
            'public_id' => Str::uuid(),
            'form_definition_id' => $form->id,
            'name' => 'Freigabe',
            'is_active' => true,
        ]);
        $first = $workflow->steps()->create([
            'position' => 10,
            'name' => 'Sachprüfung',
            'step_type' => 'approval',
            'assigned_user_id' => $user->id,
            'decision_required' => true,
        ]);
        $second = $workflow->steps()->create([
            'position' => 20,
            'name' => 'Umsetzung',
            'step_type' => 'task',
            'assigned_user_id' => $user->id,
            'decision_required' => false,
        ]);

        $submission = app(FormEngineService::class)->submit($form, ['grund' => 'Test'], [], $user);
        $this->assertSame('in_review', $submission->status);
        $this->assertSame($first->id, $submission->currentStep->workflow_step_id);

        $submission = app(FormEngineService::class)->processStep($submission, $submission->currentStep, $user, 'approve', 'Geprüft');
        $this->assertSame('in_review', $submission->status);
        $this->assertSame($second->id, $submission->currentStep->workflow_step_id);

        $submission = app(FormEngineService::class)->processStep($submission, $submission->currentStep, $user, 'complete', 'Erledigt');
        $this->assertSame('approved', $submission->status);
        $this->assertNull($submission->current_step_id);
        $this->assertNotNull($submission->completed_at);
    }

    public function test_submission_keeps_field_snapshot_after_form_changes(): void
    {
        [, $user] = $this->tenantUser();
        $form = $this->form($user);
        $field = $this->field($form, 'thema', 'Ursprüngliche Bezeichnung', 'text', true);
        $submission = app(FormEngineService::class)->submit($form, ['thema' => 'Inhalt'], [], $user);

        $field->update(['label' => 'Neue Bezeichnung']);
        $snapshot = collect($submission->fresh()->metadata['field_snapshot'])->firstWhere('key', 'thema');

        $this->assertSame('Ursprüngliche Bezeichnung', $snapshot['label']);
        $this->assertSame('Inhalt', $submission->answers()->where('field_key', 'thema')->value('value_text'));
    }

    public function test_submission_route_binding_is_tenant_isolated(): void
    {
        [$tenantA, $userA] = $this->tenantUser('forms-a');
        $formA = $this->form($userA);
        $submissionA = app(FormEngineService::class)->submit($formA, [], [], $userA);

        app(TenantContext::class)->clear();
        [, $userB] = $this->tenantUser('forms-b');
        $formB = $this->form($userB);
        $submissionB = app(FormEngineService::class)->submit($formB, [], [], $userB);

        app(TenantContext::class)->set($tenantA);
        $this->actingAs($userA)->get(route('forms.submissions.show', $submissionB))->assertNotFound();
        $this->actingAs($userA)->get(route('forms.submissions.show', $submissionA))->assertOk();
    }

    public function test_member_can_be_linked_to_internal_submission(): void
    {
        [, $user] = $this->tenantUser();
        $form = $this->form($user);
        $person = Person::factory()->create(['first_name' => 'Mara', 'last_name' => 'Muster']);
        $member = Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => 'M-FORM-001',
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);

        $submission = app(FormEngineService::class)->submit($form, [], [], $user, $member);

        $this->assertSame($member->id, $submission->member_id);
    }

    private function form(User $user, string $type = 'internal'): FormDefinition
    {
        return FormDefinition::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Testformular',
            'slug' => 'testformular-'.Str::lower(Str::random(6)),
            'form_type' => $type,
            'status' => $type === 'internal' ? 'published' : 'draft',
            'public_token' => $type === 'public' ? Str::random(48) : null,
            'allow_anonymous' => false,
            'require_member' => false,
            'submission_prefix' => 'FM',
            'version' => 1,
            'published_at' => $type === 'internal' ? now() : null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function field(FormDefinition $form, string $key, string $label, string $type, bool $required = false, ?array $options = null, ?array $condition = null): FormField
    {
        return $form->fields()->create([
            'field_key' => $key,
            'label' => $label,
            'field_type' => $type,
            'position' => ((int) $form->fields()->max('position')) + 10,
            'is_required' => $required,
            'options' => $options,
            'condition' => $condition,
        ]);
    }

    private function tenantUser(string $slug = 'formularverein'): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
