<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\OrganizationUnit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeMembers = Member::query()->where('status', 'active')->count();
        $pendingMembers = Member::query()->where('status', 'pending')->count();
        $organizationUnits = OrganizationUnit::query()->where('status', 'active')->count();
        $newThisMonth = Member::query()->whereDate('joined_at', '>=', now()->startOfMonth()->toDateString())->count();
        $recentMembers = Member::query()->with(['person', 'memberships.organizationUnit'])->latest('created_at')->limit(6)->get();
        $statusCounts = Member::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('dashboard.index', compact(
            'activeMembers',
            'pendingMembers',
            'organizationUnits',
            'newThisMonth',
            'recentMembers',
            'statusCounts',
        ));
    }
}
