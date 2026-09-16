<?php

namespace Tests\Feature\Governance;

use App\Models\GovernanceMeeting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Governance\GovernanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GovernanceEdgeCasesTest extends TestCase
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

    public function test_recorded_resolution_does_not_reject_linked_motion(): void
    {
        $user = $this->tenantUser();
        $meeting = $this->meeting($user);
        $service = app(GovernanceService::class);
        $motion = $service->createMotion($meeting, [
            'title' => 'Kenntnisnahme',
            'motion_text' => 'Der Bericht wird zur Kenntnis genommen.',
        ]);

        $service->createResolution($meeting, [
            'motion_id' => $motion->id,
            'title' => 'Feststellung',
            'resolution_text' => 'Der Bericht wurde zur Kenntnis genommen.',
            'decision_status' => 'recorded',
            'voting_method' => 'open',
        ], $user->id);

        $this->assertSame('submitted', $motion->fresh()->status);
    }

    public function test_removing_quorum_requirement_clears_old_quorum_result(): void
    {
        $user = $this->tenantUser();
        $meeting = $this->meeting($user);
        $meeting->update(['quorum_required' => 1, 'quorum_met' => true]);
        $meeting->update(['quorum_required' => null]);

        app(GovernanceService::class)->recalculateQuorum($meeting->fresh());

        $this->assertNull($meeting->fresh()->quorum_met);
    }

    private function meeting(User $user): GovernanceMeeting
    {
        return GovernanceMeeting::query()->create([
            'public_id' => Str::uuid(),
            'title' => 'Edge-Case-Sitzung',
            'meeting_type' => 'meeting',
            'starts_at' => '2026-09-20 18:00:00',
            'status' => 'planned',
            'minutes_status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    private function tenantUser(): User
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Governance Test',
            'slug' => 'governance-test',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return $user;
    }
}
