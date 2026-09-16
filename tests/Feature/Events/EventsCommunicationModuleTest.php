<?php

namespace Tests\Feature\Events;

use App\Models\CommunicationCampaign;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventsCommunicationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('app/installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed'));
        parent::tearDown();
    }

    public function test_weekly_series_creates_requested_occurrences(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Jugendübung',
            'event_type' => 'training',
            'starts_at' => '2026-09-21 17:00:00',
            'ends_at' => '2026-09-21 18:30:00',
            'registration_enabled' => 1,
            'waitlist_enabled' => 1,
            'recurrence_type' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_count' => 3,
        ])->assertRedirect();

        $this->assertDatabaseCount('event_series', 1);
        $this->assertDatabaseCount('events', 3);
        $events = Event::query()->orderBy('starts_at')->get();
        $this->assertSame('2026-09-21 17:00:00', $events[0]->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-28 17:00:00', $events[1]->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 17:00:00', $events[2]->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_public_rsvp_uses_capacity_waitlist_and_promotes_oldest_waitlisted(): void
    {
        [, $user] = $this->tenantUser();
        $first = $this->member('M-EVT-001', 'anna@example.test', 'Anna');
        $second = $this->member('M-EVT-002', 'ben@example.test', 'Ben');
        $event = $this->event($user, 1);

        $this->actingAs($user)->post(route('events.invite', $event), ['target_type' => 'all_active'])->assertRedirect();
        $firstRegistration = EventRegistration::query()->where('member_id', $first->id)->firstOrFail();
        $secondRegistration = EventRegistration::query()->where('member_id', $second->id)->firstOrFail();

        app(TenantContext::class)->clear();
        $this->post(route('events.rsvp.update', $firstRegistration->response_token), ['response' => 'registered'])->assertRedirect();
        app(TenantContext::class)->clear();
        $this->post(route('events.rsvp.update', $secondRegistration->response_token), ['response' => 'registered'])->assertRedirect();

        $this->assertSame('registered', $firstRegistration->fresh()->status);
        $this->assertSame('waitlisted', $secondRegistration->fresh()->status);

        app(TenantContext::class)->clear();
        $this->post(route('events.rsvp.update', $firstRegistration->response_token), ['response' => 'declined'])->assertRedirect();
        $this->assertSame('declined', $firstRegistration->fresh()->status);
        $this->assertSame('registered', $secondRegistration->fresh()->status);
    }

    public function test_campaign_prepares_snapshot_and_sends_without_duplicate_delivery(): void
    {
        Mail::fake();
        [, $user] = $this->tenantUser();
        $member = $this->member('M-COM-001', 'max@example.test', 'Max');

        $this->actingAs($user)->post(route('communications.campaigns.store'), [
            'name' => 'September-Info',
            'target_type' => 'all_active',
            'subject' => 'Hallo {{mitglied.vorname}}',
            'body' => 'Mitgliedsnummer: {{mitglied.nummer}}',
        ])->assertRedirect();
        $campaign = CommunicationCampaign::query()->firstOrFail();

        $this->actingAs($user)->post(route('communications.campaigns.prepare', $campaign))->assertRedirect();
        $this->assertDatabaseHas('communication_recipients', [
            'campaign_id' => $campaign->id,
            'member_id' => $member->id,
            'recipient_email' => 'max@example.test',
            'status' => 'pending',
        ]);

        $member->person->update(['email' => 'changed@example.test']);
        $this->actingAs($user)->post(route('communications.campaigns.send', $campaign), ['limit' => 50])->assertRedirect();
        $this->assertDatabaseHas('communication_recipients', [
            'campaign_id' => $campaign->id,
            'recipient_email' => 'max@example.test',
            'status' => 'sent',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('member_communications', [
            'member_id' => $member->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'subject' => 'Hallo Max',
        ]);
        $this->assertSame('completed', $campaign->fresh()->status);
    }

    public function test_communication_workspace_renders_with_placeholder_examples(): void
    {
        [, $user] = $this->tenantUser();
        $event = $this->event($user, null, 'Herbstversammlung');

        $this->actingAs($user)->get(route('communications.index', ['event' => $event->id]))
            ->assertOk()
            ->assertSeeText('E-Mail-Vorlagen & Kampagnen')
            ->assertSee('Einladung: {{veranstaltung.titel}}', false)
            ->assertSee('{{mitglied.vorname}}', false)
            ->assertSee('{{anmeldung.link}}', false);
    }

    public function test_event_workspace_is_tenant_isolated(): void
    {
        [$tenantA, $userA] = $this->tenantUser('events-a');
        $eventA = $this->event($userA, null, 'Nur A');

        app(TenantContext::class)->clear();
        [, $userB] = $this->tenantUser('events-b');
        $this->event($userB, null, 'Nur B');

        app(TenantContext::class)->set($tenantA);
        $this->actingAs($userA)->get(route('events.index', ['month' => $eventA->starts_at->startOfMonth()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Nur A')
            ->assertDontSee('Nur B');
    }

    public function test_ics_export_contains_event_identity(): void
    {
        [, $user] = $this->tenantUser();
        $event = $this->event($user, null, 'Sommerfest');

        $this->actingAs($user)->get(route('events.ics', $event))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
            ->assertSee('BEGIN:VCALENDAR', false)
            ->assertSee('SUMMARY:Sommerfest', false);
    }

    private function event(User $user, ?int $capacity = null, string $title = 'Testveranstaltung'): Event
    {
        return Event::query()->create([
            'public_id' => Str::uuid(),
            'title' => $title,
            'event_type' => 'event',
            'starts_at' => '2026-09-25 18:00:00',
            'ends_at' => '2026-09-25 20:00:00',
            'status' => 'scheduled',
            'registration_enabled' => true,
            'capacity' => $capacity,
            'waitlist_enabled' => true,
            'created_by' => $user->id,
        ]);
    }

    private function member(string $number, string $email, string $firstName): Member
    {
        $person = Person::factory()->create(['first_name' => $firstName, 'last_name' => 'Test', 'email' => $email]);

        return Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => $number,
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
    }

    private function tenantUser(string $slug = 'eventverein'): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
