<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use App\Models\FunctionAssignment;
use App\Models\Member;
use App\Models\MemberCommunication;
use App\Models\MemberDocument;
use App\Models\MemberTag;
use App\Models\Membership;
use App\Models\Person;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberCrmController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
    ) {
    }

    public function dashboard(Request $request, Member $member): View
    {
        $this->authorizeMemberPermission($request, 'members.view', $member);
        $member->load([
            'person',
            'memberships.organizationUnit',
            'tags',
            'documents.uploader',
            'communications.user',
            'customFieldValues.definition',
        ]);

        return view('members.crm', [
            'member' => $member,
            'tags' => MemberTag::query()->where('is_active', true)->orderBy('name')->get(),
            'customFields' => CustomFieldDefinition::query()
                ->where('entity_type', 'member')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'customValues' => $member->customFieldValues->keyBy('custom_field_definition_id'),
            'canTags' => $this->allowsMemberPermission($request, 'members.tags', $member),
            'canDocuments' => $this->allowsMemberPermission($request, 'members.documents', $member),
            'canCommunications' => $this->allowsMemberPermission($request, 'members.communications', $member),
            'canUpdate' => $this->allowsMemberPermission($request, 'members.update', $member),
            'canHistory' => $this->allowsMemberPermission($request, 'members.history', $member),
        ]);
    }

    public function saveCustomFields(Request $request, Member $member): RedirectResponse
    {
        $this->authorizeMemberPermission($request, 'members.update', $member);
        $fields = CustomFieldDefinition::query()
            ->where('entity_type', 'member')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $rules = ['custom_fields' => ['nullable', 'array']];
        foreach ($fields as $field) {
            $fieldRules = $field->is_required ? ['required'] : ['nullable'];
            $fieldRules[] = match ($field->field_type) {
                'number' => 'numeric',
                'date' => 'date',
                'checkbox' => $field->is_required ? 'accepted' : 'boolean',
                'select' => Rule::in($field->options ?? []),
                default => 'string',
            };
            if (in_array($field->field_type, ['text', 'textarea', 'select'], true)) {
                $fieldRules[] = 'max:10000';
            }
            $rules['custom_fields.'.$field->id] = $fieldRules;
        }
        $validated = $request->validate($rules);
        $values = $validated['custom_fields'] ?? [];
        $oldValues = $member->customFieldValues()->pluck('value', 'custom_field_definition_id')->all();

        DB::transaction(function () use ($member, $fields, $values): void {
            foreach ($fields as $field) {
                $value = $values[$field->id] ?? null;
                if ($field->field_type === 'checkbox') {
                    $value = (string) ((bool) $value ? 1 : 0);
                } elseif (is_string($value)) {
                    $value = trim($value);
                }

                if ($value === null || $value === '') {
                    $member->customFieldValues()->where('custom_field_definition_id', $field->id)->delete();
                    continue;
                }

                $member->customFieldValues()->updateOrCreate(
                    ['custom_field_definition_id' => $field->id],
                    ['entity_type' => 'member', 'value' => (string) $value],
                );
            }
        });

        $newValues = $member->customFieldValues()->pluck('value', 'custom_field_definition_id')->all();
        $this->audit->record('member.custom_fields_updated', $member, old: $oldValues, new: $newValues);

        return back()->with('success', 'Zusatzfelder wurden gespeichert.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'members.tags');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        if (MemberTag::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ein Tag mit diesem Namen existiert bereits.']);
        }

        $tag = MemberTag::query()->create([
            'name' => trim($data['name']),
            'color' => $data['color'] ?? null,
            'is_active' => true,
        ]);
        $this->audit->record('member_tag.created', $tag, new: ['name' => $tag->name]);

        return back()->with('success', 'Tag wurde angelegt.');
    }

    public function toggleTag(Request $request, MemberTag $tag): RedirectResponse
    {
        $this->authorizePermission($request, 'members.tags');
        $tag->update(['is_active' => ! $tag->is_active]);
        $this->audit->record('member_tag.updated', $tag, new: ['is_active' => $tag->is_active]);

        return back()->with('success', 'Tag wurde aktualisiert.');
    }

    public function assignTag(Request $request, Member $member): RedirectResponse
    {
        $this->authorizeMemberPermission($request, 'members.tags', $member);
        $data = $request->validate(['member_tag_id' => ['required', 'integer']]);
        $tag = MemberTag::query()->where('is_active', true)->findOrFail($data['member_tag_id']);
        $member->tags()->syncWithoutDetaching([$tag->id => ['tenant_id' => $this->tenant->id()]]);
        $this->audit->record('member.tag_added', $member, new: ['tag_id' => $tag->id, 'tag' => $tag->name]);

        return back()->with('success', 'Tag wurde zugewiesen.');
    }

    public function detachTag(Request $request, Member $member, MemberTag $tag): RedirectResponse
    {
        $this->authorizeMemberPermission($request, 'members.tags', $member);
        $member->tags()->detach($tag->id);
        $this->audit->record('member.tag_removed', $member, old: ['tag_id' => $tag->id, 'tag' => $tag->name]);

        return back()->with('success', 'Tag wurde entfernt.');
    }

    public function storeDocument(Request $request, Member $member): RedirectResponse
    {
        $this->authorizeMemberPermission($request, 'members.documents', $member);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:80'],
            'document_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,odt,ods,txt,csv', 'max:15360'],
        ]);
        $file = $request->file('file');
        $safeName = Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs('member-documents/'.$this->tenant->id().'/'.$member->id, $safeName, 'local');

        $document = $member->documents()->create([
            'public_id' => Str::uuid(),
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'document_date' => $data['document_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('member.document_uploaded', $member, new: [
            'document_id' => $document->id,
            'title' => $document->title,
            'original_name' => $document->original_name,
        ]);

        return back()->with('success', 'Dokument wurde hochgeladen.');
    }

    public function downloadDocument(Request $request, Member $member, MemberDocument $document): StreamedResponse
    {
        abort_unless($document->member_id === $member->id, 404);
        $this->authorizeMemberPermission($request, 'members.documents', $member);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Datei wurde im Speicher nicht gefunden.');

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function destroyDocument(Request $request, Member $member, MemberDocument $document): RedirectResponse
    {
        abort_unless($document->member_id === $member->id, 404);
        $this->authorizeMemberPermission($request, 'members.documents', $member);
        $document->delete();
        $this->audit->record('member.document_archived', $member, old: [
            'document_id' => $document->id,
            'title' => $document->title,
        ]);

        return back()->with('success', 'Dokument wurde archiviert.');
    }

    public function storeCommunication(Request $request, Member $member): RedirectResponse
    {
        $this->authorizeMemberPermission($request, 'members.communications', $member);
        $data = $request->validate([
            'channel' => ['required', 'in:email,phone,mobile,post,meeting,other'],
            'direction' => ['required', 'in:inbound,outbound,internal'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'outcome' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['required', 'date'],
        ]);

        $communication = $member->communications()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);
        $this->audit->record('member.communication_added', $member, new: [
            'communication_id' => $communication->id,
            'channel' => $communication->channel,
            'direction' => $communication->direction,
            'subject' => $communication->subject,
        ]);

        return back()->with('success', 'Kommunikation wurde dokumentiert.');
    }

    public function destroyCommunication(Request $request, Member $member, MemberCommunication $communication): RedirectResponse
    {
        abort_unless($communication->member_id === $member->id, 404);
        $this->authorizeMemberPermission($request, 'members.communications', $member);
        $this->audit->record('member.communication_removed', $member, old: [
            'communication_id' => $communication->id,
            'subject' => $communication->subject,
        ]);
        $communication->delete();

        return back()->with('success', 'Kommunikationseintrag wurde entfernt.');
    }

    public function history(Request $request, Member $member): View
    {
        $this->authorizeMemberPermission($request, 'members.history', $member);
        $member->load(['person', 'memberships', 'functionAssignments']);

        $subjectPairs = [
            [Member::class, [$member->id]],
            [Person::class, [$member->person_id]],
            [Membership::class, $member->memberships->pluck('id')->all()],
            [FunctionAssignment::class, $member->functionAssignments->pluck('id')->all()],
        ];

        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.tenant_id', $this->tenant->id())
            ->where(function ($query) use ($subjectPairs): void {
                foreach ($subjectPairs as [$type, $ids]) {
                    if ($ids === []) {
                        continue;
                    }
                    $query->orWhere(function ($part) use ($type, $ids): void {
                        $part->where('audit_logs.subject_type', $type)->whereIn('audit_logs.subject_id', $ids);
                    });
                }
            })
            ->select('audit_logs.*', 'users.name as user_name', 'users.email as user_email')
            ->orderByDesc('audit_logs.created_at')
            ->paginate(50);

        return view('members.history', compact('member', 'logs'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function allowsMemberPermission(Request $request, string $permission, Member $member): bool
    {
        $member->loadMissing('memberships');
        if ($request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission)) {
            return true;
        }

        return $member->memberships->contains(fn (Membership $membership) => $membership->organization_unit_id && $this->permissions->allows($request->user(), $permission, $membership->organization_unit_id));
    }

    private function authorizeMemberPermission(Request $request, string $permission, Member $member): void
    {
        abort_unless($this->allowsMemberPermission($request, $permission, $member), 403, 'Für dieses Mitglied fehlt die Berechtigung.');
    }
}
