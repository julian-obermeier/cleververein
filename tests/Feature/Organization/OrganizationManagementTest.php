<?php

namespace Tests\Feature\Organization;

use App\Models\OrganizationUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
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

    public function test_super_admin_can_create_nested_organization_units(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $typeId = DB::table('organization_types')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Verein',
            'slug' => 'verein',
            'sort_order' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('organization.units.store'), [
            'organization_type_id' => $typeId,
            'name' => 'Hauptverein',
            'status' => 'active',
        ])->assertRedirect();

        $parent = OrganizationUnit::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('name', 'Hauptverein')->firstOrFail();

        $this->actingAs($user)->post(route('organization.units.store'), [
            'organization_type_id' => $typeId,
            'parent_id' => $parent->id,
            'name' => 'Jugendabteilung',
            'status' => 'active',
        ])->assertRedirect();

        $child = OrganizationUnit::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('name', 'Jugendabteilung')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertDatabaseHas('organization_closure', [
            'tenant_id' => $tenant->id,
            'ancestor_id' => $parent->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => 'Testverein', 'slug' => 'testverein', 'status' => 'active']);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);

        return [$tenant, $user];
    }
}
