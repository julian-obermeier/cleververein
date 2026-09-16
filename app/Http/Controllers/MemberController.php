<?php

namespace App\Http\Controllers;

use App\Models\FunctionDefinition;
use App\Models\Household;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MemberType;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $organizationId = $request->integer('organization_unit_id') ?: null;
        $this->authorizePermission($request, 'members.view', $organizationId);

        $query = $request->string('status')->toString() === 'archived'
            ? Member::onlyTrashed()
            : Member::query();

        $query->with(['person', 'memberships.organizationUnit', 'memberships.memberType']);

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($memberQuery) use ($search): void {
                $memberQuery->where('member_number', 'like', "%{$search}%")
                    ->orWhereHas('person', function ($personQuery) use ($search): void {
                        $personQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $status = $request->string('status')->toString();
        if ($status && $status !== 'archived') {
            $query->where('status', $status);
        }
        if ($organizationId) {
            $query->whereHas('memberships', fn ($membershipQuery) => $membershipQuery->where('organization_unit_id', $organizationId));
        }
        if ($memberTypeId = $request->integer('member_type_id')) {
            $query->whereHas('memberships', fn ($membershipQuery) => $membershipQuery->where('member_type_id', $memberTypeId));
        }

        return view('members.index', [
            'members' => $query->orderByDesc('joined_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'organizations' => OrganizationUnit::query()->with('type')->orderBy('name')->get(),
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => $request->only(['q', 'status', 'organization_unit_id', 'member_type_id']),
        ]);
    }

    public function create(Request $request): View
    {
        $organizationId = $request->integer('organization_unit_id') ?: null;
        $this->authorizePermission($request, 'members.create', $organizationId);

        return view('members.create', [
            'organizations' => OrganizationUnit::query()->with('type')->where('status', 'active')->orderBy('name')->get(),
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'preselectedOrganizationId' => $organizationId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMember($request);
        $organization = $this->organizationFrom($data['organization_unit_id'] ?? null);
        $this->authorizePermission($request, 'members.create', $organization?->id);
        $this->ensureMemberNumberAvailable($data['member_number'] ?? null);
        $this->ensureNoUnconfirmedDuplicate($data);

        $memberType = filled($data['member_type_id'] ?? null)
            ? MemberType::query()->where('is_active', true)->findOrFail($data['member_type_id'])
            : null;

        $member = DB::transaction(function () use ($data, $organization, $memberType): Member {
            $person = Person::query()->create([
                'public_id' => Str::uuid(),
                'salutation' => $data['salutation'] ?? null,
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'contact_data' => $this->contactData($data),
            ]);

            $member = Member::query()->create([
                'public_id' => Str::uuid(),
                'person_id' => $person->id,
                'member_number' => $data['member_number'] ?? null,
                'status' => $data['status'],
                'joined_at' => $data['joined_at'] ?? null,
                'left_at' => $data['left_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'meta' => $this->memberMeta($data),
            ]);

            if (! $member->member_number) {
                $member->update(['member_number' => 'M-'.str_pad((string) $member->id, 6, '0', STR_PAD_LEFT)]);
            }

            if ($organization) {
                $member->memberships()->create([
                    'organization_unit_id' => $organization->id,
                    'member_type_id' => $memberType?->id,
                    'membership_type' => $memberType?->name ?? ($data['membership_type'] ?? 'Ordentliches Mitglied'),
                    'status' => $data['membership_status'],
                    'starts_at' => $data['membership_starts_at'] ?? $data['joined_at'] ?? null,
                    'ends_at' => $data['membership_ends_at'] ?? null,
                    'is_primary' => true,
                ]);
            }

            return $member;
        });

        $this->audit->record('member.created', $member, new: ['member_number' => $member->member_number, 'status' => $member->status]);

        return redirect()->route('members.show', $member)->with('success', 'Mitglied wurde angelegt.');
    }

    public function show(Request $request, Member $member): View
    {
        $member->load([
            'person',
            'memberships.organizationUnit.type',
            'memberships.memberType',
            'households.members.person',
            'functionAssignments.definition',
            'functionAssignments.organizationUnit',
        ]);
        $this->authorizeMemberPermission($request, 'members.view', $member);

        return view('members.show', [
            'member' => $member,
            'organizations' => OrganizationUnit::query()->with('type')->where('status', 'active')->orderBy('name')->get(),
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'functions' => FunctionDefinition::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'households' => Household::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Member $member): View
    {
        $member->load(['person', 'memberships.organizationUnit']);
        $this->authorizeMemberPermission($request, 'members.update', $member);

        return view('members.edit', [
            'member' => $member,
            'organizations' => OrganizationUnit::query()->with('type')->where('status', 'active')->orderBy('name')->get(),
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $member->load('person', 'memberships');
        $this->authorizeMemberPermission($request, 'members.update', $member);
        $data = $this->validateMember($request, false);
        $this->ensureMemberNumberAvailable($data['member_number'] ?? null, $member);
        $old = ['member_number' => $member->member_number, 'status' => $member->status];

        DB::transaction(function () use ($member, $data): void {
            $member->person->update([
                'salutation' => $data['salutation'] ?? null,
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'contact_data' => $this->contactData($data),
            ]);
            $member->update([
                'member_number' => $data['member_number'] ?? $member->member_number,
                'status' => $data['status'],
                'joined_at' => $data['joined_at'] ?? null,
                'left_at' => $data['left_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'meta' => $this->memberMeta($data),
            ]);
        });

        $this->audit->record('member.updated', $member, old: $old, new: ['member_number' => $member->member_number, 'status' => $member->status]);

        return redirect()->route('members.show', $member)->with('success', 'Mitglied wurde aktualisiert.');
    }

    public function archive(Request $request, Member $member): RedirectResponse
    {
        $member->load('memberships');
        $this->authorizeMemberPermission($request, 'members.archive', $member);
        $oldStatus = $member->status;

        DB::transaction(function () use ($member): void {
            if (in_array($member->status, ['active', 'pending', 'inactive'], true)) {
                $member->status = 'resigned';
            }
            $member->left_at ??= today();
            $member->save();
            $member->memberships()->whereNull('ends_at')->update(['ends_at' => today(), 'status' => 'ended']);
            $member->delete();
        });

        $this->audit->record('member.archived', $member, old: ['status' => $oldStatus], new: ['status' => $member->status]);

        return redirect()->route('members.index')->with('success', 'Mitglied wurde archiviert.');
    }

    public function restore(Request $request, int $member): RedirectResponse
    {
        $memberModel = Member::withTrashed()->findOrFail($member);
        $memberModel->load('memberships');
        $this->authorizeMemberPermission($request, 'members.archive', $memberModel);
        $memberModel->restore();
        $this->audit->record('member.restored', $memberModel);

        return redirect()->route('members.show', $memberModel)->with('success', 'Mitglied wurde wiederhergestellt.');
    }

    public function storeMembership(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'organization_unit_id' => ['required', 'integer'],
            'member_type_id' => ['nullable', 'integer'],
            'membership_type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,pending,inactive,ended'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $organization = OrganizationUnit::query()->findOrFail($data['organization_unit_id']);
        $memberType = filled($data['member_type_id'] ?? null) ? MemberType::query()->findOrFail($data['member_type_id']) : null;
        $this->authorizePermission($request, 'members.memberships', $organization->id);

        $membership = DB::transaction(function () use ($member, $data, $organization, $memberType): Membership {
            if ((bool) ($data['is_primary'] ?? false)) {
                $member->memberships()->update(['is_primary' => false]);
            }

            return $member->memberships()->create([
                'organization_unit_id' => $organization->id,
                'member_type_id' => $memberType?->id,
                'membership_type' => $memberType?->name ?? ($data['membership_type'] ?? 'Ordentliches Mitglied'),
                'status' => $data['status'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $this->audit->record('membership.created', $membership, new: ['organization_unit_id' => $organization->id, 'status' => $membership->status]);

        return back()->with('success', 'Mitgliedschaft wurde hinzugefügt.');
    }

    public function destroyMembership(Request $request, Member $member, Membership $membership): RedirectResponse
    {
        abort_unless($membership->member_id === $member->id, 404);
        $this->authorizePermission($request, 'members.memberships', $membership->organization_unit_id);
        $this->audit->record('membership.archived', $membership);
        $membership->delete();

        return back()->with('success', 'Mitgliedschaft wurde entfernt.');
    }

    private function validateMember(Request $request, bool $withInitialMembership = true): array
    {
        $rules = [
            'salutation' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:80'],
            'mobile' => ['nullable', 'string', 'max:80'],
            'street' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:150'],
            'emergency_phone' => ['nullable', 'string', 'max:80'],
            'communication_preference' => ['nullable', 'in:email,phone,mobile,post'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'member_number' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,pending,inactive,resigned,deceased'],
            'joined_at' => ['nullable', 'date'],
            'left_at' => ['nullable', 'date', 'after_or_equal:joined_at'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'newsletter' => ['nullable', 'boolean'],
            'force_duplicate' => ['nullable', 'boolean'],
        ];

        if ($withInitialMembership) {
            $rules += [
                'organization_unit_id' => ['nullable', 'integer'],
                'member_type_id' => ['nullable', 'integer'],
                'membership_type' => ['nullable', 'string', 'max:100'],
                'membership_status' => ['required', 'in:active,pending,inactive,ended'],
                'membership_starts_at' => ['nullable', 'date'],
                'membership_ends_at' => ['nullable', 'date', 'after_or_equal:membership_starts_at'],
            ];
        }

        return $request->validate($rules);
    }

    private function contactData(array $data): array
    {
        return array_filter([
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'street' => $data['street'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
            'communication_preference' => $data['communication_preference'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function memberMeta(array $data): array
    {
        return array_filter([
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->values()->all(),
            'newsletter' => (bool) ($data['newsletter'] ?? false),
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function organizationFrom(?int $organizationId): ?OrganizationUnit
    {
        return $organizationId ? OrganizationUnit::query()->findOrFail($organizationId) : null;
    }

    private function ensureMemberNumberAvailable(?string $number, ?Member $except = null): void
    {
        if (! $number) {
            return;
        }
        $query = Member::withTrashed()->where('member_number', $number);
        if ($except) {
            $query->where('id', '!=', $except->id);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['member_number' => 'Diese Mitgliedsnummer ist bereits vergeben.']);
        }
    }

    private function ensureNoUnconfirmedDuplicate(array $data): void
    {
        if ((bool) ($data['force_duplicate'] ?? false)) {
            return;
        }

        $firstName = mb_strtolower(trim($data['first_name']));
        $lastName = mb_strtolower(trim($data['last_name']));
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        $birthDate = $data['birth_date'] ?? null;

        $candidates = Member::withTrashed()->with('person')->whereHas('person', function ($query) use ($firstName, $lastName, $email, $birthDate): void {
            $query->where(function ($person) use ($firstName, $lastName, $birthDate): void {
                $person->whereRaw('LOWER(first_name) = ?', [$firstName])
                    ->whereRaw('LOWER(last_name) = ?', [$lastName]);
                if ($birthDate) {
                    $person->whereDate('birth_date', $birthDate);
                }
            });
            if ($email) {
                $query->orWhereRaw('LOWER(email) = ?', [$email]);
            }
        })->limit(5)->get();

        if ($candidates->isNotEmpty()) {
            $matches = $candidates->map(fn (Member $member) => $member->member_number.' · '.$member->person->display_name)->implode(', ');
            throw ValidationException::withMessages([
                'duplicate' => 'Mögliche Dublette gefunden: '.$matches.'. Prüfe den bestehenden Datensatz oder aktiviere „Trotz Dublettenwarnung anlegen“.',
            ]);
        }
    }

    private function authorizePermission(Request $request, string $permission, ?int $organizationId = null): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), $permission, $organizationId), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function authorizeMemberPermission(Request $request, string $permission, Member $member): void
    {
        if ($request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission)) {
            return;
        }
        $allowed = $member->memberships->contains(fn (Membership $membership) => $membership->organization_unit_id && $this->permissions->allows($request->user(), $permission, $membership->organization_unit_id));
        abort_unless($allowed, 403, 'Für dieses Mitglied fehlt die Berechtigung.');
    }
}
