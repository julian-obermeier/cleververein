<?php

namespace Tests\Feature\Documents;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentTemplateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentGeneratorTest extends TestCase
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

    public function test_super_admin_can_open_document_workspace(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Dokumenten-Generator')
            ->assertSee('Neue Vorlage');
    }

    public function test_template_can_be_created_and_edited(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $response = $this->actingAs($user)->post(route('documents.templates.store'), [
            'name' => 'Mitgliedsbescheinigung',
            'category' => 'Bescheinigung',
            'description' => 'Standardvorlage',
            'page_size' => 'A4',
            'orientation' => 'portrait',
        ]);

        $template = DocumentTemplate::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($template);
        $response->assertRedirect(route('documents.templates.edit', $template));

        $layout = ['blocks' => [[
            'id' => 'headline', 'type' => 'text', 'x' => 20, 'y' => 20, 'w' => 160, 'h' => 20,
            'text' => 'Bescheinigung für {{mitglied.name}}', 'font_size' => 16, 'font_weight' => '700', 'align' => 'left', 'color' => '#0f172a',
        ]]];

        $this->actingAs($user)->put(route('documents.templates.update', $template), [
            'name' => 'Mitgliedsbescheinigung',
            'category' => 'Bescheinigung',
            'description' => 'Aktualisiert',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'layout_json' => json_encode($layout, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $template->refresh();
        $this->assertSame('Bescheinigung für {{mitglied.name}}', $template->layout['blocks'][0]['text']);
    }

    public function test_placeholders_are_resolved_for_current_member_and_tenant(): void
    {
        [$tenant] = $this->tenantUser();
        $person = Person::factory()->create(['first_name' => 'Erika', 'last_name' => 'Musterfrau']);
        $member = Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => 'M-100',
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
        $template = DocumentTemplate::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Test',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'layout' => ['blocks' => [[
                'id' => 'text', 'type' => 'text', 'x' => 20, 'y' => 20, 'w' => 160, 'h' => 20,
                'text' => '{{verein.name}} · {{mitglied.name}} · {{mitglied.nummer}}', 'font_size' => 11,
                'font_weight' => '400', 'align' => 'left', 'color' => '#0f172a',
            ]]],
            'is_active' => true,
        ]);

        $html = app(DocumentTemplateService::class)->renderHtml($template, $member->load('person', 'memberships'));

        $this->assertStringContainsString(e($tenant->name), $html);
        $this->assertStringContainsString('Erika Musterfrau', $html);
        $this->assertStringContainsString('M-100', $html);
        $this->assertStringNotContainsString('{{mitglied.name}}', $html);
    }

    public function test_pdf_can_be_generated_and_is_stored_privately(): void
    {
        Storage::fake('local');
        [$tenant, $user] = $this->tenantUser();
        $person = Person::factory()->create(['first_name' => 'Max', 'last_name' => 'Mustermann']);
        $member = Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => 'M-200',
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
        $template = DocumentTemplate::query()->create([
            'public_id' => Str::uuid(),
            'created_by' => $user->id,
            'name' => 'Bestätigung',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'layout' => ['blocks' => [[
                'id' => 'text', 'type' => 'text', 'x' => 20, 'y' => 20, 'w' => 160, 'h' => 30,
                'text' => 'Hallo {{mitglied.name}}', 'font_size' => 12, 'font_weight' => '400', 'align' => 'left', 'color' => '#0f172a',
            ]]],
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('documents.templates.generate', $template), [
            'member_id' => $member->id,
            'title' => 'Bestätigung Max Mustermann',
        ])->assertRedirect(route('documents.index'));

        $document = GeneratedDocument::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($document);
        $this->assertSame($member->id, $document->member_id);
        $this->assertGreaterThan(100, $document->size);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_other_tenant_template_is_not_route_bindable(): void
    {
        [$tenantA, $user] = $this->tenantUser('Verein A', 'verein-a');
        $tenantB = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => 'Verein B', 'slug' => 'verein-b', 'status' => 'active']);

        app(TenantContext::class)->set($tenantB);
        $foreignTemplate = DocumentTemplate::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Fremde Vorlage',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'layout' => ['blocks' => []],
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($tenantA);

        $this->actingAs($user)->get(route('documents.templates.edit', $foreignTemplate->id))->assertNotFound();
    }

    private function tenantUser(string $name = 'Testverein', string $slug = 'testverein'): array
    {
        $tenant = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => $name, 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
