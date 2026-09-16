<?php

namespace App\Http\Controllers;

use App\Models\FunctionAssignment;
use App\Models\FunctionDefinition;
use App\Models\OrganizationUnit;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FunctionDirectoryController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request);

        $query = FunctionAssignment::query()
            ->with(['definition', 'member.person', 'organizationUnit'])
            ->orderByRaw('ends_at IS NULL DESC')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($assignmentQuery) use ($search): void {
                $assignmentQuery->whereHas('definition', fn ($definitionQuery) => $definitionQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('member.person', function ($personQuery) use ($search): void {
                        $personQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('organizationUnit', fn ($organizationQuery) => $organizationQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $status = $request->string('status')->toString();
        if ($status === 'active') {
            $query->where(function ($activeQuery): void {
                $activeQuery->whereNull('ends_at')->orWhereDate('ends_at', '>=', today());
            });
        } elseif ($status === 'ended') {
            $query->whereNotNull('ends_at')->whereDate('ends_at', '<', today());
        }

        if ($functionId = $request->integer('function_definition_id')) {
            $query->where('function_definition_id', $functionId);
        }
        if ($organizationId = $request->integer('organization_unit_id')) {
            $query->where('organization_unit_id', $organizationId);
        }

        return view('members.functions', [
            'assignments' => $query->paginate(30)->withQueryString(),
            'definitions' => FunctionDefinition::query()->orderBy('sort_order')->orderBy('name')->get(),
            'organizations' => OrganizationUnit::query()->orderBy('name')->get(),
            'filters' => $request->only(['q', 'status', 'function_definition_id', 'organization_unit_id']),
            'activeCount' => FunctionAssignment::query()
                ->where(fn ($activeQuery) => $activeQuery->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
                ->count(),
        ]);
    }

    private function authorizePermission(Request $request): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless(
            $this->permissions->allows($request->user(), 'members.functions') || $this->permissions->allows($request->user(), 'members.view'),
            403,
            'Für diese Ansicht fehlt die Berechtigung.',
        );
    }
}
