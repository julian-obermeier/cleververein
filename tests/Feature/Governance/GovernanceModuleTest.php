<?php

namespace Tests\Feature\Governance;

use App\Models\GovernanceCommittee;
use App\Models\GovernanceMeeting;
use App\Models\GovernanceMotion;
use App\Models\GovernanceResolution;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GovernanceModuleTest extends TestCase
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

    public function test_committee_member_and_meeting_seed_workflow(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-GOV-001');

        $this->actingAs($user)->post(route('governance.committees.store'), [
            'name' => 'Vorstand',
            'short_name' => 'VS',
            'committee_type' => 'board',
        ])->assertRedirect();
        $committee = GovernanceCommittee::query()->firstOrFail();

        $this->actingAs($user)->post(route('governance.committees.members.store', $committee), [
            'member_id' => $member->id,
            'role_name' => 'Vorsitz',
            'is_chair' => 1,
            'has_voting_right' => 1,
            'starts_at' => '2026-01-01',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('governance.meetings.store'), [
            'committee_id' => $committee->id,
            'title' => 'Vorstandssitzung September',
            'meeting_type' => 'board_meeting',
            'starts_at' => '2026-09-20 18:00:00',
            'quorum_required' => 1,
            'seed_committee_members' => 1,
        ])->assertRedirect();

        $meeting = GovernanceMeeting::query()->firstOrFail();
        $this->assertDatabaseHas('governance_meeting_participants', [
            'meeting_id' => $meeting->id,
            'member_id' => $member->id,
            'has_voting_right' => 1,
            'attendance_status' => 'invited',
        ]);

        $participant = $meeting->participants()->firstOrFail();
        $this->actingAs($user)->put(route('governance.participants.update', [$meeting, $participant]), [
            'participant_role' => 'chair',
            'attendance_status' => 'present',
            'has_voting_right' => 1,
        ])->assertRedirect();
        $this->assertTrue((bool) $meeting->fresh()->quorum_met);
    }

    public function test_motion_resolution_task_and_minutes_workflow(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-GOV-002');
        $meeting = $this->meeting($user);

        $this->actingAs($user)->post(route('governance.agenda.store', $meeting), [
            'item_number' => 'TOP 3',
            'title' => 'Beschaffung Vereinssoftware',
            'item_type' => 'motion',
            'planned_minutes' => 15,
        ])->assertRedirect();
        $agenda = $meeting->agendaItems()->firstOrFail();

        $this->actingAs($user)->post(route('governance.motions.store', $meeting), [
            'agenda_item_id' => $agenda->id,
            'proposer_member_id' => $member->id,
            'title' => 'Einführung cleververein',
            'motion_text' => 'Die Einführung von cleververein wird beschlossen.',
            'rationale' => 'Zentrale Verwaltung aller Vereinsbereiche.',
        ])->assertRedirect();
        $motion = GovernanceMotion::query()->firstOrFail();
        $this->assertSame('AN-2026-000001', $motion->motion_number);

        $this->actingAs($user)->post(route('governance.resolutions.store', $meeting), [
            'agenda_item_id' => $agenda->id,
            'motion_id' => $motion->id,
            'title' => 'Einführung cleververein',
            'resolution_text' => 'Der Antrag wird angenommen.',
            'decision_status' => 'passed',
            'voting_method' => 'secret',
            'votes_yes' => 5,
            'votes_no' => 1,
            'votes_abstain' => 1,
            'votes_invalid' => 0,
        ])->assertRedirect();
        $resolution = GovernanceResolution::query()->firstOrFail();
        $this->assertSame('BE-2026-000001', $resolution->resolution_number);
        $this->assertSame('accepted', $motion->fresh()->status);
        $this->assertSame(5, $resolution->votes_yes);

        $this->actingAs($user)->post(route('governance.tasks.store', $meeting), [
            'resolution_id' => $resolution->id,
            'assigned_member_id' => $member->id,
            'title' => 'Einführung vorbereiten',
            'priority' => 'high',
            'due_at' => '2026-10-01',
        ])->assertRedirect();
        $this->assertDatabaseHas('governance_tasks', [
            'resolution_id' => $resolution->id,
            'assigned_member_id' => $member->id,
            'status' => 'open',
        ]);

        $this->actingAs($user)->put(route('governance.minutes.update', $meeting), [
            'minutes_text' => 'Die Sitzung wurde ordnungsgemäß durchgeführt. TOP 3 wurde beschlossen.',
            'minutes_status' => 'review',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('governance.minutes.approve', $meeting))->assertRedirect();
        $this->assertSame('approved', $meeting->fresh()->minutes_status);

        $this->actingAs($user)->put(route('governance.minutes.update', $meeting), [
            'minutes_text' => 'Nachträglich verändert',
            'minutes_status' => 'draft',
        ])->assertStatus(422);
        $this->assertStringNotContainsString('Nachträglich verändert', (string) $meeting->fresh()->minutes_text);
    }

    public function test_resolution_register_is_tenant_isolated(): void
    {
        [$tenantA, $userA] = $this->tenantUser('verein-a');
        $meetingA = $this->meeting($userA);
        $this->actingAs($userA)->post(route('governance.resolutions.store', $meetingA), [
            'title' => 'Beschluss A',
            'resolution_text' => 'Nur Mandant A',
            'decision_status' => 'recorded',
            'voting_method' => 'open',
        ])->assertRedirect();

        app(TenantContext::class)->clear();
        [$tenantB, $userB] = $this->tenantUser('verein-b');
        $meetingB = $this->meeting($userB);
        $this->actingAs($userB)->post(route('governance.resolutions.store', $meetingB), [
            'title' => 'Beschluss B',
            'resolution_text' => 'Nur Mandant B',
            'decision_status' => 'recorded',
            'voting_method' => 'open',
        ])->assertRedirect();

        app(TenantContext::class)->set($tenantA);
        $this->actingAs($userA)->get(route('governance.resolutions.index'))
            ->assertOk()
            ->assertSee('Beschluss A')
            ->assertDontSee('Beschluss B');

        $this->assertNotSame($tenantA->id, $tenantB->id);
    }

    private function meeting(User $user): GovernanceMeeting
    {
        return GovernanceMeeting::query()->create([
            'public_id' => Str::uuid(),
            'title' => 'Testsitzung',
            'meeting_type' => 'meeting',
            'starts_at' => '2026-09-20 18:00:00',
            'status' => 'planned',
            'minutes_status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    private function member(string $number): Member
    {
        $person = Person::factory()->create(['first_name' => 'Max', 'last_name' => 'Mustermann']);

        return Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => $number,
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
    }

    private function tenantUser(string $slug = 'testverein'): array
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
