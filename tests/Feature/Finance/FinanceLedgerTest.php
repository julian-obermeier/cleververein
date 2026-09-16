<?php

namespace Tests\Feature\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\FinanceInvoice;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceLedgerService;
use App\Services\Finance\FinanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
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

    public function test_finance_ledger_route_is_registered_and_defaults_are_created(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('finance.ledger.index'))->assertOk()->assertSee('Finanzjournal &amp; Berichte', false);

        $this->assertDatabaseHas('finance_accounts', ['tenant_id' => $user->current_tenant_id, 'code' => 'BANK']);
        $this->assertDatabaseHas('finance_accounts', ['tenant_id' => $user->current_tenant_id, 'code' => 'KASSE']);
        $this->assertDatabaseHas('finance_categories', ['tenant_id' => $user->current_tenant_id, 'code' => 'MITGLIEDSBEITRAEGE']);
        $this->assertDatabaseHas('finance_categories', ['tenant_id' => $user->current_tenant_id, 'code' => 'BETRIEBSAUSGABEN']);
    }

    public function test_manual_expense_is_posted_with_tax_and_sequential_number(): void
    {
        [, $user] = $this->tenantUser();
        app(FinanceLedgerService::class)->ensureDefaults();
        $account = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $category = FinanceCategory::query()->where('code', 'BETRIEBSAUSGABEN')->firstOrFail();

        $this->actingAs($user)->post(route('finance.ledger.entries.store'), [
            'booking_date' => '2026-09-16',
            'direction' => 'expense',
            'finance_account_id' => $account->id,
            'finance_category_id' => $category->id,
            'gross_amount' => 119,
            'tax_rate' => 19,
            'description' => 'Büromaterial',
            'reference' => 'BEL-1001',
        ])->assertRedirect();

        $entry = FinanceEntry::query()->firstOrFail();
        $this->assertSame('BU-2026-000001', $entry->entry_number);
        $this->assertSame('100.00', $entry->net_amount);
        $this->assertSame('19.00', $entry->tax_amount);
        $this->assertSame('119.00', $entry->gross_amount);
        $this->assertSame('manual', $entry->source_type);
    }

    public function test_manual_entry_is_reversed_by_counter_entry_instead_of_deletion(): void
    {
        [, $user] = $this->tenantUser();
        $ledger = app(FinanceLedgerService::class);
        $ledger->ensureDefaults();
        $account = FinanceAccount::query()->where('code', 'KASSE')->firstOrFail();
        $category = FinanceCategory::query()->where('code', 'SONSTIGE_EINNAHMEN')->firstOrFail();
        $entry = $ledger->postManual([
            'booking_date' => '2026-09-16',
            'direction' => 'income',
            'finance_account_id' => $account->id,
            'finance_category_id' => $category->id,
            'gross_amount' => 50,
            'tax_rate' => 0,
            'description' => 'Barverkauf',
        ], $user->id);

        $this->actingAs($user)->post(route('finance.ledger.entries.reverse', $entry), ['reason' => 'Fehlbuchung'])->assertRedirect();

        $entry->refresh();
        $reversal = FinanceEntry::query()->where('reversal_of_id', $entry->id)->firstOrFail();
        $this->assertSame('reversed', $entry->status);
        $this->assertSame('-50.00', $reversal->gross_amount);
        $this->assertSame('reversal', $reversal->source_type);
        $this->assertSame('BU-2026-000002', $reversal->entry_number);
        $this->assertDatabaseCount('finance_entries', 2);
    }

    public function test_invoice_payment_creates_exactly_one_automatic_ledger_entry(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-600');
        $invoice = $this->issuedInvoice($member, $user, 60);

        $payment = app(FinanceService::class)->recordPayment($invoice, [
            'amount' => 60,
            'paid_at' => '2026-09-16',
            'method' => 'bank_transfer',
            'reference' => $invoice->invoice_number,
        ], $user->id);

        $entry = FinanceEntry::query()->where('finance_payment_id', $payment->id)->firstOrFail();
        $this->assertSame('income', $entry->direction);
        $this->assertSame('60.00', $entry->gross_amount);
        $this->assertSame('BANK', $entry->account->code);
        $this->assertSame('payment', $entry->source_type);
        app(FinanceLedgerService::class)->postPayment($payment, $invoice->fresh(), $user->id);
        $this->assertSame(1, FinanceEntry::query()->where('finance_payment_id', $payment->id)->count());
    }

    public function test_finance_entries_are_tenant_isolated(): void
    {
        [$tenantA, $userA] = $this->tenantUser('verein-a');
        $ledger = app(FinanceLedgerService::class);
        $ledger->ensureDefaults();
        $entry = $ledger->postManual([
            'booking_date' => '2026-09-16',
            'direction' => 'income',
            'finance_account_id' => FinanceAccount::query()->where('code', 'BANK')->value('id'),
            'finance_category_id' => FinanceCategory::query()->where('code', 'SONSTIGE_EINNAHMEN')->value('id'),
            'gross_amount' => 10,
            'description' => 'Tenant A',
        ], $userA->id);

        [$tenantB] = $this->tenantUser('verein-b');
        $this->assertSame($tenantB->id, app(TenantContext::class)->id());
        $this->assertNull(FinanceEntry::query()->find($entry->id));

        app(TenantContext::class)->set($tenantA);
        $this->assertNotNull(FinanceEntry::query()->find($entry->id));
    }

    private function issuedInvoice(Member $member, User $user, float $amount): FinanceInvoice
    {
        $invoice = FinanceInvoice::query()->create([
            'public_id' => Str::uuid(), 'member_id' => $member->id, 'status' => 'draft',
            'invoice_date' => '2026-09-16', 'due_date' => '2026-09-30', 'net_amount' => $amount,
            'tax_amount' => 0, 'gross_amount' => $amount, 'paid_amount' => 0, 'currency' => 'EUR', 'created_by' => $user->id,
        ]);
        $invoice->items()->create([
            'description' => 'Sonstige Leistung', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0,
            'net_amount' => $amount, 'tax_amount' => 0, 'gross_amount' => $amount, 'sort_order' => 10,
        ]);

        return app(FinanceService::class)->issue($invoice, $user->id);
    }

    private function member(string $number): Member
    {
        $person = Person::factory()->create(['first_name' => 'Max', 'last_name' => 'Mustermann']);

        return Member::query()->create([
            'public_id' => Str::uuid(), 'person_id' => $person->id, 'member_number' => $number,
            'status' => 'active', 'joined_at' => '2026-01-01',
        ]);
    }

    private function tenantUser(string $slug = 'testverein'): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(), 'name' => ucfirst($slug), 'slug' => $slug.'-'.Str::lower(Str::random(4)), 'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
