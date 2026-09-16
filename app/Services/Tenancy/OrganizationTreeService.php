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

    public function move(OrganizationUnit $unit, ?OrganizationUnit $newParent): void
    {
        if ($unit->tenant_id !== $this->context->id() || ($newParent && $newParent->tenant_id !== $this->context->id())) {
            abort(403, 'Die Organisationseinheit gehört zu einem anderen Mandanten.');
        }
        if ($newParent?->is($unit)) {
            abort(422, 'Eine Organisationseinheit kann nicht ihr eigener Elternknoten sein.');
        }
        if ($newParent && DB::table('organization_closure')
            ->where('tenant_id', $this->context->id())
            ->where('ancestor_id', $unit->id)
            ->where('descendant_id', $newParent->id)
            ->exists()) {
            abort(422, 'Eine Organisationseinheit kann nicht unter eine eigene Untergliederung verschoben werden.');
        }

        DB::transaction(function () use ($unit, $newParent): void {
            $tenantId = $this->context->id();
            $subtree = DB::table('organization_closure')
                ->where('tenant_id', $tenantId)
                ->where('ancestor_id', $unit->id)
                ->get(['descendant_id', 'depth']);
            $subtreeIds = $subtree->pluck('descendant_id')->all();

            DB::table('organization_closure')
                ->where('tenant_id', $tenantId)
                ->whereIn('descendant_id', $subtreeIds)
                ->whereNotIn('ancestor_id', $subtreeIds)
                ->delete();

            if ($newParent) {
                $parentAncestors = DB::table('organization_closure')
                    ->where('tenant_id', $tenantId)
                    ->where('descendant_id', $newParent->id)
                    ->get(['ancestor_id', 'depth']);

                $rows = [];
                foreach ($parentAncestors as $ancestor) {
                    foreach ($subtree as $descendant) {
                        $rows[] = [
                            'tenant_id' => $tenantId,
                            'ancestor_id' => $ancestor->ancestor_id,
                            'descendant_id' => $descendant->descendant_id,
                            'depth' => $ancestor->depth + 1 + $descendant->depth,
                        ];
                    }
                }
                if ($rows) {
                    DB::table('organization_closure')->insert($rows);
                }
            }

            $unit->forceFill(['parent_id' => $newParent?->id])->save();
        });
    }
}
