<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use App\Models\Tenant;
use App\Services\Audit\AuditService;
use App\Services\Events\EventRegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventRsvpController extends Controller
{
    public function __construct(
        private TenantContext $tenant,
        private EventRegistrationService $registrations,
        private AuditService $audit,
    ) {}

    public function show(string $token): View
    {
        $registration = $this->registration($token);
        $registration->load(['event', 'member.person']);

        return view('events.rsvp', compact('registration'));
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $registration = $this->registration($token);
        $data = $request->validate(['response' => ['required', Rule::in(['registered', 'declined'])]]);
        $old = $registration->status;
        $updated = $this->registrations->respond($registration, $data['response']);
        $this->audit->record('event.public_response', $updated, old: ['status' => $old], new: ['status' => $updated->status]);

        return redirect()->route('events.rsvp.show', $token)->with('success', 'Deine Rückmeldung wurde gespeichert.');
    }

    private function registration(string $token): EventRegistration
    {
        $registration = EventRegistration::withoutGlobalScopes()->where('response_token', $token)->firstOrFail();
        $tenant = Tenant::query()->findOrFail($registration->tenant_id);
        $this->tenant->set($tenant);

        return EventRegistration::query()->whereKey($registration->id)->firstOrFail();
    }
}
