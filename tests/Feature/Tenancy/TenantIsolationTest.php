<?php

namespace Tests\Feature\Tenancy;

use App\Models\OrganizationUnit;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_hides_other_tenant_records(): void
    {
        $a = Tenant::create(['public_id' => Str::uuid(), 'name' => 'Mandant A', 'slug' => 'a', 'status' => 'active']);
        $b = Tenant::create(['public_id' => Str::uuid(), 'name' => 'Mandant B', 'slug' => 'b', 'status' => 'active']);
        $typeA = DB::table('organization_types')->insertGetId(['tenant_id' => $a->id, 'name' => 'Verein', 'slug' => 'verein', 'created_at' => now(), 'updated_at' => now()]);
        $typeB = DB::table('organization_types')->insertGetId(['tenant_id' => $b->id, 'name' => 'Verein', 'slug' => 'verein', 'created_at' => now(), 'updated_at' => now()]);
        $context = app(TenantContext::class);
        $context->set($a);
        OrganizationUnit::create(['public_id' => Str::uuid(), 'organization_type_id' => $typeA, 'name' => 'Nur A', 'slug' => 'nur-a']);
        $context->set($b);
        OrganizationUnit::create(['public_id' => Str::uuid(), 'organization_type_id' => $typeB, 'name' => 'Nur B', 'slug' => 'nur-b']);

        $this->assertSame(['Nur B'], OrganizationUnit::pluck('name')->all());
        $this->assertNull(OrganizationUnit::where('name', 'Nur A')->first());
    }
}
