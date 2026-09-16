<?php

namespace Tests\Feature\Members;

use App\Models\FunctionDefinition;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtendedMemberManagementTest extends TestCase
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

    public function test_possible_duplicate_requires_explicit_confirmation(): void
    {
        [, $user] = $this->tenantUser();

        $payload = [
            'first_name' => 'Erika',
            'last_name' => 'Musterfrau',
            'email' => 'erika@example.test',
            'status' => 'active',
            'membership_status' => 'active',
        ];

        $this->actingAs($user)->post(route('members.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('members.store'), $payload)
            ->assertSessionHasErrors('duplicate');

        $this->assertSame(1, Member::withoutGlobalScope('tenant')->count());
    }

    public function test_super_admin_can_create_member_master_data(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $this->actingAs($user)->post(route('members.types.store'), [
            'name' => 'Familienmitglied',
            'code' => 'FAMILIE',
            'sort_order' => 60,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('members.functions.definitions.store'), [
            'name' => 'Jugendwart',
            'code' => 'JUGENDWART',
            'category' => 'Jugendarbeit',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('members.types.store'), [
            'name' => 'Familienmitglied',
            'code' => 'FAMILIE2',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseHas('member_types', ['tenant_id' => $tenant->id, 'name' => 'Familienmitglied']);
        $this->assertDatabaseHas('function_definitions', ['tenant_id' => $tenant->id, 'name' => 'Jugendwart']);
    }

    public function test_member_can_be_assigned_to_household_and_function(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $this->actingAs($user)->post(route('members.store'), [
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'status' => 'active',
            'membership_status' => 'active',
        ])->assertRedirect();

        $member = Member::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $function = FunctionDefinition::query()->create(['name' => 'Jugendwart', 'code' => 'JUGENDWART', 'is_active' => true]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->post(route('members.households.store', $member), [
            'name' => 'Familie Muster',
            'relationship' => 'Elternteil',
            'is_primary_contact' => 1,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('members.functions.store', $member), [
            'function_definition_id' => $function->id,
            'starts_at' => '2026-09-16',
        ])->assertRedirect();

        $householdId = DB::table('households')->where('tenant_id', $tenant->id)->value('id');
        $this->assertNotNull($householdId);
        $this->assertDatabaseHas('household_members', ['tenant_id' => $tenant->id, 'household_id' => $householdId, 'member_id' => $member->id]);
        $this->assertDatabaseHas('function_assignments', ['tenant_id' => $tenant->id, 'member_id' => $member->id, 'function_definition_id' => $function->id]);
    }

    public function test_csv_import_skips_duplicate_rows(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $csv = "Vorname;Nachname;E-Mail;Status\nAnna;Alpha;anna@example.test;active\nAnna;Alpha;anna@example.test;active\n";

        $this->actingAs($user)->post(route('members.import'), [
            'file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        ])->assertRedirect();

        $this->assertSame(1, Member::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count());
    }

    public function test_csv_import_understands_german_dates_and_assigns_known_master_data(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $organizationTypeId = DB::table('organization_types')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Verein',
            'slug' => 'verein',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $organizationId = DB::table('organization_units')->insertGetId([
            'public_id' => Str::uuid(),
            'tenant_id' => $tenant->id,
            'organization_type_id' => $organizationTypeId,
            'name' => 'SV Muster',
            'slug' => 'sv-muster',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $memberTypeId = DB::table('member_types')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Fördermitglied',
            'code' => 'FOERDER',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $csv = "Mitgliedsnummer;Vorname;Nachname;E-Mail;Geburtsdatum;Status;Eintritt;Organisation;Mitgliedsart\nF-100;Frida;Foerder;frida@example.test;12.03.1990;Aktiv;01.09.2026;SV Muster;Fördermitglied\n";

        $this->actingAs($user)->post(route('members.import'), [
            'file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        ])->assertRedirect();

        $member = Member::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('member_number', 'F-100')->firstOrFail();
        $this->assertSame('2026-09-01', $member->joined_at?->format('Y-m-d'));
        $this->assertDatabaseHas('people', ['tenant_id' => $tenant->id, 'id' => $member->person_id, 'birth_date' => '1990-03-12']);
        $this->assertDatabaseHas('memberships', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'organization_unit_id' => $organizationId,
            'member_type_id' => $memberTypeId,
            'membership_type' => 'Fördermitglied',
        ]);
    }

    private function tenantUser(string $name = 'Testverein', string $slug = 'testverein'): array
    {
        $tenant = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => $name, 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);

        return [$tenant, $user];
    }
}
