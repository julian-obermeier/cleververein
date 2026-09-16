<?php

namespace Tests\Feature\Forms;

use App\Models\FormDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormLifecycleGuardTest extends TestCase
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

    public function test_archived_form_rejects_structure_mutations(): void
    {
        [, $user, $form] = $this->tenantUserAndForm();
        $form->update(['status' => 'archived']);

        $this->actingAs($user)
            ->post(route('forms.fields.store', $form), [
                'label' => 'Neues Feld',
                'field_key' => 'neues_feld',
                'field_type' => 'text',
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('form_fields', [
            'form_definition_id' => $form->id,
            'field_key' => 'neues_feld',
        ]);
    }

    public function test_selection_field_without_options_cannot_be_published(): void
    {
        [, $user, $form] = $this->tenantUserAndForm();
        $form->fields()->create([
            'field_key' => 'auswahl',
            'label' => 'Auswahl',
            'field_type' => 'select',
            'position' => 10,
            'is_required' => true,
            'options' => [],
        ]);

        $this->actingAs($user)
            ->post(route('forms.publish', $form))
            ->assertSessionHasErrors('form');

        $this->assertSame('draft', $form->fresh()->status);
    }

    private function tenantUserAndForm(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Lifecycle Verein',
            'slug' => 'lifecycle-'.Str::lower(Str::random(6)),
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
            'name' => 'Lifecycle Formular',
            'slug' => 'lifecycle-formular',
            'form_type' => 'internal',
            'status' => 'draft',
            'allow_anonymous' => false,
            'require_member' => false,
            'submission_prefix' => 'FM',
            'version' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [$tenant, $user, $form];
    }
}
