<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Audit\AuditService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $context, private AuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $tenantId = (int) ($request->session()->get('tenant_id') ?: $user->current_tenant_id);
        $supportMode = $user->is_super_admin
            && filter_var($request->session()->get('support_mode', false), FILTER_VALIDATE_BOOL);

        $tenant = $supportMode
            ? Tenant::query()->find($tenantId)
            : $user->tenants()->whereKey($tenantId)->wherePivot('status', 'active')->first();

        abort_unless($tenant && in_array($tenant->status, ['active', 'trial'], true), 403, 'Kein zulässiger Mandantenkontext.');
        $this->context->set($tenant, $supportMode);

        if ($supportMode && ! $request->session()->has('support_access_logged')) {
            $this->audit->record('support.access_started', $tenant, reason: (string) $request->session()->get('support_reason'));
            $request->session()->put('support_access_logged', true);
        }

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
