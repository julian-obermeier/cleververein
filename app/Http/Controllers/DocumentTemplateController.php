<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Member;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Documents\DocumentTemplateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
        private DocumentTemplateService $renderer,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'documents.view');

        return view('documents.index', [
            'templates' => DocumentTemplate::query()->with('creator')->orderByDesc('is_active')->orderBy('name')->get(),
            'documents' => GeneratedDocument::query()->with(['template', 'member.person', 'generator'])->orderByDesc('generated_at')->limit(50)->get(),
            'members' => Member::query()->with('person')->whereIn('status', ['active', 'pending', 'inactive'])->orderBy('member_number')->limit(500)->get(),
            'canManage' => $this->allows($request, 'documents.manage'),
            'canGenerate' => $this->allows($request, 'documents.generate'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'documents.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'page_size' => ['required', 'in:A4,A5,Letter'],
            'orientation' => ['required', 'in:portrait,landscape'],
        ]);
        $this->ensureUniqueName($data['name']);

        $template = DocumentTemplate::query()->create([
            'public_id' => Str::uuid(),
            'created_by' => $request->user()->id,
            ...$data,
            'layout' => $this->defaultLayout(),
            'is_active' => true,
        ]);
        $this->audit->record('document_template.created', $template, new: ['name' => $template->name]);

        return redirect()->route('documents.templates.edit', $template)->with('success', 'Vorlage wurde angelegt.');
    }

    public function edit(Request $request, DocumentTemplate $template): View
    {
        $this->authorizePermission($request, 'documents.manage');

        return view('documents.editor', [
            'template' => $template,
            'placeholders' => $this->renderer->placeholders(),
            'members' => Member::query()->with('person')->whereIn('status', ['active', 'pending', 'inactive'])->orderBy('member_number')->limit(500)->get(),
        ]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $this->authorizePermission($request, 'documents.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'page_size' => ['required', 'in:A4,A5,Letter'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'layout_json' => ['required', 'string', 'max:250000'],
        ]);
        $this->ensureUniqueName($data['name'], $template);

        try {
            $layout = json_decode($data['layout_json'], true, 512, JSON_THROW_ON_ERROR);
            $layout = $this->renderer->normalizeLayout($layout);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['layout_json' => 'Das Dokumentlayout konnte nicht gespeichert werden.']);
        }

        $old = $template->only(['name', 'category', 'description', 'page_size', 'orientation', 'layout']);
        $template->update([
            'name' => trim($data['name']),
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'page_size' => $data['page_size'],
            'orientation' => $data['orientation'],
            'layout' => $layout,
        ]);
        $this->audit->record('document_template.updated', $template, old: $old, new: $template->only(['name', 'category', 'description', 'page_size', 'orientation', 'layout']));

        return back()->with('success', 'Vorlage wurde gespeichert.');
    }

    public function duplicate(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $this->authorizePermission($request, 'documents.manage');
        $name = $this->copyName($template->name);
        $copy = DocumentTemplate::query()->create([
            'public_id' => Str::uuid(),
            'created_by' => $request->user()->id,
            'name' => $name,
            'category' => $template->category,
            'description' => $template->description,
            'page_size' => $template->page_size,
            'orientation' => $template->orientation,
            'layout' => $template->layout,
            'is_active' => true,
        ]);
        $this->audit->record('document_template.duplicated', $copy, new: ['source_id' => $template->id, 'name' => $copy->name]);

        return redirect()->route('documents.templates.edit', $copy)->with('success', 'Vorlage wurde dupliziert.');
    }

    public function toggle(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $this->authorizePermission($request, 'documents.manage');
        $old = $template->is_active;
        $template->update(['is_active' => ! $template->is_active]);
        $this->audit->record('document_template.status_changed', $template, old: ['is_active' => $old], new: ['is_active' => $template->is_active]);

        return back()->with('success', 'Vorlagenstatus wurde aktualisiert.');
    }

    public function preview(Request $request, DocumentTemplate $template): Response
    {
        $this->authorizePermission($request, 'documents.generate');
        $member = $this->resolveMember($request->integer('member_id'));
        $pdf = $this->renderer->renderPdf($template, $member);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vorschau.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function generate(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $this->authorizePermission($request, 'documents.generate');
        abort_unless($template->is_active || $request->user()->is_super_admin, 403, 'Diese Vorlage ist deaktiviert.');
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:180'],
        ]);
        $member = $this->resolveMember((int) ($data['member_id'] ?? 0));
        $pdf = $this->renderer->renderPdf($template, $member);
        $title = trim((string) ($data['title'] ?? '')) ?: $template->name.($member ? ' – '.$member->person->display_name : '');
        $fileName = Str::slug($title).'-'.now()->format('Ymd-His').'.pdf';
        $publicId = Str::uuid()->toString();
        $path = 'generated-documents/'.$this->tenant->id().'/'.$publicId.'.pdf';
        Storage::disk('local')->put($path, $pdf);

        $document = GeneratedDocument::query()->create([
            'public_id' => $publicId,
            'document_template_id' => $template->id,
            'member_id' => $member?->id,
            'generated_by' => $request->user()->id,
            'title' => $title,
            'file_name' => $fileName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => strlen($pdf),
            'context' => [
                'template_name' => $template->name,
                'member_number' => $member?->member_number,
                'member_name' => $member?->person?->display_name,
            ],
            'generated_at' => now(),
        ]);
        $this->audit->record('document.generated', $document, new: [
            'template_id' => $template->id,
            'member_id' => $member?->id,
            'title' => $title,
        ]);

        return redirect()->route('documents.index')->with('success', 'PDF wurde erzeugt und sicher abgelegt.');
    }

    public function download(Request $request, GeneratedDocument $document): StreamedResponse
    {
        $this->authorizePermission($request, 'documents.view');
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Die PDF-Datei wurde nicht gefunden.');
        $this->audit->record('document.downloaded', $document);

        return Storage::disk($document->disk)->download($document->path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    private function resolveMember(int $memberId): ?Member
    {
        if ($memberId <= 0) {
            return null;
        }

        return Member::query()->with(['person', 'memberships.organizationUnit', 'memberships.memberType'])->findOrFail($memberId);
    }

    private function defaultLayout(): array
    {
        return ['blocks' => [
            ['id' => 'title', 'type' => 'text', 'x' => 20, 'y' => 20, 'w' => 170, 'h' => 16, 'text' => '{{verein.name}}', 'font_size' => 18, 'font_weight' => '700', 'align' => 'left', 'color' => '#0f172a'],
            ['id' => 'recipient', 'type' => 'text', 'x' => 20, 'y' => 55, 'w' => 90, 'h' => 32, 'text' => "{{mitglied.name}}\n{{mitglied.strasse}}\n{{mitglied.plz}} {{mitglied.ort}}", 'font_size' => 10, 'font_weight' => '400', 'align' => 'left', 'color' => '#0f172a'],
            ['id' => 'date', 'type' => 'text', 'x' => 140, 'y' => 55, 'w' => 50, 'h' => 10, 'text' => '{{datum.heute}}', 'font_size' => 10, 'font_weight' => '400', 'align' => 'right', 'color' => '#475569'],
            ['id' => 'subject', 'type' => 'text', 'x' => 20, 'y' => 100, 'w' => 170, 'h' => 14, 'text' => 'Betreff', 'font_size' => 13, 'font_weight' => '700', 'align' => 'left', 'color' => '#0f172a'],
            ['id' => 'body', 'type' => 'text', 'x' => 20, 'y' => 120, 'w' => 170, 'h' => 120, 'text' => "Sehr geehrte Damen und Herren,\n\nhier können Sie Ihren individuellen Dokumenttext hinterlegen. Mitglied: {{mitglied.name}}, Mitgliedsnummer: {{mitglied.nummer}}.\n\nMit freundlichen Grüßen\n{{verein.name}}", 'font_size' => 11, 'font_weight' => '400', 'align' => 'left', 'color' => '#0f172a'],
        ]];
    }

    private function ensureUniqueName(string $name, ?DocumentTemplate $except = null): void
    {
        $query = DocumentTemplate::withTrashed()->where('name', trim($name));
        if ($except) {
            $query->whereKeyNot($except->id);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'Eine Dokumentvorlage mit diesem Namen existiert bereits.']);
        }
    }

    private function copyName(string $name): string
    {
        for ($i = 1; $i <= 100; $i++) {
            $candidate = Str::limit($name.' – Kopie'.($i > 1 ? ' '.$i : ''), 150, '');
            if (! DocumentTemplate::withTrashed()->where('name', $candidate)->exists()) {
                return $candidate;
            }
        }

        return Str::limit($name.' – '.Str::random(6), 150, '');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->allows($request, $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function allows(Request $request, string $permission): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission);
    }
}
