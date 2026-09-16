<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberSegment;
use App\Models\MemberTag;
use App\Models\MemberType;
use App\Models\OrganizationUnit;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberSegmentController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request);
        $segments = MemberSegment::query()->orderByDesc('is_active')->orderBy('name')->get();
        $segments->each(fn (MemberSegment $segment) => $segment->setAttribute('member_count', $this->queryFor($segment->criteria ?? [])->count()));

        return view('members.segments', [
            'segments' => $segments,
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => MemberTag::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:active,pending,inactive,resigned,deceased'],
            'organization_unit_id' => ['nullable', 'integer'],
            'member_type_id' => ['nullable', 'integer'],
            'member_tag_id' => ['nullable', 'integer'],
            'joined_from' => ['nullable', 'date'],
            'joined_to' => ['nullable', 'date', 'after_or_equal:joined_from'],
        ]);
        if (MemberSegment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ein Segment mit diesem Namen existiert bereits.']);
        }

        if (filled($data['organization_unit_id'] ?? null)) {
            OrganizationUnit::query()->findOrFail($data['organization_unit_id']);
        }
        if (filled($data['member_type_id'] ?? null)) {
            MemberType::query()->findOrFail($data['member_type_id']);
        }
        if (filled($data['member_tag_id'] ?? null)) {
            MemberTag::query()->findOrFail($data['member_tag_id']);
        }

        $criteria = array_filter([
            'status' => $data['status'] ?? null,
            'organization_unit_id' => $data['organization_unit_id'] ?? null,
            'member_type_id' => $data['member_type_id'] ?? null,
            'member_tag_id' => $data['member_tag_id'] ?? null,
            'joined_from' => $data['joined_from'] ?? null,
            'joined_to' => $data['joined_to'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $segment = MemberSegment::query()->create([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'criteria' => $criteria,
            'is_active' => true,
        ]);
        $this->audit->record('member_segment.created', $segment, new: ['criteria' => $criteria]);

        return back()->with('success', 'Segment wurde angelegt.');
    }

    public function show(Request $request, MemberSegment $segment): View
    {
        $this->authorizePermission($request);
        $members = $this->queryFor($segment->criteria ?? [])
            ->with(['person', 'memberships.organizationUnit', 'memberships.memberType', 'tags'])
            ->orderBy('member_number')
            ->paginate(50);

        return view('members.segment-show', compact('segment', 'members'));
    }

    public function toggle(Request $request, MemberSegment $segment): RedirectResponse
    {
        $this->authorizePermission($request);
        $segment->update(['is_active' => ! $segment->is_active]);
        $this->audit->record('member_segment.updated', $segment, new: ['is_active' => $segment->is_active]);

        return back()->with('success', 'Segment wurde aktualisiert.');
    }

    private function queryFor(array $criteria): Builder
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

    private function authorizePermission(Request $request): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), 'members.segments'), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
