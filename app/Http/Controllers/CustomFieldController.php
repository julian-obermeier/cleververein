<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomFieldController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'members.custom_fields');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'key' => ['nullable', 'string', 'max:80'],
            'field_type' => ['required', 'in:text,textarea,number,date,checkbox,select'],
            'options' => ['nullable', 'string', 'max:5000'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $keySource = filled($data['key'] ?? null) ? $data['key'] : $data['name'];
        $key = Str::snake(Str::ascii(trim($keySource)));
        if ($key === '') {
            $key = 'field_'.Str::lower(Str::random(6));
        }
        if (CustomFieldDefinition::query()->where('entity_type', 'member')->where('key', $key)->exists()) {
            throw ValidationException::withMessages(['key' => 'Ein Zusatzfeld mit diesem Schlüssel existiert bereits.']);
        }

        $options = $data['field_type'] === 'select'
            ? collect(preg_split('/[\r\n,;]+/', (string) ($data['options'] ?? '')))->map(fn ($option) => trim($option))->filter()->unique()->values()->all()
            : null;
        if ($data['field_type'] === 'select' && $options === []) {
            throw ValidationException::withMessages(['options' => 'Für ein Auswahlfeld muss mindestens eine Option angegeben werden.']);
        }

        $field = CustomFieldDefinition::query()->create([
            'entity_type' => 'member',
            'name' => $data['name'],
            'key' => $key,
            'field_type' => $data['field_type'],
            'options' => $options,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $this->audit->record('custom_field.created', $field, new: ['name' => $field->name, 'key' => $field->key]);

        return back()->with('success', 'Zusatzfeld wurde angelegt.');
    }

    public function toggle(Request $request, CustomFieldDefinition $field): RedirectResponse
    {
        $this->authorizePermission($request, 'members.custom_fields');
        abort_unless($field->entity_type === 'member', 404);
        $field->update(['is_active' => ! $field->is_active]);
        $this->audit->record('custom_field.updated', $field, new: ['is_active' => $field->is_active]);

        return back()->with('success', 'Zusatzfeld wurde aktualisiert.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
