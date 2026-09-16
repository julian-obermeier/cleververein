<?php

namespace Tests\Feature\Members;

use App\Models\CustomFieldDefinition;
use App\Models\Member;
use App\Models\MemberTag;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberCrmToolsTest extends TestCase
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

    public function test_super_admin_can_open_member_crm_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $member = $this->member($tenant);

        $this->actingAs($user)->get(route('members.crm', $member))
            ->assertOk()
            ->assertSeeText('Kommunikationshistorie')
            ->assertSeeText('Benutzerdefinierte Felder');
    }

    public function test_tags_can_be_created_assigned_and_removed(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $member = $this->member($tenant);

        $this->actingAs($user)->post(route('members.tags.store'), ['name' => 'Jubiläum'])
            ->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $tag = MemberTag::query()->where('name', 'Jubiläum')->firstOrFail();
        app(TenantContext::class)->clear();

        $this->actingAs($user)->post(route('members.crm.tags.store', $member), ['member_tag_id' => $tag->id])
            ->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $this->assertTrue($member->fresh()->tags()->whereKey($tag->id)->exists());
        app(TenantContext::class)->clear();

        $this->actingAs($user)->delete(route('members.crm.tags.destroy', [$member, $tag]))
            ->assertRedirect();
    }

    public function test_custom_field_values_are_saved_for_member(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $member = $this->member($tenant);

        app(TenantContext::class)->set($tenant);
        $field = CustomFieldDefinition::query()->create([
            'entity_type' => 'member',
            'name' => 'T-Shirt-Größe',
            'key' => 'shirt_size',
            'field_type' => 'select',
            'options' => ['S', 'M', 'L'],
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->put(route('members.crm.custom-fields.update', $member), [
            'custom_fields' => [$field->id => 'M'],
        ])->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $this->assertSame('M', $member->fresh()->customFieldValues()->where('custom_field_definition_id', $field->id)->value('value'));
        app(TenantContext::class)->clear();
    }

    public function test_private_member_document_can_be_uploaded_and_downloaded(): void
    {
        Storage::fake('local');
        [$tenant, $user] = $this->tenantUser();
        $member = $this->member($tenant);

        $this->actingAs($user)->post(route('members.crm.documents.store', $member), [
            'title' => 'Aufnahmeantrag',
            'category' => 'Antrag',
            'file' => UploadedFile::fake()->create('antrag.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $document = $member->fresh()->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get(route('members.crm.documents.download', [$member, $document]))
            ->assertOk();
    }

    public function test_communication_segment_and_bulk_status_work(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $member = $this->member($tenant);

        $this->actingAs($user)->post(route('members.crm.communications.store', $member), [
            'channel' => 'phone',
            'direction' => 'outbound',
            'subject' => 'Rückfrage',
            'body' => 'Mitglied telefonisch erreicht.',
            'occurred_at' => '2026-09-16 15:00:00',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('members.segments.store'), [
            'name' => 'Aktive Mitglieder',
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($user)->get(route('members.segments.index'))
            ->assertOk()
            ->assertSeeText('Aktive Mitglieder');

        $this->actingAs($user)->post(route('members.bulk'), [
            'members' => [$member->id],
            'action' => 'set_status',
            'status' => 'inactive',
        ])->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertSame(1, $member->fresh()->communications()->count());
        app(TenantContext::class)->clear();
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Testverein',
            'slug' => 'crm-test-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);

        return [$tenant, $user];
    }

    private function member(Tenant $tenant): Member
    {
        app(TenantContext::class)->set($tenant);
        $person = Person::factory()->create(['first_name' => 'CRM', 'last_name' => 'Mitglied']);
        $member = Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => 'CRM-'.Str::upper(Str::random(5)),
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
        app(TenantContext::class)->clear();

        return $member;
    }
}
