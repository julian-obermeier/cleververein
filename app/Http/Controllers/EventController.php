<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\MemberSegment;
use App\Models\OrganizationUnit;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Communication\MemberAudienceService;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventSeriesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private EventSeriesService $series,
        private EventRegistrationService $registrations,
        private MemberAudienceService $audiences,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'events.view');
        $month = $request->date('month')?->startOfMonth() ?? now()->startOfMonth();
        $events = Event::query()
            ->with('organizationUnit')
            ->withCount([
                'registrations as registered_count' => fn ($q) => $q->where('status', 'registered'),
                'registrations as waitlisted_count' => fn ($q) => $q->where('status', 'waitlisted'),
            ])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('starts_at')
            ->get();

        return view('events.index', [
            'events' => $events,
            'month' => $month,
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'canManage' => $this->can($request, 'events.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'events.manage');
        $data = $request->validate([
            'organization_unit_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'event_type' => ['required', Rule::in(['event', 'meeting', 'training', 'celebration', 'trip', 'assembly', 'other'])],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:220'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'registration_enabled' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'waitlist_enabled' => ['nullable', 'boolean'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'recurrence_type' => ['required', Rule::in(['none', 'daily', 'weekly', 'monthly'])],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'recurrence_count' => ['nullable', 'integer', 'min:1', 'max:104'],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        if (($data['recurrence_type'] ?? 'none') !== 'none' && blank($data['recurrence_count'] ?? null)) {
            throw ValidationException::withMessages(['recurrence_count' => 'Für Serientermine muss eine Anzahl angegeben werden.']);
        }
        $organization = filled($data['organization_unit_id'] ?? null) ? OrganizationUnit::query()->findOrFail($data['organization_unit_id']) : null;
        $this->authorizePermission($request, 'events.manage', $organization?->id);

        $event = $this->series->create($data, $request->user()->id);
        $this->audit->record('event.created', $event, new: $event->only(['title', 'starts_at', 'event_type', 'organization_unit_id', 'event_series_id']));

        return redirect()->route('events.show', $event)->with('success', 'Veranstaltung wurde angelegt.');
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorizePermission($request, 'events.view', $event->organization_unit_id);
        $event->load([
            'organizationUnit', 'series',
            'registrations' => fn ($q) => $q->with('member.person')->orderByRaw("CASE status WHEN 'registered' THEN 0 WHEN 'waitlisted' THEN 1 WHEN 'invited' THEN 2 ELSE 3 END")->orderBy('id'),
        ]);

        return view('events.show', [
            'event' => $event,
            'segments' => MemberSegment::query()->where('is_active', true)->orderBy('name')->get(),
            'organizations' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(500)->get(),
            'canManage' => $this->can($request, 'events.manage', $event->organization_unit_id),
            'canRegistrations' => $this->can($request, 'events.registrations', $event->organization_unit_id),
            'canAttendance' => $this->can($request, 'events.attendance', $event->organization_unit_id),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizePermission($request, 'events.manage', $event->organization_unit_id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:220'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(['scheduled', 'cancelled', 'completed'])],
            'registration_enabled' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'waitlist_enabled' => ['nullable', 'boolean'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $old = $event->only(array_keys($data));
        $event->update([
            ...$data,
            'registration_enabled' => (bool) ($data['registration_enabled'] ?? false),
            'waitlist_enabled' => (bool) ($data['waitlist_enabled'] ?? false),
        ]);
        $this->audit->record('event.updated', $event, old: $old, new: $event->only(array_keys($data)));

        return back()->with('success', 'Veranstaltung wurde aktualisiert.');
    }

    public function inviteAudience(Request $request, Event $event): RedirectResponse
    {
        $this->authorizePermission($request, 'events.registrations', $event->organization_unit_id);
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['all_active', 'segment', 'organization', 'member'])],
            'member_segment_id' => ['nullable', 'integer'],
            'organization_unit_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
        ]);

        $query = match ($data['target_type']) {
            'segment' => $this->audiences->query(MemberSegment::query()->findOrFail($data['member_segment_id'])->criteria ?? []),
            'organization' => $this->audiences->query(['status' => 'active', 'organization_unit_id' => OrganizationUnit::query()->findOrFail($data['organization_unit_id'])->id]),
            'member' => Member::query()->whereKey(Member::query()->findOrFail($data['member_id'])->id),
            default => $this->audiences->query(['status' => 'active']),
        };

        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($members) use ($event, &$count): void {
            foreach ($members as $member) {
                $before = EventRegistration::query()->where('event_id', $event->id)->where('member_id', $member->id)->exists();
                $this->registrations->inviteMember($event, $member);
                if (! $before) {
                    $count++;
                }
            }
        });
        $this->audit->record('event.audience_invited', $event, new: ['added' => $count, 'target_type' => $data['target_type']]);

        return back()->with('success', $count.' neue Einladung(en) wurden vorgemerkt.');
    }

    public function addGuest(Request $request, Event $event): RedirectResponse
    {
        $this->authorizePermission($request, 'events.registrations', $event->organization_unit_id);
        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:180'],
            'guest_email' => ['required', 'email', 'max:255'],
        ]);
        $registration = $this->registrations->addGuest($event, $data['guest_name'], $data['guest_email']);
        $this->audit->record('event.guest_invited', $registration, new: ['event_id' => $event->id, 'guest_name' => $data['guest_name']]);

        return back()->with('success', 'Gast wurde hinzugefügt.');
    }

    public function respond(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorizePermission($request, 'events.registrations', $event->organization_unit_id);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['response' => ['required', Rule::in(['registered', 'declined'])]]);
        $old = $registration->status;
        $updated = $this->registrations->respond($registration, $data['response']);
        $this->audit->record('event.registration_updated', $updated, old: ['status' => $old], new: ['status' => $updated->status]);

        return back()->with('success', 'Teilnahmestatus wurde aktualisiert.');
    }

    public function attendance(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorizePermission($request, 'events.attendance', $event->organization_unit_id);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['attendance_status' => ['required', Rule::in(['unknown', 'present', 'absent', 'excused'])]]);
        $registration->update([
            'attendance_status' => $data['attendance_status'],
            'checked_in_at' => $data['attendance_status'] === 'present' ? now() : null,
        ]);
        $this->audit->record('event.attendance_updated', $registration, new: ['attendance_status' => $registration->attendance_status]);

        return back()->with('success', 'Anwesenheit wurde gespeichert.');
    }

    public function participantsCsv(Request $request, Event $event): StreamedResponse
    {
        $this->authorizePermission($request, 'events.registrations', $event->organization_unit_id);
        $rows = $event->registrations()->with('member.person')->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'E-Mail', 'Status', 'Anwesenheit'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->member?->person?->display_name ?: $row->guest_name,
                    $row->member?->person?->email ?: $row->guest_email,
                    $row->status,
                    $row->attendance_status,
                ], ';');
            }
            fclose($out);
        }, 'teilnehmer-'.$event->starts_at->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function ics(Request $request, Event $event): Response
    {
        $this->authorizePermission($request, 'events.view', $event->organization_unit_id);
        $escape = fn (?string $value) => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", '\\;', '\\,', '', '\\n'], (string) $value);
        $body = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//cleververein//Event//DE', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT', 'UID:'.$event->public_id.'@cleververein',
            'DTSTAMP:'.now()->utc()->format('Ymd\\THis\\Z'),
            'DTSTART:'.$event->starts_at->utc()->format('Ymd\\THis\\Z'),
            $event->ends_at ? 'DTEND:'.$event->ends_at->utc()->format('Ymd\\THis\\Z') : null,
            'SUMMARY:'.$escape($event->title),
            filled($event->location) ? 'LOCATION:'.$escape($event->location) : null,
            filled($event->description) ? 'DESCRIPTION:'.$escape($event->description) : null,
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response(collect(explode("\r\n", $body))->filter(fn ($line) => $line !== '')->implode("\r\n")."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="veranstaltung.ics"',
        ]);
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
