<?php

namespace App\Services\Tenancy;

use App\Models\OrganizationUnit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class OrganizationTreeService
{
    public function __construct(private TenantContext $context) {}

    public function create(array $attributes, ?OrganizationUnit $parent = null): OrganizationUnit
    {
        if ($parent && $parent->tenant_id !== $this->context->id()) {
            abort(403, 'Die übergeordnete Einheit gehört zu einem anderen Mandanten.');
        }

        return DB::transaction(function () use ($attributes, $parent): OrganizationUnit {
            $unit = OrganizationUnit::query()->create([...$attributes, 'parent_id' => $parent?->id]);
            DB::table('organization_closure')->insert([
                'tenant_id' => $this->context->id(), 'ancestor_id' => $unit->id, 'descendant_id' => $unit->id, 'depth' => 0,
            ]);
            if ($parent) {
                $ancestors = DB::table('organization_closure')->where('tenant_id', $this->context->id())->where('descendant_id', $parent->id)->get();
                DB::table('organization_closure')->insert($ancestors->map(fn ($row) => [
                    'tenant_id' => $this->context->id(), 'ancestor_id' => $row->ancestor_id, 'descendant_id' => $unit->id, 'depth' => $row->depth + 1,
                ])->all());
            }
            return $unit;
        });
    }
}
