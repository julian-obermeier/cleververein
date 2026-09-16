<?php

namespace App\Services\Governance;

use App\Models\GovernanceCommittee;
use App\Models\GovernanceMeeting;
use App\Models\GovernanceMeetingParticipant;
use App\Models\GovernanceMotion;
use App\Models\GovernanceResolution;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GovernanceService
{
    public function __construct(private TenantContext $tenant) {}

    public function seedCommitteeParticipants(GovernanceMeeting $meeting, GovernanceCommittee $committee): void
    {
        $onDate = $meeting->starts_at->toDateString();
        $members = $committee->members()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $onDate))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $onDate))
            ->get();

        foreach ($members as $committeeMember) {
            GovernanceMeetingParticipant::query()->firstOrCreate(
                ['meeting_id' => $meeting->id, 'member_id' => $committeeMember->member_id],
                [
                    'participant_role' => $committeeMember->is_chair ? 'chair' : 'participant',
                    'attendance_status' => 'invited',
                    'has_voting_right' => $committeeMember->has_voting_right,
                ],
            );
        }
    }

    public function recalculateQuorum(GovernanceMeeting $meeting): GovernanceMeeting
    {
        if ($meeting->quorum_required === null) {
            if ($meeting->quorum_met !== null) {
                $meeting->update(['quorum_met' => null]);
            }

            return $meeting->fresh();
        }

        $presentVoting = $meeting->participants()
            ->where('attendance_status', 'present')
            ->where('has_voting_right', true)
            ->count();
        $meeting->update(['quorum_met' => $presentVoting >= $meeting->quorum_required]);

        return $meeting->fresh();
    }

    public function createMotion(GovernanceMeeting $meeting, array $data): GovernanceMotion
    {
        return DB::transaction(function () use ($meeting, $data): GovernanceMotion {
            $year = $meeting->starts_at->year;
            $number = $this->nextSequence('motion', $year);

            return GovernanceMotion::query()->create([
                'public_id' => Str::uuid(),
                'meeting_id' => $meeting->id,
                'agenda_item_id' => $data['agenda_item_id'] ?? null,
                'organization_unit_id' => $meeting->organization_unit_id,
                'proposer_member_id' => $data['proposer_member_id'] ?? null,
                'motion_number' => sprintf('AN-%d-%06d', $year, $number),
                'title' => $data['title'],
                'motion_text' => $data['motion_text'],
                'rationale' => $data['rationale'] ?? null,
                'proposer_name' => $data['proposer_name'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        });
    }

    public function createResolution(GovernanceMeeting $meeting, array $data, int $userId): GovernanceResolution
    {
        return DB::transaction(function () use ($meeting, $data, $userId): GovernanceResolution {
            $year = $meeting->starts_at->year;
            $number = $this->nextSequence('resolution', $year);
            $resolution = GovernanceResolution::query()->create([
                'public_id' => Str::uuid(),
                'meeting_id' => $meeting->id,
                'agenda_item_id' => $data['agenda_item_id'] ?? null,
                'motion_id' => $data['motion_id'] ?? null,
                'organization_unit_id' => $meeting->organization_unit_id,
                'resolution_number' => sprintf('BE-%d-%06d', $year, $number),
                'title' => $data['title'],
                'resolution_text' => $data['resolution_text'],
                'decision_status' => $data['decision_status'],
                'voting_method' => $data['voting_method'],
                'votes_yes' => $data['votes_yes'] ?? 0,
                'votes_no' => $data['votes_no'] ?? 0,
                'votes_abstain' => $data['votes_abstain'] ?? 0,
                'votes_invalid' => $data['votes_invalid'] ?? 0,
                'effective_date' => $data['effective_date'] ?? null,
                'created_by' => $userId,
            ]);

            if ($resolution->motion_id && in_array($resolution->decision_status, ['passed', 'rejected'], true)) {
                GovernanceMotion::query()->whereKey($resolution->motion_id)->update([
                    'status' => $resolution->decision_status === 'passed' ? 'accepted' : 'rejected',
                ]);
            }

            return $resolution->fresh(['motion', 'agendaItem']);
        });
    }

    private function nextSequence(string $key, int $year): int
    {
        DB::table('governance_sequences')->insertOrIgnore([
            'tenant_id' => $this->tenant->id(),
            'sequence_key' => $key,
            'year' => $year,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('governance_sequences')
            ->where('tenant_id', $this->tenant->id())
            ->where('sequence_key', $key)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $number = (int) $sequence->next_value;
        DB::table('governance_sequences')->where('id', $sequence->id)->update([
            'next_value' => $number + 1,
            'updated_at' => now(),
        ]);

        return $number;
    }
}
