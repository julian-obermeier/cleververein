<?php

namespace App\Http\Controllers;

use App\Models\GovernanceAgendaItem;
use App\Models\GovernanceCommittee;
use App\Models\GovernanceCommitteeMember;
use App\Models\GovernanceMeeting;
use App\Models\GovernanceMeetingParticipant;
use App\Models\GovernanceMotion;
use App\Models\GovernanceResolution;
use App\Models\GovernanceTask;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Governance\GovernanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GovernanceController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private GovernanceService $governance,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize($request, 'governance.view');

        $committees = GovernanceCommittee::query()
            ->with('organizationUnit')
            ->withCount(['members' => fn ($query) => $query->where('status', 'active')])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
        $meetings = GovernanceMeeting::query()
            ->with(['committee', 'organizationUnit'])
            ->withCount('participants')
            ->orderByDesc('starts_at')
            ->limit(30)
            ->get();
        $tasks = GovernanceTask::query()
            ->with(['assignedMember.person', 'resolution'])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(12)
            ->get();

        return view('governance.index', [
            'committees' => $committees,
            'meetings' => $meetings,
            'tasks' => $tasks,
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'canManage' => $this->can($request, 'governance.manage'),
        ]);
    }

    public function storeCommittee(Request $request): RedirectResponse
    {
        $this->authorize($request, 'governance.manage');
        $data = $request->validate([
            'organization_unit_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'committee_type' => ['required', Rule::in(['board', 'committee', 'assembly', 'working_group', 'advisory', 'other'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $organization = $this->organization($data['organization_unit_id'] ?? null);
        $this->authorize($request, 'governance.manage', $organization?->id);

        $committee = GovernanceCommittee::query()->create([
            'public_id' => Str::uuid(),
            'organization_unit_id' => $organization?->id,
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'committee_type' => $data['committee_type'],
            'description' => $data['description'] ?? null,
            'status' => 'active',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);
        $this->audit->record('governance.committee_created', $committee, new: $committee->only(['name', 'committee_type', 'organization_unit_id']));

        return redirect()->route('governance.committees.show', $committee)->with('success', 'Gremium wurde angelegt.');
    }

    public function showCommittee(Request $request, GovernanceCommittee $committee): View
    {
        $this->authorize($request, 'governance.view', $committee->organization_unit_id);
        $committee->load([
            'organizationUnit',
            'members' => fn ($query) => $query->with('member.person')->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderByDesc('is_chair')->orderBy('id'),
            'meetings' => fn ($query) => $query->orderByDesc('starts_at')->limit(30),
        ]);

        return view('governance.committee', [
            'committee' => $committee,
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->get(),
            'canManage' => $this->can($request, 'governance.manage', $committee->organization_unit_id),
        ]);
    }

    public function addCommitteeMember(Request $request, GovernanceCommittee $committee): RedirectResponse
    {
        $this->authorize($request, 'governance.manage', $committee->organization_unit_id);
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'role_name' => ['nullable', 'string', 'max:120'],
            'is_chair' => ['nullable', 'boolean'],
            'has_voting_right' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $member = Member::query()->whereKey($data['member_id'])->firstOrFail();
        $exists = $committee->members()->where('member_id', $member->id)->where('status', 'active')->exists();
        if ($exists) {
            throw ValidationException::withMessages(['member_id' => 'Dieses Mitglied ist bereits aktiv im Gremium.']);
        }

        $assignment = $committee->members()->create([
            'member_id' => $member->id,
            'role_name' => $data['role_name'] ?? null,
            'is_chair' => (bool) ($data['is_chair'] ?? false),
            'has_voting_right' => (bool) ($data['has_voting_right'] ?? false),
            'status' => 'active',
            'starts_at' => $data['starts_at'] ?? now()->toDateString(),
            'ends_at' => $data['ends_at'] ?? null,
        ]);
        $this->audit->record('governance.committee_member_added', $assignment, new: ['committee_id' => $committee->id, 'member_id' => $member->id]);

        return back()->with('success', 'Gremienmitglied wurde hinzugefügt.');
    }

    public function endCommitteeMember(Request $request, GovernanceCommittee $committee, GovernanceCommitteeMember $assignment): RedirectResponse
    {
        $this->authorize($request, 'governance.manage', $committee->organization_unit_id);
        abort_unless($assignment->committee_id === $committee->id, 404);
        $old = $assignment->only(['status', 'ends_at']);
        $assignment->update(['status' => 'inactive', 'ends_at' => $assignment->ends_at ?: now()->toDateString()]);
        $this->audit->record('governance.committee_member_ended', $assignment, old: $old, new: $assignment->only(['status', 'ends_at']));

        return back()->with('success', 'Gremienzugehörigkeit wurde beendet.');
    }

    public function storeMeeting(Request $request): RedirectResponse
    {
        $this->authorize($request, 'governance.manage');
        $data = $request->validate([
            'committee_id' => ['nullable', 'integer'],
            'organization_unit_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'meeting_type' => ['required', Rule::in(['meeting', 'board_meeting', 'general_assembly', 'delegate_assembly', 'working_session', 'other'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:220'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'quorum_required' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'seed_committee_members' => ['nullable', 'boolean'],
        ]);

        $committee = isset($data['committee_id']) ? GovernanceCommittee::query()->whereKey($data['committee_id'])->firstOrFail() : null;
        $organization = $committee?->organizationUnit ?: $this->organization($data['organization_unit_id'] ?? null);
        $this->authorize($request, 'governance.manage', $organization?->id);

        $meeting = GovernanceMeeting::query()->create([
            'public_id' => Str::uuid(),
            'committee_id' => $committee?->id,
            'organization_unit_id' => $organization?->id,
            'title' => $data['title'],
            'meeting_type' => $data['meeting_type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'online_url' => $data['online_url'] ?? null,
            'status' => 'planned',
            'quorum_required' => $data['quorum_required'] ?? null,
            'minutes_status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        if ($committee && ($data['seed_committee_members'] ?? false)) {
            $this->governance->seedCommitteeParticipants($meeting, $committee);
        }
        $this->audit->record('governance.meeting_created', $meeting, new: $meeting->only(['title', 'starts_at', 'committee_id', 'organization_unit_id']));

        return redirect()->route('governance.meetings.show', $meeting)->with('success', 'Sitzung wurde angelegt.');
    }

    public function showMeeting(Request $request, GovernanceMeeting $meeting): View
    {
        $this->authorize($request, 'governance.view', $meeting->organization_unit_id);
        $meeting->load([
            'committee', 'organizationUnit',
            'participants.member.person',
            'agendaItems.motions.proposer.person',
            'agendaItems.resolutions',
            'motions.proposer.person',
            'resolutions.tasks.assignedMember.person',
            'tasks.assignedMember.person',
        ]);

        return view('governance.meeting', [
            'meeting' => $meeting,
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->get(),
            'canManage' => $this->can($request, 'governance.manage', $meeting->organization_unit_id),
            'canDecide' => $this->can($request, 'governance.decisions', $meeting->organization_unit_id),
            'canMinutes' => $this->can($request, 'governance.minutes', $meeting->organization_unit_id),
        ]);
    }

    public function updateMeeting(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.manage', $meeting->organization_unit_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['planned', 'in_progress', 'closed', 'cancelled'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:220'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'quorum_required' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $old = $meeting->only(array_keys($data));
        $meeting->update($data);
        $this->governance->recalculateQuorum($meeting);
        $this->audit->record('governance.meeting_updated', $meeting, old: $old, new: $meeting->fresh()->only(array_keys($data)));

        return back()->with('success', 'Sitzungsdaten wurden gespeichert.');
    }

    public function addParticipant(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.manage', $meeting->organization_unit_id);
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'external_name' => ['nullable', 'string', 'max:180'],
            'participant_role' => ['required', Rule::in(['chair', 'participant', 'guest', 'minute_taker'])],
            'attendance_status' => ['required', Rule::in(['invited', 'present', 'absent', 'excused'])],
            'has_voting_right' => ['nullable', 'boolean'],
        ]);
        if (! ($data['member_id'] ?? null) && ! filled($data['external_name'] ?? null)) {
            throw ValidationException::withMessages(['member_id' => 'Bitte Mitglied oder externen Namen angeben.']);
        }
        $member = isset($data['member_id']) ? Member::query()->whereKey($data['member_id'])->firstOrFail() : null;
        if ($member && $meeting->participants()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['member_id' => 'Dieses Mitglied ist bereits als Teilnehmer erfasst.']);
        }

        $participant = $meeting->participants()->create([
            'member_id' => $member?->id,
            'external_name' => $member ? null : $data['external_name'],
            'participant_role' => $data['participant_role'],
            'attendance_status' => $data['attendance_status'],
            'has_voting_right' => (bool) ($data['has_voting_right'] ?? false),
        ]);
        $this->governance->recalculateQuorum($meeting);
        $this->audit->record('governance.participant_added', $participant, new: $participant->only(['meeting_id', 'member_id', 'attendance_status', 'has_voting_right']));

        return back()->with('success', 'Teilnehmer wurde hinzugefügt.');
    }

    public function updateParticipant(Request $request, GovernanceMeeting $meeting, GovernanceMeetingParticipant $participant): RedirectResponse
    {
        $this->authorize($request, 'governance.manage', $meeting->organization_unit_id);
        abort_unless($participant->meeting_id === $meeting->id, 404);
        $data = $request->validate([
            'participant_role' => ['required', Rule::in(['chair', 'participant', 'guest', 'minute_taker'])],
            'attendance_status' => ['required', Rule::in(['invited', 'present', 'absent', 'excused'])],
            'has_voting_right' => ['nullable', 'boolean'],
        ]);
        $old = $participant->only(['participant_role', 'attendance_status', 'has_voting_right']);
        $participant->update([...$data, 'has_voting_right' => (bool) ($data['has_voting_right'] ?? false)]);
        $this->governance->recalculateQuorum($meeting);
        $this->audit->record('governance.participant_updated', $participant, old: $old, new: $participant->only(['participant_role', 'attendance_status', 'has_voting_right']));

        return back()->with('success', 'Teilnahmestatus wurde aktualisiert.');
    }

    public function storeAgendaItem(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.manage', $meeting->organization_unit_id);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'item_number' => ['nullable', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:10000'],
            'item_type' => ['required', Rule::in(['information', 'discussion', 'motion', 'election', 'other'])],
            'planned_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);
        $parent = isset($data['parent_id']) ? GovernanceAgendaItem::query()->whereKey($data['parent_id'])->firstOrFail() : null;
        abort_if($parent && $parent->meeting_id !== $meeting->id, 422, 'Unterpunkt gehört zu einer anderen Sitzung.');
        $position = ((int) $meeting->agendaItems()->max('position')) + 10;
        $item = $meeting->agendaItems()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'item_number' => $data['item_number'] ?? 'TOP '.((int) $meeting->agendaItems()->count() + 1),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'item_type' => $data['item_type'],
            'planned_minutes' => $data['planned_minutes'] ?? null,
            'status' => 'open',
        ]);
        $this->audit->record('governance.agenda_item_created', $item, new: $item->only(['meeting_id', 'item_number', 'title', 'item_type']));

        return back()->with('success', 'Tagesordnungspunkt wurde ergänzt.');
    }

    public function storeMotion(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.decisions', $meeting->organization_unit_id);
        $data = $request->validate([
            'agenda_item_id' => ['nullable', 'integer'],
            'proposer_member_id' => ['nullable', 'integer'],
            'proposer_name' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:220'],
            'motion_text' => ['required', 'string', 'max:30000'],
            'rationale' => ['nullable', 'string', 'max:10000'],
        ]);
        if (isset($data['agenda_item_id'])) {
            $agenda = GovernanceAgendaItem::query()->whereKey($data['agenda_item_id'])->firstOrFail();
            abort_unless($agenda->meeting_id === $meeting->id, 404);
        }
        if (isset($data['proposer_member_id'])) {
            Member::query()->whereKey($data['proposer_member_id'])->firstOrFail();
        }
        $motion = $this->governance->createMotion($meeting, $data);
        $this->audit->record('governance.motion_submitted', $motion, new: $motion->only(['motion_number', 'title', 'meeting_id']));

        return back()->with('success', "Antrag {$motion->motion_number} wurde erfasst.");
    }

    public function storeResolution(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.decisions', $meeting->organization_unit_id);
        $data = $request->validate([
            'agenda_item_id' => ['nullable', 'integer'],
            'motion_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'resolution_text' => ['required', 'string', 'max:30000'],
            'decision_status' => ['required', Rule::in(['passed', 'rejected', 'recorded'])],
            'voting_method' => ['required', Rule::in(['open', 'secret', 'unanimous', 'acclamation'])],
            'votes_yes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'votes_no' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'votes_abstain' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'votes_invalid' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'effective_date' => ['nullable', 'date'],
        ]);
        if (isset($data['agenda_item_id'])) {
            $agenda = GovernanceAgendaItem::query()->whereKey($data['agenda_item_id'])->firstOrFail();
            abort_unless($agenda->meeting_id === $meeting->id, 404);
        }
        if (isset($data['motion_id'])) {
            $motion = GovernanceMotion::query()->whereKey($data['motion_id'])->firstOrFail();
            abort_unless($motion->meeting_id === $meeting->id, 404);
        }
        $resolution = $this->governance->createResolution($meeting, $data, $request->user()->id);
        $this->audit->record('governance.resolution_created', $resolution, new: $resolution->only(['resolution_number', 'title', 'decision_status', 'voting_method', 'votes_yes', 'votes_no', 'votes_abstain']));

        return back()->with('success', "Beschluss {$resolution->resolution_number} wurde gespeichert.");
    }

    public function saveMinutes(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.minutes', $meeting->organization_unit_id);
        abort_if($meeting->minutes_status === 'approved', 422, 'Ein freigegebenes Protokoll kann nicht still überschrieben werden.');
        $data = $request->validate([
            'minutes_text' => ['required', 'string', 'max:100000'],
            'minutes_status' => ['required', Rule::in(['draft', 'review'])],
        ]);
        $old = $meeting->only(['minutes_status', 'minutes_text']);
        $meeting->update($data);
        $this->audit->record('governance.minutes_saved', $meeting, old: ['minutes_status' => $old['minutes_status']], new: ['minutes_status' => $meeting->minutes_status]);

        return back()->with('success', 'Protokoll wurde gespeichert.');
    }

    public function approveMinutes(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.minutes', $meeting->organization_unit_id);
        if (! filled($meeting->minutes_text)) {
            throw ValidationException::withMessages(['minutes_text' => 'Vor der Freigabe muss ein Protokolltext vorhanden sein.']);
        }
        $meeting->update([
            'minutes_status' => 'approved',
            'minutes_approved_at' => now(),
            'minutes_approved_by' => $request->user()->id,
        ]);
        $this->audit->record('governance.minutes_approved', $meeting, new: ['minutes_status' => 'approved', 'minutes_approved_at' => $meeting->minutes_approved_at]);

        return back()->with('success', 'Protokoll wurde freigegeben.');
    }

    public function storeTask(Request $request, GovernanceMeeting $meeting): RedirectResponse
    {
        $this->authorize($request, 'governance.decisions', $meeting->organization_unit_id);
        $data = $request->validate([
            'resolution_id' => ['nullable', 'integer'],
            'assigned_member_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
        ]);
        if (isset($data['resolution_id'])) {
            $resolution = GovernanceResolution::query()->whereKey($data['resolution_id'])->firstOrFail();
            abort_unless($resolution->meeting_id === $meeting->id, 404);
        }
        if (isset($data['assigned_member_id'])) {
            Member::query()->whereKey($data['assigned_member_id'])->firstOrFail();
        }
        $task = GovernanceTask::query()->create([
            'public_id' => Str::uuid(),
            'meeting_id' => $meeting->id,
            'resolution_id' => $data['resolution_id'] ?? null,
            'organization_unit_id' => $meeting->organization_unit_id,
            'assigned_member_id' => $data['assigned_member_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'status' => 'open',
            'due_at' => $data['due_at'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->record('governance.task_created', $task, new: $task->only(['title', 'assigned_member_id', 'due_at', 'resolution_id']));

        return back()->with('success', 'Aufgabe wurde angelegt.');
    }

    public function updateTask(Request $request, GovernanceTask $task): RedirectResponse
    {
        $this->authorize($request, 'governance.decisions', $task->organization_unit_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'done', 'cancelled'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
        ]);
        $old = $task->only(['status', 'priority', 'due_at']);
        $task->update([
            ...$data,
            'completed_at' => $data['status'] === 'done' ? ($task->completed_at ?: now()) : null,
        ]);
        $this->audit->record('governance.task_updated', $task, old: $old, new: $task->only(['status', 'priority', 'due_at', 'completed_at']));

        return back()->with('success', 'Aufgabe wurde aktualisiert.');
    }

    private function organization(?int $id): ?OrganizationUnit
    {
        return $id ? OrganizationUnit::query()->whereKey($id)->firstOrFail() : null;
    }

    private function can(Request $request, string $permission, ?int $organizationUnitId = null): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission, $organizationUnitId);
    }

    private function authorize(Request $request, string $permission, ?int $organizationUnitId = null): void
    {
        abort_unless($this->can($request, $permission, $organizationUnitId), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
