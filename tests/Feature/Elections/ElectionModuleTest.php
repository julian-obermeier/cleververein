<?php

namespace Tests\Feature\Elections;

use App\Models\DelegateMandate;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionOffice;
use App\Models\ElectionRound;
use App\Models\ElectionVoter;
use App\Models\FunctionAssignment;
use App\Models\FunctionDefinition;
use App\Models\Member;
use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Elections\ElectionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ElectionModuleTest extends TestCase
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

    public function test_super_admin_can_open_election_workspace(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('elections.index'))
            ->assertOk()
            ->assertSeeText('Wahlen & Delegiertenverwaltung');
    }

    public function test_delegate_seed_combines_multiple_mandates_for_same_member(): void
    {
        [, $user] = $this->tenantUser();
        [$represented, $receiving] = $this->organizations();
        $member = $this->member('M-DEL-001', 'Dana');

        foreach ([1.5, 2.0] as $index => $weight) {
            DelegateMandate::query()->create([
                'public_id' => Str::uuid(),
                'member_id' => $member->id,
                'represented_organization_unit_id' => $represented->id,
                'receiving_organization_unit_id' => $receiving->id,
                'mandate_number' => 'DEL-2026-00000'.($index + 1),
                'voting_weight' => $weight,
                'status' => 'active',
                'starts_at' => '2026-01-01',
            ]);
        }

        $election = $this->election($user, 'delegates', $receiving->id);
        $created = app(ElectionService::class)->seedVoters($election);

        $this->assertSame(1, $created);
        $this->assertDatabaseCount('election_voters', 1);
        $voter = ElectionVoter::query()->firstOrFail();
        $this->assertSame($member->id, $voter->member_id);
        $this->assertSame('3.500', $voter->voting_weight);
        $this->assertNull($voter->delegate_mandate_id);
    }

    public function test_tie_requires_runoff_instead_of_choosing_a_winner(): void
    {
        [, $user] = $this->tenantUser();
        $voter = $this->member('M-VOT-001', 'Vera');
        $first = $this->member('M-CAN-001', 'Anna');
        $second = $this->member('M-CAN-002', 'Ben');
        $election = $this->election($user);
        $election->update(['status' => 'open']);
        $office = $this->office($election, 1, 'highest_votes');
        $candidateA = $this->candidate($office, $first);
        $candidateB = $this->candidate($office, $second);
        $election->voters()->create(['member_id' => $voter->id, 'source' => 'manual', 'voting_weight' => 2, 'status' => 'present', 'checked_in_at' => now()]);

        $round = app(ElectionService::class)->createRound($office);
        $round = app(ElectionService::class)->finalizeRound($round, [$candidateA->id => 1, $candidateB->id => 1], 0, 0, $user->id);

        $this->assertSame('runoff', $round->result_status);
        $this->assertSame(0, $round->results->where('is_elected', true)->count());
        $this->assertSame('accepted', $candidateA->fresh()->status);
        $this->assertSame('accepted', $candidateB->fresh()->status);
    }

    public function test_multi_seat_election_keeps_clear_winner_across_runoff(): void
    {
        [, $user] = $this->tenantUser();
        $voter = $this->member('M-VOT-002', 'Wahlperson');
        $members = collect([
            $this->member('M-CAN-011', 'A'),
            $this->member('M-CAN-012', 'B'),
            $this->member('M-CAN-013', 'C'),
        ]);
        $election = $this->election($user);
        $election->update(['status' => 'open']);
        $office = $this->office($election, 2, 'highest_votes');
        $candidates = $members->map(fn (Member $member) => $this->candidate($office, $member));
        $election->voters()->create(['member_id' => $voter->id, 'source' => 'manual', 'voting_weight' => 4, 'status' => 'present', 'checked_in_at' => now()]);

        $roundOne = app(ElectionService::class)->createRound($office);
        $roundOne = app(ElectionService::class)->finalizeRound($roundOne, [
            $candidates[0]->id => 2,
            $candidates[1]->id => 1,
            $candidates[2]->id => 1,
        ], 0, 0, $user->id);

        $this->assertSame('runoff', $roundOne->result_status);
        $this->assertSame('elected', $candidates[0]->fresh()->status);
        $this->assertSame('accepted', $candidates[1]->fresh()->status);
        $this->assertSame('accepted', $candidates[2]->fresh()->status);

        $roundTwo = app(ElectionService::class)->createRound($office);
        $roundTwo = app(ElectionService::class)->finalizeRound($roundTwo, [
            $candidates[1]->id => 2,
            $candidates[2]->id => 0,
        ], 0, 0, $user->id);

        $this->assertSame('decided', $roundTwo->result_status);
        $this->assertSame('elected', $candidates[1]->fresh()->status);
        $this->assertSame(2, $office->candidates()->where('status', 'elected')->count());
    }

    public function test_finalization_creates_private_protocol_and_function_assignment(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        [$organization] = $this->organizations();
        $voter = $this->member('M-VOT-003', 'Voter');
        $winner = $this->member('M-WIN-001', 'Gewinner');
        $function = FunctionDefinition::query()->create([
            'name' => 'Vorsitz',
            'code' => 'chair',
            'category' => 'Vorstand',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $election = $this->election($user, 'manual', $organization->id);
        $election->update(['status' => 'open']);
        $office = $this->office($election, 1, 'highest_votes');
        $office->update([
            'function_definition_id' => $function->id,
            'sync_function_assignments' => true,
            'term_starts_at' => '2026-10-01',
            'term_ends_at' => '2028-09-30',
        ]);
        $candidate = $this->candidate($office, $winner);
        $election->voters()->create(['member_id' => $voter->id, 'source' => 'manual', 'voting_weight' => 1, 'status' => 'present', 'checked_in_at' => now()]);

        $round = app(ElectionService::class)->createRound($office);
        app(ElectionService::class)->finalizeRound($round, [$candidate->id => 1], 0, 0, $user->id);
        $protocol = app(ElectionService::class)->finalizeElection($election, $user->id);

        $this->assertSame('finalized', $election->fresh()->status);
        $this->assertDatabaseHas('function_assignments', [
            'member_id' => $winner->id,
            'function_definition_id' => $function->id,
            'organization_unit_id' => $organization->id,
            'starts_at' => '2026-10-01 00:00:00',
            'ends_at' => '2028-09-30 00:00:00',
        ]);
        Storage::disk('local')->assertExists($protocol->path);
        $this->assertGreaterThan(100, $protocol->size);
        $this->assertSame(1, $protocol->version);
    }

    public function test_election_route_binding_is_tenant_isolated(): void
    {
        [$tenantA, $userA] = $this->tenantUser('wahl-a');
        $this->election($userA);

        app(TenantContext::class)->clear();
        [, $userB] = $this->tenantUser('wahl-b');
        $foreign = $this->election($userB);

        app(TenantContext::class)->set($tenantA);
        $this->actingAs($userA)->get(route('elections.show', $foreign))->assertNotFound();
    }

    private function election(User $user, string $basis = 'manual', ?int $organizationId = null): Election
    {
        return Election::query()->create([
            'public_id' => Str::uuid(),
            'organization_unit_id' => $organizationId,
            'title' => 'Vorstandswahl 2026',
            'election_date' => '2026-10-15',
            'status' => 'draft',
            'voter_basis' => $basis,
            'allow_proxies' => false,
            'created_by' => $user->id,
        ]);
    }

    private function office(Election $election, int $seats, string $majority): ElectionOffice
    {
        return $election->offices()->create([
            'name' => 'Vorsitz',
            'seats' => $seats,
            'voting_method' => 'secret',
            'majority_type' => $majority,
            'majority_basis' => 'valid_votes',
            'max_rounds' => 3,
            'allow_abstention' => true,
            'position' => 10,
        ]);
    }

    private function candidate(ElectionOffice $office, Member $member): ElectionCandidate
    {
        return $office->candidates()->create([
            'member_id' => $member->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    private function member(string $number, string $firstName): Member
    {
        $person = Person::factory()->create([
            'first_name' => $firstName,
            'last_name' => 'Test',
            'email' => strtolower($number).'@example.test',
        ]);

        return Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => $number,
            'status' => 'active',
            'joined_at' => '2025-01-01',
        ]);
    }

    private function organizations(): array
    {
        $type = OrganizationType::query()->firstOrCreate([
            'slug' => 'verein',
        ], [
            'name' => 'Verein',
            'level_rank' => 10,
            'is_system' => false,
        ]);
        $first = OrganizationUnit::query()->create([
            'public_id' => Str::uuid(),
            'organization_type_id' => $type->id,
            'name' => 'Ortsverein A',
            'slug' => 'ortsverein-a-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);
        $second = OrganizationUnit::query()->create([
            'public_id' => Str::uuid(),
            'organization_type_id' => $type->id,
            'name' => 'Kreisverband',
            'slug' => 'kreisverband-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        return [$first, $second];
    }

    private function tenantUser(string $slug = 'wahlverein'): array
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
