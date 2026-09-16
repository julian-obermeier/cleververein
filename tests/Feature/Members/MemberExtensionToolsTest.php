<?php

namespace Tests\Feature\Members;

use App\Models\Household;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberExtensionToolsTest extends TestCase
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

    public function test_super_admin_can_create_household_and_attach_member(): void
    {
        [$tenant, $user] = $this->tenantUser();
        app(TenantContext::class)->set($tenant);
        $person = Person::factory()->create(['first_name' => 'Anna', 'last_name' => 'Familie']);
        $member = Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => 'M-2000',
            'status' => 'active',
        ]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->post(route('members.households.central.store'), [
            'name' => 'Familie Beispiel',
            'member_id' => $member->id,
            'relationship' => 'Hauptperson',
            'is_primary_contact' => '1',
        ])->assertRedirect();

        $household = Household::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('name', 'Familie Beispiel')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $this->assertTrue($household->members()->whereKey($member->id)->exists());
        $this->assertTrue((bool) $household->members()->whereKey($member->id)->firstOrFail()->pivot->is_primary_contact);
        app(TenantContext::class)->clear();
    }

    public function test_super_admin_can_open_function_directory(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('members.functions.index'))
            ->assertOk()
            ->assertSeeText('Ämter & Funktionen');
    }

    public function test_super_admin_can_download_xlsx_export(): void
    {
        [, $user] = $this->tenantUser();

        $response = $this->actingAs($user)->get(route('members.export.xlsx'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Testverein',
            'slug' => 'testverein-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);

        return [$tenant, $user];
    }
}
