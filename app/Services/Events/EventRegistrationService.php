<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventRegistrationService
{
    public function inviteMember(Event $event, Member $member): EventRegistration
    {
        return EventRegistration::query()->firstOrCreate(
            ['event_id' => $event->id, 'member_id' => $member->id],
            [
                'public_id' => Str::uuid(),
                'status' => 'invited',
                'attendance_status' => 'unknown',
                'response_token' => Str::random(64),
            ],
        );
    }

    public function addGuest(Event $event, string $name, string $email, string $status = 'invited'): EventRegistration
    {
        return EventRegistration::query()->create([
            'public_id' => Str::uuid(),
            'event_id' => $event->id,
            'guest_name' => trim($name),
            'guest_email' => mb_strtolower(trim($email)),
            'status' => $status,
            'attendance_status' => 'unknown',
            'response_token' => Str::random(64),
        ]);
    }

    public function respond(EventRegistration $registration, string $response): EventRegistration
    {
        return DB::transaction(function () use ($registration, $response): EventRegistration {
            $registration->refresh();
            $event = Event::query()->lockForUpdate()->findOrFail($registration->event_id);

            if (! $event->registration_enabled) {
                throw ValidationException::withMessages(['response' => 'Für diese Veranstaltung sind Anmeldungen deaktiviert.']);
            }
            if ($event->registration_deadline && now()->isAfter($event->registration_deadline)) {
                throw ValidationException::withMessages(['response' => 'Die Anmeldefrist ist abgelaufen.']);
            }
            if (in_array($event->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['response' => 'Für diese Veranstaltung sind keine Änderungen mehr möglich.']);
            }

            $oldStatus = $registration->status;
            if ($response === 'declined') {
                $registration->update(['status' => 'declined', 'responded_at' => now()]);
                if (in_array($oldStatus, ['registered'], true)) {
                    $this->promoteWaitlist($event);
                }

                return $registration->fresh();
            }

            $confirmed = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', 'registered')
                ->whereKeyNot($registration->id)
                ->count();

            if ($event->capacity !== null && $confirmed >= $event->capacity) {
                if (! $event->waitlist_enabled) {
                    throw ValidationException::withMessages(['response' => 'Die Veranstaltung ist ausgebucht und hat keine Warteliste.']);
                }
                $registration->update(['status' => 'waitlisted', 'responded_at' => now()]);
            } else {
                $registration->update(['status' => 'registered', 'responded_at' => now()]);
            }

            return $registration->fresh();
        });
    }

    public function promoteWaitlist(Event $event): void
    {
        if ($event->capacity === null) {
            return;
        }
        $registered = EventRegistration::query()->where('event_id', $event->id)->where('status', 'registered')->count();
        $available = max(0, $event->capacity - $registered);
        if ($available === 0) {
            return;
        }

        EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', 'waitlisted')
            ->orderBy('responded_at')
            ->orderBy('id')
            ->limit($available)
            ->get()
            ->each(fn (EventRegistration $registration) => $registration->update(['status' => 'registered']));
    }
}
