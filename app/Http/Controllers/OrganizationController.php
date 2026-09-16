<?php

namespace App\Http\Controllers;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Tenancy\OrganizationTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private OrganizationTreeService $tree,
        private AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'organization.view');
        $units = OrganizationUnit::query()
            ->with(['type', 'parent'])
            ->withCount(['memberships' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return view('organization.index', [
            'types' => OrganizationType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'units' => $units,
            'rows' => $this->flatten($units),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $slug = $this->uniqueTypeSlug($data['name']);
        $type = OrganizationType::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->audit->record('organization_type.created', $type, new: ['name' => $type->name]);

        return back()->with('success', 'Organisationstyp wurde angelegt.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $data = $this->validateUnit($request);
        $parent = $data['parent_id'] ? OrganizationUnit::query()->findOrFail($data['parent_id']) : null;
        $this->authorizePermission($request, 'organization.manage', $parent?->id);
        OrganizationType::query()->whereKey($data['organization_type_id'])->firstOrFail();

        $unit = $this->tree->create([
            'public_id' => Str::uuid(),
            'organization_type_id' => $data['organization_type_id'],
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'slug' => $this->uniqueUnitSlug($data['name']),
            'status' => $data['status'],
            'founded_at' => $data['founded_at'] ?? null,
            'dissolved_at' => $data['dissolved_at'] ?? null,
            'contact_data' => $this->contactData($data),
        ], $parent);
        $this->audit->record('organization_unit.created', $unit, new: ['name' => $unit->name, 'parent_id' => $unit->parent_id]);

        return back()->with('success', 'Organisationseinheit wurde angelegt.');
    }

    public function updateUnit(Request $request, OrganizationUnit $unit): RedirectResponse
    {
        $this->authorizePermission($request, 'organization.manage', $unit->id);
        $data = $this->validateUnit($request);
        OrganizationType::query()->whereKey($data['organization_type_id'])->firstOrFail();
        $parent = $data['parent_id'] ? OrganizationUnit::query()->findOrFail($data['parent_id']) : null;
        $old = ['name' => $unit->name, 'parent_id' => $unit->parent_id, 'status' => $unit->status];

        if ($unit->parent_id !== $parent?->id) {
            $this->tree->move($unit, $parent);
        }

        $unit->update([
            'organization_type_id' => $data['organization_type_id'],
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'slug' => $this->uniqueUnitSlug($data['name'], $unit),
            'status' => $data['status'],
            'founded_at' => $data['founded_at'] ?? null,
            'dissolved_at' => $data['dissolved_at'] ?? null,
            'contact_data' => $this->contactData($data),
        ]);
        $this->audit->record('organization_unit.updated', $unit, old: $old, new: ['name' => $unit->name, 'parent_id' => $unit->parent_id, 'status' => $unit->status]);

        return back()->with('success', 'Organisationseinheit wurde aktualisiert.');
    }

    public function archiveUnit(Request $request, OrganizationUnit $unit): RedirectResponse
    {
        $this->authorizePermission($request, 'organization.manage', $unit->id);
        if ($unit->children()->exists()) {
            throw ValidationException::withMessages(['organization' => 'Die Einheit besitzt Untergliederungen und kann nicht archiviert werden.']);
        }
        if ($unit->memberships()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['organization' => 'Der Einheit sind noch aktive Mitgliedschaften zugeordnet.']);
        }

        $unit->update(['status' => 'inactive', 'dissolved_at' => $unit->dissolved_at ?: today()]);
        $this->audit->record('organization_unit.archived', $unit);
        $unit->delete();

        return back()->with('success', 'Organisationseinheit wurde archiviert.');
    }

    private function validateUnit(Request $request): array
    {
        return $request->validate([
            'organization_type_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:active,inactive,planned'],
            'founded_at' => ['nullable', 'date'],
            'dissolved_at' => ['nullable', 'date', 'after_or_equal:founded_at'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'street' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);
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

    private function uniqueUnitSlug(string $name, ?OrganizationUnit $except = null): string
    {
        $base = Str::slug($name) ?: 'einheit';
        $slug = $base;
        $counter = 2;
        while (OrganizationUnit::withTrashed()->where('slug', $slug)->when($except, fn ($query) => $query->where('id', '!=', $except->id))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function uniqueTypeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'typ';
        $slug = $base;
        $counter = 2;
        while (OrganizationType::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function flatten(Collection $units): array
    {
        $children = $units->groupBy(fn (OrganizationUnit $unit) => $unit->parent_id ?: 0);
        $rows = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $children): void {
            foreach ($children->get($parentId, collect()) as $unit) {
                $rows[] = ['unit' => $unit, 'depth' => $depth];
                $walk($unit->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $rows;
    }

    private function authorizePermission(Request $request, string $permission, ?int $organizationId = null): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), $permission, $organizationId), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
