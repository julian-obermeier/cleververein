<?php

namespace App\Http\Controllers;

use App\Models\GovernanceMeeting;
use App\Models\GovernanceTask;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\Authorization\PermissionService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function __invoke(): View
    {
        $activeMembers = Member::query()->where('status', 'active')->count();
        $pendingMembers = Member::query()->where('status', 'pending')->count();
        $organizationUnits = OrganizationUnit::query()->where('status', 'active')->count();
        $newThisMonth = Member::query()->whereDate('joined_at', '>=', now()->startOfMonth()->toDateString())->count();
        $recentMembers = Member::query()->with(['person', 'memberships.organizationUnit'])->latest('created_at')->limit(6)->get();
        $statusCounts = Member::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $user = auth()->user();
        $canViewGovernance = $user->is_super_admin || $this->permissions->allows($user, 'governance.view');
        $upcomingMeetings = collect();
        $governanceTasks = collect();
        if ($canViewGovernance) {
            $upcomingMeetings = GovernanceMeeting::query()
                ->with(['committee', 'organizationUnit'])
                ->whereIn('status', ['planned', 'in_progress'])
                ->where('starts_at', '>=', now()->startOfDay())
                ->orderBy('starts_at')
                ->limit(5)
                ->get();
            $governanceTasks = GovernanceTask::query()
                ->with(['assignedMember.person', 'resolution'])
                ->whereIn('status', ['open', 'in_progress'])
                ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_at')
                ->limit(6)
                ->get();
        }

        return view('dashboard.index', compact(
            'activeMembers',
            'pendingMembers',
            'organizationUnits',
            'newThisMonth',
            'recentMembers',
            'statusCounts',
            'canViewGovernance',
            'upcomingMeetings',
            'governanceTasks',
        ));
    }
}
