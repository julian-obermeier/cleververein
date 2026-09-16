<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionOffice;
use App\Models\ElectionProxy;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ElectionSafeguardsTest extends TestCase
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

    public function test_proxy_weight_cannot_exceed_grantor_voting_weight(): void
    {
        [, $user] = $this->tenantUser();
        $grantor = $this->member('M-PROXY-001', 'Geber');
        $holder = $this->member('M-PROXY-002', 'Nehmer');
        $election = $this->election($user);
        $election->voters()->create(['member_id' => $grantor->id, 'source' => 'manual', 'voting_weight' => 1, 'status' => 'absent']);
        $election->voters()->create(['member_id' => $holder->id, 'source' => 'manual', 'voting_weight' => 1, 'status' => 'present']);

        $this->expectException(ValidationException::class);

        ElectionProxy::query()->create([
            'election_id' => $election->id,
            'grantor_member_id' => $grantor->id,
            'proxy_member_id' => $holder->id,
            'voting_weight' => 2,
            'status' => 'active',
            'issued_at' => now(),
        ]);
    }

    public function test_elected_candidate_cannot_be_downgraded(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-ELECTED-001', 'Gewählt');
        $election = $this->election($user);
        $office = ElectionOffice::query()->create([
            'election_id' => $election->id,
            'name' => 'Vorsitz',
            'seats' => 1,
            'voting_method' => 'secret',
            'majority_type' => 'highest_votes',
            'majority_basis' => 'valid_votes',
            'max_rounds' => 3,
            'allow_abstention' => true,
            'position' => 10,
        ]);
        $candidate = ElectionCandidate::query()->create([
            'election_office_id' => $office->id,
            'member_id' => $member->id,
            'status' => 'elected',
            'accepted_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $candidate->update(['status' => 'withdrawn']);
    }

    private function election(User $user): Election
    {
        return Election::query()->create([
            'public_id' => Str::uuid(),
            'title' => 'Sicherheitsprüfung Wahl',
            'election_date' => '2026-10-15',
            'status' => 'draft',
            'voter_basis' => 'manual',
            'allow_proxies' => true,
            'created_by' => $user->id,
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

    private function tenantUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Wahlverein',
            'slug' => 'wahlverein-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
