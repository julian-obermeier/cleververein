<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventSeriesService
{
    public function create(array $data, int $userId): Event
    {
        return DB::transaction(function () use ($data, $userId): Event {
            $startsAt = CarbonImmutable::parse($data['starts_at']);
            $endsAt = filled($data['ends_at'] ?? null) ? CarbonImmutable::parse($data['ends_at']) : null;
            $recurrence = $data['recurrence_type'] ?? 'none';
            $series = null;

            if ($recurrence !== 'none') {
                $series = EventSeries::query()->create([
                    'public_id' => Str::uuid(),
                    'organization_unit_id' => $data['organization_unit_id'] ?? null,
                    'title' => $data['title'],
                    'event_type' => $data['event_type'],
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'online_url' => $data['online_url'] ?? null,
                    'recurrence_type' => $recurrence,
                    'recurrence_interval' => $data['recurrence_interval'] ?? 1,
                    'recurrence_count' => $data['recurrence_count'] ?? null,
                    'recurrence_until' => $data['recurrence_until'] ?? null,
                    'registration_enabled' => (bool) ($data['registration_enabled'] ?? false),
                    'capacity' => $data['capacity'] ?? null,
                    'waitlist_enabled' => (bool) ($data['waitlist_enabled'] ?? false),
                    'status' => 'active',
                    'created_by' => $userId,
                ]);
            }

            $first = null;
            $count = $recurrence === 'none' ? 1 : (int) ($data['recurrence_count'] ?? 1);
            $until = filled($data['recurrence_until'] ?? null) ? CarbonImmutable::parse($data['recurrence_until'])->endOfDay() : null;
            $interval = max(1, (int) ($data['recurrence_interval'] ?? 1));
            $durationSeconds = $endsAt ? $startsAt->diffInSeconds($endsAt) : null;

            for ($i = 0; $i < $count; $i++) {
                $occurrenceStart = $this->advance($startsAt, $recurrence, $interval, $i);
                if ($until && $occurrenceStart->greaterThan($until)) {
                    break;
                }

                $event = Event::query()->create([
                    'public_id' => Str::uuid(),
                    'event_series_id' => $series?->id,
                    'organization_unit_id' => $data['organization_unit_id'] ?? null,
                    'title' => $data['title'],
                    'event_type' => $data['event_type'],
                    'description' => $data['description'] ?? null,
                    'starts_at' => $occurrenceStart,
                    'ends_at' => $durationSeconds !== null ? $occurrenceStart->addSeconds($durationSeconds) : null,
                    'location' => $data['location'] ?? null,
                    'online_url' => $data['online_url'] ?? null,
                    'status' => 'scheduled',
                    'registration_enabled' => (bool) ($data['registration_enabled'] ?? false),
                    'capacity' => $data['capacity'] ?? null,
                    'waitlist_enabled' => (bool) ($data['waitlist_enabled'] ?? false),
                    'registration_deadline' => $data['registration_deadline'] ?? null,
                    'created_by' => $userId,
                ]);
                $first ??= $event;
            }

            return $first ?? throw new \RuntimeException('Es konnte kein Veranstaltungstermin erzeugt werden.');
        });
    }

    private function advance(CarbonImmutable $start, string $type, int $interval, int $index): CarbonImmutable
    {
        $amount = $interval * $index;

        return match ($type) {
            'daily' => $start->addDays($amount),
            'weekly' => $start->addWeeks($amount),
            'monthly' => $start->addMonthsNoOverflow($amount),
            default => $start,
        };
    }
}
