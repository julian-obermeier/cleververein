<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberTag;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberBulkController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
    ) {}

    public function apply(Request $request): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $request->validate([
            'members' => ['required', 'array', 'min:1', 'max:500'],
            'members.*' => ['integer'],
            'action' => ['required', 'in:set_status,add_tag,remove_tag,archive'],
            'status' => ['nullable', 'in:active,pending,inactive,resigned,deceased'],
            'member_tag_id' => ['nullable', 'integer'],
        ]);

        $members = Member::query()->whereIn('id', array_values(array_unique($data['members'])))->get();
        if ($members->isEmpty()) {
            throw ValidationException::withMessages(['members' => 'Keine gültigen Mitglieder ausgewählt.']);
        }

        $tag = null;
        if (in_array($data['action'], ['add_tag', 'remove_tag'], true)) {
            $tag = MemberTag::query()->findOrFail($data['member_tag_id'] ?? 0);
        }
        if ($data['action'] === 'set_status' && blank($data['status'] ?? null)) {
            throw ValidationException::withMessages(['status' => 'Für diese Massenaktion muss ein Status ausgewählt werden.']);
        }

        DB::transaction(function () use ($members, $data, $tag): void {
            foreach ($members as $member) {
                if ($data['action'] === 'set_status') {
                    $old = $member->status;
                    $member->update(['status' => $data['status']]);
                    $this->audit->record('member.bulk_status_changed', $member, old: ['status' => $old], new: ['status' => $member->status]);

                    continue;
                }

                if ($data['action'] === 'add_tag' && $tag) {
                    $member->tags()->syncWithoutDetaching([$tag->id => ['tenant_id' => $this->tenant->id()]]);
                    $this->audit->record('member.bulk_tag_added', $member, new: ['tag_id' => $tag->id, 'tag' => $tag->name]);

                    continue;
                }

                if ($data['action'] === 'remove_tag' && $tag) {
                    $member->tags()->detach($tag->id);
                    $this->audit->record('member.bulk_tag_removed', $member, old: ['tag_id' => $tag->id, 'tag' => $tag->name]);

                    continue;
                }

                if ($data['action'] === 'archive') {
                    $old = $member->status;
                    if (in_array($member->status, ['active', 'pending', 'inactive'], true)) {
                        $member->status = 'resigned';
                    }
                    $member->left_at ??= today();
                    $member->save();
                    $member->memberships()->whereNull('ends_at')->update(['ends_at' => today(), 'status' => 'ended']);
                    $member->delete();
                    $this->audit->record('member.bulk_archived', $member, old: ['status' => $old], new: ['status' => $member->status]);
                }
            }
        });

        return back()->with('success', $members->count().' Mitglieder wurden verarbeitet.');
    }

    private function authorizePermission(Request $request): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), 'members.bulk'), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
