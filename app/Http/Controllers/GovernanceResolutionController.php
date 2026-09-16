<?php

namespace App\Http\Controllers;

use App\Models\GovernanceResolution;
use App\Models\OrganizationUnit;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GovernanceResolutionController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function __invoke(Request $request): View
    {
        abort_unless(
            $request->user()->is_super_admin || $this->permissions->allows($request->user(), 'governance.view'),
            403,
            'Für diese Aktion fehlt die Berechtigung.',
        );

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:passed,rejected,recorded'],
            'organization_unit_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $query = GovernanceResolution::query()
            ->with(['meeting.committee', 'organizationUnit', 'motion'])
            ->withCount(['tasks as open_tasks_count' => fn ($taskQuery) => $taskQuery->whereIn('status', ['open', 'in_progress'])]);
        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(fn ($builder) => $builder
                ->where('resolution_number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('resolution_text', 'like', "%{$term}%"));
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('decision_status', $filters['status']);
        }
        if (filled($filters['organization_unit_id'] ?? null)) {
            OrganizationUnit::query()->whereKey($filters['organization_unit_id'])->firstOrFail();
            $query->where('organization_unit_id', $filters['organization_unit_id']);
        }
        if (filled($filters['year'] ?? null)) {
            $query->whereHas('meeting', fn ($meeting) => $meeting->whereYear('starts_at', (int) $filters['year']));
        }

        return view('governance.resolutions', [
            'resolutions' => $query->orderByDesc('id')->paginate(30)->withQueryString(),
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }
}
