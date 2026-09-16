<?php

namespace Tests\Feature\Forms;

use App\Models\FormDefinition;
use App\Models\FormWorkflow;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormEditorRenderTest extends TestCase
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

    public function test_form_editor_renders_with_unassigned_workflow_step(): void
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Formularverein',
            'slug' => 'formularverein-editor',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
            'is_super_admin' => true,
        ]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        $form = FormDefinition::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Bearbeitungstest',
            'slug' => 'bearbeitungstest',
            'form_type' => 'internal',
            'status' => 'draft',
            'allow_anonymous' => false,
            'require_member' => false,
            'submission_prefix' => 'FM',
            'version' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $form->fields()->create([
            'field_key' => 'nachricht',
            'label' => 'Nachricht',
            'field_type' => 'text',
            'position' => 10,
            'is_required' => true,
        ]);

        $workflow = FormWorkflow::query()->create([
            'public_id' => Str::uuid(),
            'form_definition_id' => $form->id,
            'name' => 'Standardprüfung',
            'is_active' => true,
        ]);
        $workflow->steps()->create([
            'position' => 10,
            'name' => 'Allgemeine Prüfung',
            'step_type' => 'review',
            'decision_required' => true,
        ]);

        $this->actingAs($user)
            ->get(route('forms.edit', $form))
            ->assertOk()
            ->assertSeeText('Bearbeitungstest')
            ->assertSeeText('Alle berechtigten Bearbeiter');
    }
}
