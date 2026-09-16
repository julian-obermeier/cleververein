<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function __construct(private TenantContext $context) {}

    public function allows(User $user, string $permissionKey, ?int $organizationUnitId = null): bool
    {
        if ($user->is_super_admin && $this->context->isSupportMode()) {
            return true;
        }
        $permissionId = Permission::query()->where('key', $permissionKey)->value('id');
        if (! $permissionId || ! $this->context->hasTenant()) {
            return false;
        }

        $direct = DB::table('permission_assignments')
            ->where('tenant_id', $this->context->id())->where('user_id', $user->id)->where('permission_id', $permissionId)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->get()->filter(fn ($row) => $this->scopeMatches($row->organization_unit_id, (bool) $row->include_descendants, $organizationUnitId));
        if ($direct->contains(fn ($row) => ! $row->allowed)) {
            return false;
        }
        if ($direct->contains(fn ($row) => (bool) $row->allowed)) {
            return true;
        }

        return DB::table('role_assignments as ra')
            ->join('permission_role as pr', 'pr.role_id', '=', 'ra.role_id')
            ->where('ra.tenant_id', $this->context->id())->where('ra.user_id', $user->id)->where('pr.permission_id', $permissionId)
            ->where(fn ($q) => $q->whereNull('ra.valid_from')->orWhere('ra.valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ra.valid_until')->orWhere('ra.valid_until', '>=', now()))
            ->get(['ra.organization_unit_id', 'ra.include_descendants'])
            ->contains(fn ($row) => $this->scopeMatches($row->organization_unit_id, (bool) $row->include_descendants, $organizationUnitId));
    }

    private function scopeMatches(?int $assignedOrganization, bool $includeDescendants, ?int $requestedOrganization): bool
    {
        if ($assignedOrganization === null) return true;
        if ($requestedOrganization === null) return false;
        if ($assignedOrganization === $requestedOrganization) return true;
        return $includeDescendants && DB::table('organization_closure')->where('tenant_id', $this->context->id())->where('ancestor_id', $assignedOrganization)->where('descendant_id', $requestedOrganization)->exists();
    }
}
