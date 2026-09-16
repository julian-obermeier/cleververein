<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Member;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request);

        $query = Household::query()
            ->with(['members.person'])
            ->withCount('members')
            ->orderBy('name');

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($householdQuery) use ($search): void {
                $householdQuery->where('name', 'like', "%{$search}%")
                    ->orWhereHas('members.person', function ($personQuery) use ($search): void {
                        $personQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return view('members.households', [
            'households' => $query->paginate(20)->withQueryString(),
            'members' => Member::query()->with('person')->orderBy('member_number')->get(),
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $this->validateHousehold($request);

        if (Household::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ein Haushalt mit diesem Namen existiert bereits.']);
        }

        $household = Household::query()->create([
            'public_id' => Str::uuid(),
            'name' => trim($data['name']),
            'contact_data' => $this->contactData($data),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncOptionalMember($household, $data);
        $this->audit->record('household.created', $household, new: ['name' => $household->name]);

        return back()->with('success', 'Haushalt wurde angelegt.');
    }

    public function update(Request $request, Household $household): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $this->validateHousehold($request, false);

        $duplicate = Household::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])
            ->whereKeyNot($household->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ein anderer Haushalt mit diesem Namen existiert bereits.']);
        }

        $old = ['name' => $household->name, 'contact_data' => $household->contact_data];
        $household->update([
            'name' => trim($data['name']),
            'contact_data' => $this->contactData($data),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('household.updated', $household, old: $old, new: ['name' => $household->name, 'contact_data' => $household->contact_data]);

        return back()->with('success', 'Haushalt wurde aktualisiert.');
    }

    public function attachMember(Request $request, Household $household): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'relationship' => ['nullable', 'string', 'max:80'],
            'is_primary_contact' => ['nullable', 'boolean'],
        ]);
        $member = Member::query()->findOrFail($data['member_id']);

        DB::transaction(function () use ($household, $member, $data): void {
            if ((bool) ($data['is_primary_contact'] ?? false)) {
                DB::table('household_members')
                    ->where('tenant_id', $this->tenant->id())
                    ->where('household_id', $household->id)
                    ->update(['is_primary_contact' => false]);
            }

            $household->members()->syncWithoutDetaching([
                $member->id => [
                    'tenant_id' => $this->tenant->id(),
                    'relationship' => $data['relationship'] ?? null,
                    'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
                ],
            ]);
        });
        $this->audit->record('household.member_added', $household, new: ['member_id' => $member->id]);

        return back()->with('success', 'Mitglied wurde dem Haushalt zugeordnet.');
    }

    public function detachMember(Request $request, Household $household, Member $member): RedirectResponse
    {
        $this->authorizePermission($request);
        abort_unless($household->members()->whereKey($member->id)->exists(), 404);
        $household->members()->detach($member->id);
        $this->audit->record('household.member_removed', $household, old: ['member_id' => $member->id]);

        return back()->with('success', 'Mitglied wurde aus dem Haushalt entfernt.');
    }

    public function destroy(Request $request, Household $household): RedirectResponse
    {
        $this->authorizePermission($request);
        if ($household->members()->exists()) {
            throw ValidationException::withMessages(['household' => 'Der Haushalt enthält noch Mitglieder und kann nicht archiviert werden.']);
        }

        $this->audit->record('household.archived', $household, old: ['name' => $household->name]);
        $household->delete();

        return back()->with('success', 'Haushalt wurde archiviert.');
    }

    private function validateHousehold(Request $request, bool $withMember = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'street' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($withMember) {
            $rules += [
                'member_id' => ['nullable', 'integer'],
                'relationship' => ['nullable', 'string', 'max:80'],
                'is_primary_contact' => ['nullable', 'boolean'],
            ];
        }

        return $request->validate($rules);
    }

    private function contactData(array $data): array
    {
        return array_filter([
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'street' => $data['street'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function syncOptionalMember(Household $household, array $data): void
    {
        if (! filled($data['member_id'] ?? null)) {
            return;
        }

        $member = Member::query()->findOrFail($data['member_id']);
        $household->members()->attach($member->id, [
            'tenant_id' => $this->tenant->id(),
            'relationship' => $data['relationship'] ?? null,
            'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
        ]);
    }

    private function authorizePermission(Request $request): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), 'members.households'), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
