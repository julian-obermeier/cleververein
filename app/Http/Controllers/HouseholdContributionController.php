<?php

namespace App\Http\Controllers;

use App\Models\ContributionRate;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseholdContributionController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
    ) {}

    public function storeRate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin || $this->permissions->allows($request->user(), 'finance.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('contribution_rates', 'code')->where('tenant_id', $this->tenant->id())],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'interval' => ['required', 'in:monthly,quarterly,half_yearly,yearly,once'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $rate = ContributionRate::query()->create([...$data, 'scope' => 'household', 'is_active' => true]);
        $this->audit->record('finance.household_rate_created', $rate, new: $rate->only(['name', 'code', 'amount', 'interval', 'scope']));

        return back()->with('success', 'Haushaltsbeitrag wurde angelegt.');
    }
}
