<?php

namespace Tests\Feature\Members;

use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberManagementTest extends TestCase
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

    public function test_super_admin_can_create_member_in_current_tenant(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)->post(route('members.store'), [
            'first_name' => 'Erika',
            'last_name' => 'Musterfrau',
            'email' => 'erika@example.test',
            'member_number' => 'M-1000',
            'status' => 'active',
            'joined_at' => '2026-09-16',
            'membership_status' => 'active',
        ])->assertRedirect();

        $member = Member::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('member_number', 'M-1000')->first();
        $this->assertNotNull($member);
        $this->assertSame('Erika', $member->person->first_name);
        $this->assertSame('active', $member->status);
    }

    public function test_member_index_only_shows_current_tenant_records(): void
    {
        [$tenantA, $user] = $this->tenantUser('Mandant A', 'mandant-a');
        $tenantB = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => 'Mandant B', 'slug' => 'mandant-b', 'status' => 'active']);

        app(TenantContext::class)->set($tenantA);
        $personA = Person::factory()->create(['first_name' => 'Anna', 'last_name' => 'Alpha']);
        Member::query()->create(['public_id' => Str::uuid(), 'person_id' => $personA->id, 'member_number' => 'A-1', 'status' => 'active']);
        app(TenantContext::class)->set($tenantB);
        $personB = Person::factory()->create(['first_name' => 'Bernd', 'last_name' => 'Beta']);
        Member::query()->create(['public_id' => Str::uuid(), 'person_id' => $personB->id, 'member_number' => 'B-1', 'status' => 'active']);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get(route('members.index'))
            ->assertOk()
            ->assertSee('Anna Alpha')
            ->assertDontSee('Bernd Beta');
    }

    private function tenantUser(string $name = 'Testverein', string $slug = 'testverein'): array
    {
        $tenant = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => $name, 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);

        return [$tenant, $user];
    }
}
