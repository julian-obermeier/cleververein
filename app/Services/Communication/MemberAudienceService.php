<?php

namespace App\Services\Communication;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class MemberAudienceService
{
    public function query(array $criteria = []): Builder
    {
        $query = Member::query();

        if (filled($criteria['status'] ?? null)) {
            $query->where('status', $criteria['status']);
        }
        if (filled($criteria['organization_unit_id'] ?? null)) {
            $query->whereHas('memberships', fn ($q) => $q->where('organization_unit_id', $criteria['organization_unit_id']));
        }
        if (filled($criteria['member_type_id'] ?? null)) {
            $query->whereHas('memberships', fn ($q) => $q->where('member_type_id', $criteria['member_type_id']));
        }
        if (filled($criteria['member_tag_id'] ?? null)) {
            $query->whereHas('tags', fn ($q) => $q->whereKey($criteria['member_tag_id']));
        }
        if (filled($criteria['joined_from'] ?? null)) {
            $query->whereDate('joined_at', '>=', $criteria['joined_from']);
        }
        if (filled($criteria['joined_to'] ?? null)) {
            $query->whereDate('joined_at', '<=', $criteria['joined_to']);
        }

        return $query;
    }
}
