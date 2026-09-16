<?php

namespace App\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\MemberSegment;
use App\Models\OrganizationUnit;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Communication\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private CampaignService $campaigns,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'communications.view');

        return view('communications.index', [
            'templates' => CommunicationTemplate::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'campaigns' => CommunicationCampaign::query()->with(['event', 'segment', 'organizationUnit'])->latest()->limit(50)->get(),
            'segments' => MemberSegment::query()->where('is_active', true)->orderBy('name')->get(),
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'events' => Event::query()->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->limit(100)->get(),
            'canManage' => $this->can($request, 'communications.manage'),
            'canSend' => $this->can($request, 'communications.send'),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'communications.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ]);
        if (CommunicationTemplate::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
            throw ValidationException::withMessages(['name' => 'Eine Vorlage mit diesem Namen existiert bereits.']);
        }
        $template = CommunicationTemplate::query()->create([
            'public_id' => Str::uuid(),
            ...$data,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->record('communication.template_created', $template, new: ['name' => $template->name]);

        return back()->with('success', 'E-Mail-Vorlage wurde angelegt.');
    }

    public function toggleTemplate(Request $request, CommunicationTemplate $template): RedirectResponse
    {
        $this->authorizePermission($request, 'communications.manage');
        $template->update(['is_active' => ! $template->is_active]);
        $this->audit->record('communication.template_updated', $template, new: ['is_active' => $template->is_active]);

        return back()->with('success', 'Vorlagenstatus wurde geändert.');
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'communications.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'template_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'],
            'target_type' => ['required', Rule::in(['all_active', 'segment', 'organization', 'event_registrations'])],
            'member_segment_id' => ['nullable', 'integer'],
            'organization_unit_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ]);

        $template = filled($data['template_id'] ?? null) ? CommunicationTemplate::query()->findOrFail($data['template_id']) : null;
        $event = filled($data['event_id'] ?? null) ? Event::query()->findOrFail($data['event_id']) : null;
        $segment = filled($data['member_segment_id'] ?? null) ? MemberSegment::query()->findOrFail($data['member_segment_id']) : null;
        $organization = filled($data['organization_unit_id'] ?? null) ? OrganizationUnit::query()->findOrFail($data['organization_unit_id']) : null;

        if ($data['target_type'] === 'segment' && ! $segment) {
            throw ValidationException::withMessages(['member_segment_id' => 'Bitte ein Segment auswählen.']);
        }
        if ($data['target_type'] === 'organization' && ! $organization) {
            throw ValidationException::withMessages(['organization_unit_id' => 'Bitte eine Gliederung auswählen.']);
        }
        if ($data['target_type'] === 'event_registrations' && ! $event) {
            throw ValidationException::withMessages(['event_id' => 'Bitte eine Veranstaltung auswählen.']);
        }
        $this->authorizePermission($request, 'communications.manage', $organization?->id ?? $event?->organization_unit_id);

        $campaign = CommunicationCampaign::query()->create([
            'public_id' => Str::uuid(),
            'event_id' => $event?->id,
            'template_id' => $template?->id,
            'member_segment_id' => $segment?->id,
            'organization_unit_id' => $organization?->id,
            'name' => $data['name'],
            'channel' => 'email',
            'target_type' => $data['target_type'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        $this->audit->record('communication.campaign_created', $campaign, new: $campaign->only(['name', 'target_type', 'event_id', 'organization_unit_id']));

        return redirect()->route('communications.campaigns.show', $campaign)->with('success', 'Kampagne wurde als Entwurf angelegt.');
    }

    public function showCampaign(Request $request, CommunicationCampaign $campaign): View
    {
        $this->authorizePermission($request, 'communications.view', $campaign->organization_unit_id ?? $campaign->event?->organization_unit_id);
        $campaign->load(['event', 'segment', 'organizationUnit', 'recipients' => fn ($q) => $q->orderBy('status')->orderBy('recipient_name')]);

        return view('communications.show', [
            'campaign' => $campaign,
            'canManage' => $this->can($request, 'communications.manage', $campaign->organization_unit_id ?? $campaign->event?->organization_unit_id),
            'canSend' => $this->can($request, 'communications.send', $campaign->organization_unit_id ?? $campaign->event?->organization_unit_id),
        ]);
    }

    public function prepare(Request $request, CommunicationCampaign $campaign): RedirectResponse
    {
        $this->authorizePermission($request, 'communications.manage', $campaign->organization_unit_id ?? $campaign->event?->organization_unit_id);
        $count = $this->campaigns->prepare($campaign);
        $this->audit->record('communication.campaign_prepared', $campaign, new: ['recipient_count' => $count]);

        return back()->with('success', $count.' Empfänger wurden eingefroren.');
    }

    public function sendBatch(Request $request, CommunicationCampaign $campaign): RedirectResponse
    {
        $this->authorizePermission($request, 'communications.send', $campaign->organization_unit_id ?? $campaign->event?->organization_unit_id);
        $data = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $result = $this->campaigns->sendBatch($campaign, (int) ($data['limit'] ?? 50));
        $this->audit->record('communication.campaign_batch_sent', $campaign, new: $result);

        return back()->with('success', "Versandstand: {$result['sent']} gesendet, {$result['failed']} fehlgeschlagen, {$result['remaining']} offen.");
    }

    private function authorizePermission(Request $request, string $permission, ?int $organizationId = null): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), $permission, $organizationId), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function can(Request $request, string $permission, ?int $organizationId = null): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission, $organizationId);
    }
}
