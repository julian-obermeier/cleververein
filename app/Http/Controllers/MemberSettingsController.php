<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use App\Models\FunctionDefinition;
use App\Models\MemberTag;
use App\Models\MemberType;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberSettingsController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function __invoke(Request $request): View
    {
        if (! $request->user()->is_super_admin) {
            abort_unless(
                $this->permissions->allows($request->user(), 'members.master_data')
                || $this->permissions->allows($request->user(), 'members.custom_fields')
                || $this->permissions->allows($request->user(), 'members.tags')
                || $this->permissions->allows($request->user(), 'members.import_export'),
                403,
                'Für diese Seite fehlt die Berechtigung.',
            );
        }

        return view('members.settings', [
            'memberTypes' => MemberType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'functions' => FunctionDefinition::query()->orderBy('sort_order')->orderBy('name')->get(),
            'customFields' => CustomFieldDefinition::query()
                ->where('entity_type', 'member')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'tags' => MemberTag::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }
}
