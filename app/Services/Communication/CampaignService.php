<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationRecipient;
use App\Models\EventRegistration;
use App\Models\MemberCommunication;
use App\Models\MemberSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CampaignService
{
    public function __construct(private MemberAudienceService $audiences) {}

    public function prepare(CommunicationCampaign $campaign): int
    {
        if (! in_array($campaign->status, ['draft', 'prepared'], true)) {
            throw new RuntimeException('Diese Kampagne kann nicht erneut vorbereitet werden.');
        }

        return DB::transaction(function () use ($campaign): int {
            $campaign->recipients()->delete();
            $count = 0;

            if ($campaign->target_type === 'event_registrations') {
                abort_unless($campaign->event_id, 422, 'Für Veranstaltungseinladungen fehlt die Veranstaltung.');
                EventRegistration::query()
                    ->where('event_id', $campaign->event_id)
                    ->with('member.person')
                    ->orderBy('id')
                    ->chunkById(200, function ($registrations) use ($campaign, &$count): void {
                        foreach ($registrations as $registration) {
                            $email = $registration->member?->person?->email ?: $registration->guest_email;
                            $name = $registration->member?->person?->display_name ?: $registration->guest_name;
                            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || blank($name)) {
                                continue;
                            }
                            $this->addRecipient($campaign, $name, $email, $registration->member_id, $registration->id);
                            $count++;
                        }
                    });
            } else {
                $query = match ($campaign->target_type) {
                    'segment' => $this->audiences->query(MemberSegment::query()->findOrFail($campaign->member_segment_id)->criteria ?? []),
                    'organization' => $this->audiences->query(['status' => 'active', 'organization_unit_id' => $campaign->organization_unit_id]),
                    default => $this->audiences->query(['status' => 'active']),
                };
                $query->with('person')->orderBy('id')->chunkById(200, function ($members) use ($campaign, &$count): void {
                    foreach ($members as $member) {
                        $email = $member->person?->email;
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }
                        $this->addRecipient($campaign, $member->person->display_name, $email, $member->id, null);
                        $count++;
                    }
                });
            }

            $campaign->update([
                'status' => 'prepared',
                'recipient_count' => $count,
                'sent_count' => 0,
                'failed_count' => 0,
                'prepared_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ]);

            return $count;
        });
    }

    public function sendBatch(CommunicationCampaign $campaign, int $limit = 50): array
    {
        if (! in_array($campaign->status, ['prepared', 'sending'], true)) {
            throw new RuntimeException('Die Kampagne muss zuerst vorbereitet werden.');
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => $campaign->started_at ?: now(),
        ]);

        $recipients = $campaign->recipients()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 3)
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->increment('attempts');
            try {
                $subject = $this->render($campaign->subject, $recipient, $campaign);
                $body = $this->render($campaign->body, $recipient, $campaign);
                Mail::raw($body, fn ($message) => $message->to($recipient->recipient_email, $recipient->recipient_name)->subject($subject));
                $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);

                if ($recipient->member_id) {
                    MemberCommunication::query()->create([
                        'member_id' => $recipient->member_id,
                        'user_id' => $campaign->created_by,
                        'channel' => 'email',
                        'direction' => 'outbound',
                        'subject' => $subject,
                        'body' => $body,
                        'outcome' => 'Versendet über Kampagne '.$campaign->name,
                        'occurred_at' => now(),
                    ]);
                }
                if ($recipient->event_registration_id) {
                    EventRegistration::query()->whereKey($recipient->event_registration_id)->whereNull('invited_at')->update(['invited_at' => now()]);
                }
            } catch (Throwable $exception) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($exception->getMessage(), 2000, ''),
                ]);
            }
        }

        $sent = $campaign->recipients()->where('status', 'sent')->count();
        $failed = $campaign->recipients()->where('status', 'failed')->count();
        $remaining = $campaign->recipients()->whereIn('status', ['pending', 'failed'])->where('attempts', '<', 3)->count();
        $campaign->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'status' => $remaining === 0 ? 'completed' : 'sending',
            'completed_at' => $remaining === 0 ? now() : null,
        ]);

        return compact('sent', 'failed', 'remaining');
    }

    private function addRecipient(CommunicationCampaign $campaign, string $name, string $email, ?int $memberId, ?int $registrationId): void
    {
        CommunicationRecipient::query()->firstOrCreate(
            ['campaign_id' => $campaign->id, 'recipient_email' => mb_strtolower(trim($email))],
            [
                'member_id' => $memberId,
                'event_registration_id' => $registrationId,
                'recipient_name' => $name,
                'status' => 'pending',
                'attempts' => 0,
            ],
        );
    }

    private function render(string $text, CommunicationRecipient $recipient, CommunicationCampaign $campaign): string
    {
        $registration = $recipient->event_registration_id ? EventRegistration::query()->with('event')->find($recipient->event_registration_id) : null;
        $member = $recipient->member_id ? $recipient->member()->with('person')->first() : null;
        $event = $campaign->event ?: $registration?->event;

        return strtr($text, [
            '{{empfaenger.name}}' => $recipient->recipient_name,
            '{{mitglied.name}}' => $member?->person?->display_name ?? $recipient->recipient_name,
            '{{mitglied.vorname}}' => $member?->person?->first_name ?? '',
            '{{mitglied.nummer}}' => $member?->member_number ?? '',
            '{{veranstaltung.titel}}' => $event?->title ?? '',
            '{{veranstaltung.datum}}' => $event?->starts_at?->format('d.m.Y H:i') ?? '',
            '{{veranstaltung.ort}}' => $event?->location ?? '',
            '{{anmeldung.link}}' => $registration ? route('events.rsvp.show', $registration->response_token) : '',
        ]);
    }
}
