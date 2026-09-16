<?php

namespace Tests\Feature\Finance;

use App\Models\ContributionRate;
use App\Models\ContributionRule;
use App\Models\FinanceInvoice;
use App\Models\Member;
use App\Models\Person;
use App\Models\SepaMandate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceModuleTest extends TestCase
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

    public function test_super_admin_can_open_finance_workspace(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Finanz-Cockpit')
            ->assertSee('Beitragssatz anlegen');
    }

    public function test_contribution_rule_creates_single_annual_draft(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-100');
        $rate = ContributionRate::query()->create([
            'name' => 'Jahresbeitrag',
            'code' => 'JAHR',
            'amount' => 120,
            'interval' => 'yearly',
            'is_active' => true,
        ]);
        ContributionRule::query()->create([
            'contribution_rate_id' => $rate->id,
            'priority' => 100,
            'is_active' => true,
        ]);

        $service = app(FinanceService::class);
        $first = $service->createContributionDraft($member, 2026, $user->id);
        $second = $service->createContributionDraft($member, 2026, $user->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame('120.00', $first->gross_amount);
        $this->assertDatabaseCount('finance_invoices', 1);
        $this->assertDatabaseCount('finance_invoice_items', 1);
    }

    public function test_invoice_numbers_are_sequential_per_year(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-200');
        $service = app(FinanceService::class);

        $first = $this->draftInvoice($member, $user, 10);
        $second = $this->draftInvoice($member, $user, 20);

        $service->issue($first, $user->id);
        $service->issue($second, $user->id);

        $this->assertSame('RE-2026-000001', $first->fresh()->invoice_number);
        $this->assertSame('RE-2026-000002', $second->fresh()->invoice_number);
    }

    public function test_payment_marks_invoice_as_paid(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-300');
        $service = app(FinanceService::class);
        $invoice = $this->draftInvoice($member, $user, 55.50);
        $service->issue($invoice, $user->id);

        $service->recordPayment($invoice, [
            'amount' => 55.50,
            'paid_at' => '2026-09-16',
            'method' => 'bank_transfer',
            'reference' => 'TEST-123',
            'notes' => null,
        ], $user->id);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('55.50', $invoice->paid_amount);
        $this->assertSame(0.0, $invoice->open_amount);
    }

    public function test_sepa_iban_is_encrypted_at_rest(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-400');

        $this->actingAs($user)->post(route('finance.sepa.store'), [
            'member_id' => $member->id,
            'mandate_reference' => 'MANDAT-400',
            'account_holder' => 'Max Mustermann',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'signed_at' => '2026-09-16',
        ])->assertRedirect();

        $mandate = SepaMandate::query()->firstOrFail();
        $rawIban = DB::table('sepa_mandates')->where('id', $mandate->id)->value('iban');

        $this->assertSame('DE89370400440532013000', $mandate->iban);
        $this->assertNotSame('DE89370400440532013000', $rawIban);
        $this->assertStringEndsWith('3000', str_replace(' ', '', $mandate->masked_iban));
    }

    public function test_other_tenant_invoice_is_not_route_bindable(): void
    {
        [$tenantA, $user] = $this->tenantUser('Verein A', 'verein-a');
        $tenantB = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => 'Verein B', 'slug' => 'verein-b', 'status' => 'active']);

        app(TenantContext::class)->set($tenantB);
        $foreignMember = $this->member('B-1');
        $foreignInvoice = FinanceInvoice::query()->create([
            'public_id' => Str::uuid(),
            'member_id' => $foreignMember->id,
            'status' => 'draft',
            'invoice_date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'net_amount' => 10,
            'tax_amount' => 0,
            'gross_amount' => 10,
            'paid_amount' => 0,
            'currency' => 'EUR',
        ]);
        app(TenantContext::class)->set($tenantA);

        $this->actingAs($user)->get(route('finance.invoices.show', $foreignInvoice->id))->assertNotFound();
    }

    private function draftInvoice(Member $member, User $user, float $amount): FinanceInvoice
    {
        $invoice = FinanceInvoice::query()->create([
            'public_id' => Str::uuid(),
            'member_id' => $member->id,
            'status' => 'draft',
            'invoice_date' => '2026-09-16',
            'due_date' => '2026-09-30',
            'net_amount' => $amount,
            'tax_amount' => 0,
            'gross_amount' => $amount,
            'paid_amount' => 0,
            'currency' => 'EUR',
            'created_by' => $user->id,
        ]);
        $invoice->items()->create([
            'description' => 'Testposition',
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'net_amount' => $amount,
            'tax_amount' => 0,
            'gross_amount' => $amount,
            'sort_order' => 10,
        ]);

        return $invoice;
    }

    private function member(string $number): Member
    {
        $person = Person::factory()->create(['first_name' => 'Max', 'last_name' => 'Mustermann']);

        return Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => $number,
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
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
