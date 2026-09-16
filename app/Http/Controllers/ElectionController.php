<?php

namespace App\Http\Controllers;

use App\Models\DelegateMandate;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionOffice;
use App\Models\ElectionProtocol;
use App\Models\ElectionProxy;
use App\Models\ElectionRound;
use App\Models\ElectionVoter;
use App\Models\FunctionDefinition;
use App\Models\GovernanceMeeting;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Elections\ElectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ElectionController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private ElectionService $elections,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize($request, 'elections.view');

        return view('elections.index', [
            'elections' => Election::query()
                ->with(['organizationUnit', 'governanceMeeting'])
                ->withCount(['offices', 'voters'])
                ->orderByDesc('election_date')
                ->limit(50)
                ->get(),
            'mandates' => DelegateMandate::query()
                ->with(['member.person', 'representedOrganization', 'receivingOrganization'])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('starts_at')
                ->limit(30)
                ->get(),
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'meetings' => GovernanceMeeting::query()->orderByDesc('starts_at')->limit(50)->get(),
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(1000)->get(),
            'canManage' => $this->can($request, 'elections.manage'),
            'canManageDelegates' => $this->can($request, 'delegates.manage'),
        ]);
    }

    public function storeElection(Request $request): RedirectResponse
    {
        $this->authorize($request, 'elections.manage');
        $data = $request->validate([
            'organization_unit_id' => ['nullable', 'integer'],
            'governance_meeting_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'election_date' => ['required', 'date'],
            'voter_basis' => ['required', Rule::in(['members', 'delegates', 'manual'])],
            'allow_proxies' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $organization = $this->organization($data['organization_unit_id'] ?? null);
        $this->authorize($request, 'elections.manage', $organization?->id);
        $meeting = isset($data['governance_meeting_id'])
            ? GovernanceMeeting::query()->whereKey($data['governance_meeting_id'])->firstOrFail()
            : null;
        if ($meeting && $organization && $meeting->organization_unit_id && $meeting->organization_unit_id !== $organization->id) {
            throw ValidationException::withMessages(['governance_meeting_id' => 'Die Sitzung gehört zu einer anderen Gliederung.']);
        }

        $election = Election::query()->create([
            'public_id' => Str::uuid(),
            'organization_unit_id' => $organization?->id,
            'governance_meeting_id' => $meeting?->id,
            'title' => $data['title'],
            'election_date' => $data['election_date'],
            'status' => 'draft',
            'voter_basis' => $data['voter_basis'],
            'allow_proxies' => (bool) ($data['allow_proxies'] ?? false),
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->record('elections.created', $election, new: $election->only(['title', 'election_date', 'organization_unit_id', 'voter_basis']));

        return redirect()->route('elections.show', $election)->with('success', 'Wahl wurde angelegt.');
    }

    public function show(Request $request, Election $election): View
    {
        $this->authorize($request, 'elections.view', $election->organization_unit_id);
        $election->load([
            'organizationUnit', 'governanceMeeting', 'creator', 'finalizer',
            'voters.member.person', 'voters.delegateMandate.representedOrganization',
            'proxies.grantor.person', 'proxies.holder.person',
            'offices.functionDefinition',
            'offices.candidates.member.person', 'offices.candidates.nominator.person',
            'offices.rounds.results.candidate.member.person',
            'protocols.generator',
        ]);

        return view('elections.show', [
            'election' => $election,
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(1500)->get(),
            'functions' => FunctionDefinition::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'canManage' => $this->can($request, 'elections.manage', $election->organization_unit_id),
            'canConduct' => $this->can($request, 'elections.conduct', $election->organization_unit_id),
            'canFinalize' => $this->can($request, 'elections.finalize', $election->organization_unit_id),
        ]);
    }

    public function updateStatus(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_if($election->status === 'finalized', 422, 'Eine festgestellte Wahl kann nicht wieder geöffnet werden.');
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'open', 'cancelled'])]]);
        if ($data['status'] === 'open' && $election->offices()->count() === 0) {
            throw ValidationException::withMessages(['status' => 'Vor dem Öffnen muss mindestens ein zu wählendes Amt angelegt sein.']);
        }
        $old = $election->status;
        $election->update(['status' => $data['status']]);
        $this->audit->record('elections.status_updated', $election, old: ['status' => $old], new: ['status' => $election->status]);

        return back()->with('success', 'Wahlstatus wurde aktualisiert.');
    }

    public function seedVoters(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        $count = $this->elections->seedVoters($election);
        $this->audit->record('elections.voters_seeded', $election, new: ['created' => $count, 'basis' => $election->voter_basis]);

        return back()->with('success', $count.' Wahlberechtigte wurden ergänzt.');
    }

    public function storeVoter(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_if($election->status === 'finalized', 422);
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'voting_weight' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $member = Member::query()->whereKey($data['member_id'])->firstOrFail();
        if ($election->voters()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['member_id' => 'Dieses Mitglied ist bereits in der Wählerliste enthalten.']);
        }
        $voter = $election->voters()->create([
            'member_id' => $member->id,
            'source' => 'manual',
            'voting_weight' => round((float) $data['voting_weight'], 3),
            'status' => 'eligible',
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('elections.voter_added', $voter, new: $voter->only(['election_id', 'member_id', 'voting_weight']));

        return back()->with('success', 'Wahlberechtigung wurde ergänzt.');
    }

    public function updateVoter(Request $request, Election $election, ElectionVoter $voter): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_unless($voter->election_id === $election->id, 404);
        abort_if($election->status === 'finalized', 422);
        $data = $request->validate([
            'status' => ['required', Rule::in(['eligible', 'present', 'ineligible', 'absent'])],
            'voting_weight' => ['required', 'numeric', 'min:0.001', 'max:100000'],
        ]);
        $old = $voter->only(['status', 'voting_weight', 'checked_in_at']);
        $voter->update([
            'status' => $data['status'],
            'voting_weight' => round((float) $data['voting_weight'], 3),
            'checked_in_at' => $data['status'] === 'present' ? ($voter->checked_in_at ?: now()) : null,
        ]);
        $this->audit->record('elections.voter_updated', $voter, old: $old, new: $voter->only(['status', 'voting_weight', 'checked_in_at']));

        return back()->with('success', 'Wahlberechtigung wurde aktualisiert.');
    }

    public function storeProxy(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_unless($election->allow_proxies, 422, 'Vollmachten sind für diese Wahl nicht aktiviert.');
        abort_if($election->status === 'finalized', 422);
        $data = $request->validate([
            'grantor_member_id' => ['required', 'integer', 'different:proxy_member_id'],
            'proxy_member_id' => ['required', 'integer'],
            'voting_weight' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $grantor = $election->voters()->where('member_id', $data['grantor_member_id'])->first();
        $holder = $election->voters()->where('member_id', $data['proxy_member_id'])->first();
        if (! $grantor || ! $holder) {
            throw ValidationException::withMessages(['grantor_member_id' => 'Vollmachtgeber und Bevollmächtigter müssen in der Wählerliste stehen.']);
        }
        if ($grantor->status === 'ineligible' || $holder->status === 'ineligible') {
            throw ValidationException::withMessages(['grantor_member_id' => 'Für nicht wahlberechtigte Personen kann keine Vollmacht erfasst werden.']);
        }

        $proxy = ElectionProxy::query()->updateOrCreate(
            ['election_id' => $election->id, 'grantor_member_id' => $grantor->member_id],
            [
                'proxy_member_id' => $holder->member_id,
                'voting_weight' => round((float) $data['voting_weight'], 3),
                'status' => 'active',
                'issued_at' => now(),
                'revoked_at' => null,
                'notes' => $data['notes'] ?? null,
            ],
        );
        $this->audit->record('elections.proxy_saved', $proxy, new: $proxy->only(['election_id', 'grantor_member_id', 'proxy_member_id', 'voting_weight']));

        return back()->with('success', 'Vollmacht wurde gespeichert.');
    }

    public function revokeProxy(Request $request, Election $election, ElectionProxy $proxy): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_unless($proxy->election_id === $election->id, 404);
        abort_if($election->status === 'finalized', 422);
        $proxy->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->audit->record('elections.proxy_revoked', $proxy, new: ['status' => 'revoked']);

        return back()->with('success', 'Vollmacht wurde widerrufen.');
    }

    public function storeOffice(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_if($election->status === 'finalized', 422);
        $data = $request->validate([
            'function_definition_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
            'voting_method' => ['required', Rule::in(['secret', 'open', 'acclamation'])],
            'majority_type' => ['required', Rule::in(['simple', 'absolute', 'two_thirds', 'highest_votes'])],
            'majority_basis' => ['required', Rule::in(['valid_votes', 'cast_including_abstentions', 'eligible_weight'])],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'allow_abstention' => ['nullable', 'boolean'],
            'sync_function_assignments' => ['nullable', 'boolean'],
            'term_starts_at' => ['nullable', 'date'],
            'term_ends_at' => ['nullable', 'date', 'after_or_equal:term_starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $function = isset($data['function_definition_id']) ? FunctionDefinition::query()->whereKey($data['function_definition_id'])->firstOrFail() : null;
        if (($data['sync_function_assignments'] ?? false) && ! $function) {
            throw ValidationException::withMessages(['function_definition_id' => 'Für die automatische Amtsübernahme muss eine Funktion verknüpft sein.']);
        }
        $office = $election->offices()->create([
            'function_definition_id' => $function?->id,
            'name' => $data['name'],
            'seats' => $data['seats'],
            'voting_method' => $data['voting_method'],
            'majority_type' => $data['majority_type'],
            'majority_basis' => $data['majority_basis'],
            'max_rounds' => $data['max_rounds'],
            'allow_abstention' => (bool) ($data['allow_abstention'] ?? false),
            'sync_function_assignments' => (bool) ($data['sync_function_assignments'] ?? false),
            'term_starts_at' => $data['term_starts_at'] ?? null,
            'term_ends_at' => $data['term_ends_at'] ?? null,
            'position' => ((int) $election->offices()->max('position')) + 10,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('elections.office_created', $office, new: $office->only(['election_id', 'name', 'seats', 'voting_method', 'majority_type']));

        return back()->with('success', 'Wahlamt wurde angelegt.');
    }

    public function storeCandidate(Request $request, Election $election, ElectionOffice $office): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_unless($office->election_id === $election->id, 404);
        abort_if($election->status === 'finalized', 422);
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'nominated_by_member_id' => ['nullable', 'integer'],
            'accepted' => ['nullable', 'boolean'],
            'statement' => ['nullable', 'string', 'max:10000'],
        ]);
        $member = Member::query()->whereKey($data['member_id'])->firstOrFail();
        $nominator = isset($data['nominated_by_member_id']) ? Member::query()->whereKey($data['nominated_by_member_id'])->firstOrFail() : null;
        if ($office->candidates()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['member_id' => 'Diese Person kandidiert bereits für dieses Amt.']);
        }
        $candidate = $office->candidates()->create([
            'member_id' => $member->id,
            'nominated_by_member_id' => $nominator?->id,
            'status' => ($data['accepted'] ?? false) ? 'accepted' : 'nominated',
            'accepted_at' => ($data['accepted'] ?? false) ? now() : null,
            'statement' => $data['statement'] ?? null,
        ]);
        $this->audit->record('elections.candidate_added', $candidate, new: $candidate->only(['election_office_id', 'member_id', 'status']));

        return back()->with('success', 'Kandidatur wurde erfasst.');
    }

    public function updateCandidate(Request $request, Election $election, ElectionOffice $office, ElectionCandidate $candidate): RedirectResponse
    {
        $this->authorize($request, 'elections.manage', $election->organization_unit_id);
        abort_unless($office->election_id === $election->id && $candidate->election_office_id === $office->id, 404);
        abort_if($election->status === 'finalized', 422);
        $data = $request->validate(['status' => ['required', Rule::in(['nominated', 'accepted', 'withdrawn'])]]);
        $candidate->update([
            'status' => $data['status'],
            'accepted_at' => $data['status'] === 'accepted' ? ($candidate->accepted_at ?: now()) : $candidate->accepted_at,
            'withdrawn_at' => $data['status'] === 'withdrawn' ? now() : null,
        ]);
        $this->audit->record('elections.candidate_updated', $candidate, new: ['status' => $candidate->status]);

        return back()->with('success', 'Kandidatur wurde aktualisiert.');
    }

    public function startRound(Request $request, Election $election, ElectionOffice $office): RedirectResponse
    {
        $this->authorize($request, 'elections.conduct', $election->organization_unit_id);
        abort_unless($office->election_id === $election->id, 404);
        $round = $this->elections->createRound($office);
        $this->audit->record('elections.round_started', $round, new: ['office_id' => $office->id, 'round_number' => $round->round_number, 'eligible_weight' => $round->eligible_weight]);

        return back()->with('success', "Wahlgang {$round->round_number} wurde geöffnet.");
    }

    public function finalizeRound(Request $request, Election $election, ElectionOffice $office, ElectionRound $round): RedirectResponse
    {
        $this->authorize($request, 'elections.conduct', $election->organization_unit_id);
        abort_unless($office->election_id === $election->id && $round->election_office_id === $office->id, 404);
        $data = $request->validate([
            'votes' => ['required', 'array'],
            'votes.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'abstain_weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'invalid_weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);
        $round = $this->elections->finalizeRound(
            $round,
            $data['votes'],
            (float) ($data['abstain_weight'] ?? 0),
            (float) ($data['invalid_weight'] ?? 0),
            $request->user()->id,
        );
        $this->audit->record('elections.round_finalized', $round, new: $round->only(['round_number', 'cast_weight', 'abstain_weight', 'invalid_weight', 'result_status']));

        $message = match ($round->result_status) {
            'decided' => 'Wahlgang wurde ausgewertet; das Ergebnis ist entschieden.',
            'runoff' => 'Wahlgang wurde ausgewertet; ein weiterer Wahlgang ist erforderlich.',
            default => 'Wahlgang wurde ausgewertet; es liegt noch kein entscheidbares Ergebnis vor.',
        };

        return back()->with('success', $message);
    }

    public function finalizeElection(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.finalize', $election->organization_unit_id);
        $protocol = $this->elections->finalizeElection($election, $request->user()->id);
        $this->audit->record('elections.finalized', $election->fresh(), new: ['status' => 'finalized', 'protocol_id' => $protocol->id]);

        return back()->with('success', 'Wahl wurde endgültig festgestellt und das Wahlprotokoll wurde erzeugt.');
    }

    public function generateProtocol(Request $request, Election $election): RedirectResponse
    {
        $this->authorize($request, 'elections.finalize', $election->organization_unit_id);
        abort_unless($election->status === 'finalized', 422, 'Ein endgültiges Wahlprotokoll kann erst nach Feststellung erzeugt werden.');
        $protocol = $this->elections->generateProtocol($election, $request->user()->id);
        $this->audit->record('elections.protocol_generated', $protocol, new: ['election_id' => $election->id, 'version' => $protocol->version]);

        return back()->with('success', "Wahlprotokoll Version {$protocol->version} wurde erzeugt.");
    }

    public function downloadProtocol(Request $request, Election $election, ElectionProtocol $protocol): BinaryFileResponse
    {
        $this->authorize($request, 'elections.view', $election->organization_unit_id);
        abort_unless($protocol->election_id === $election->id, 404);
        abort_unless(Storage::disk($protocol->disk)->exists($protocol->path), 404);
        $this->audit->record('elections.protocol_downloaded', $protocol, new: ['version' => $protocol->version]);

        return response()->download(Storage::disk($protocol->disk)->path($protocol->path), 'Wahlprotokoll-'.Str::slug($election->title).'-v'.$protocol->version.'.pdf');
    }

    public function storeDelegate(Request $request): RedirectResponse
    {
        $this->authorize($request, 'delegates.manage');
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'represented_organization_unit_id' => ['required', 'integer'],
            'receiving_organization_unit_id' => ['nullable', 'integer'],
            'voting_weight' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $member = Member::query()->whereKey($data['member_id'])->firstOrFail();
        $represented = OrganizationUnit::query()->whereKey($data['represented_organization_unit_id'])->firstOrFail();
        $receiving = isset($data['receiving_organization_unit_id']) ? OrganizationUnit::query()->whereKey($data['receiving_organization_unit_id'])->firstOrFail() : null;
        $this->authorize($request, 'delegates.manage', $represented->id);

        $mandate = DelegateMandate::query()->create([
            'public_id' => Str::uuid(),
            'member_id' => $member->id,
            'represented_organization_unit_id' => $represented->id,
            'receiving_organization_unit_id' => $receiving?->id,
            'mandate_number' => $this->elections->nextNumber('delegate', 'DEL', (int) substr($data['starts_at'], 0, 4)),
            'voting_weight' => round((float) $data['voting_weight'], 3),
            'status' => 'active',
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('delegates.created', $mandate, new: $mandate->only(['mandate_number', 'member_id', 'represented_organization_unit_id', 'receiving_organization_unit_id', 'voting_weight']));

        return back()->with('success', "Delegiertenmandat {$mandate->mandate_number} wurde angelegt.");
    }

    public function endDelegate(Request $request, DelegateMandate $mandate): RedirectResponse
    {
        $this->authorize($request, 'delegates.manage', $mandate->represented_organization_unit_id);
        $mandate->update([
            'status' => 'ended',
            'ends_at' => $mandate->ends_at ?: now()->toDateString(),
        ]);
        $this->audit->record('delegates.ended', $mandate, new: ['status' => 'ended', 'ends_at' => $mandate->ends_at]);

        return back()->with('success', 'Delegiertenmandat wurde beendet.');
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
