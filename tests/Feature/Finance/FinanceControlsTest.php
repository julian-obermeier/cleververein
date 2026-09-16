<?php

namespace Tests\Feature\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceCashAudit;
use App\Models\FinanceCashClosing;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\FinancePeriodLock;
use App\Models\FinanceReceipt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceControlsTest extends TestCase
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

    public function test_cash_control_page_is_available_and_defaults_exist(): void
    {
        [, $user] = $this->tenantUser();

        $this->actingAs($user)->get(route('finance.controls.index'))
            ->assertOk()
            ->assertSee('Kasse &amp; Prüfung', false);

        $this->assertDatabaseHas('finance_accounts', ['tenant_id' => $user->current_tenant_id, 'code' => 'KASSE', 'type' => 'cash']);
    }

    public function test_receipt_is_stored_privately_and_voided_without_file_deletion(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        [$entry] = $this->manualCashEntry($user, '2026-09-16', 12.50);
        $file = UploadedFile::fake()->create('beleg.pdf', 120, 'application/pdf');

        $this->actingAs($user)->post(route('finance.receipts.store', $entry), [
            'receipt' => $file,
            'document_date' => '2026-09-16',
            'notes' => 'Originalbeleg',
        ])->assertRedirect();

        $receipt = FinanceReceipt::query()->firstOrFail();
        Storage::disk('local')->assertExists($receipt->path);
        $this->actingAs($user)->get(route('finance.receipts.download', $receipt))->assertOk();
        $this->actingAs($user)->patch(route('finance.receipts.void', $receipt), ['reason' => 'Falscher Scan'])->assertRedirect();
        $this->assertSame('voided', $receipt->fresh()->status);
        Storage::disk('local')->assertExists($receipt->path);
    }

    public function test_cash_closing_blocks_late_cash_booking(): void
    {
        [, $user] = $this->tenantUser();
        app(FinanceLedgerService::class)->ensureDefaults();
        $cash = FinanceAccount::query()->where('code', 'KASSE')->firstOrFail();
        $expense = FinanceCategory::query()->where('code', 'BETRIEBSAUSGABEN')->firstOrFail();

        $this->actingAs($user)->post(route('finance.cash.closings.store'), [
            'finance_account_id' => $cash->id,
            'closing_date' => '2026-09-16',
            'counted_balance' => 0,
        ])->assertRedirect();
        $this->assertDatabaseHas('finance_cash_closings', ['finance_account_id' => $cash->id, 'closing_date' => '2026-09-16']);

        $this->actingAs($user)->post(route('finance.ledger.entries.store'), [
            'booking_date' => '2026-09-16',
            'direction' => 'expense',
            'finance_account_id' => $cash->id,
            'finance_category_id' => $expense->id,
            'gross_amount' => 5,
            'tax_rate' => 0,
            'description' => 'Zu spät erfasst',
        ])->assertSessionHasErrors('booking_date');
    }

    public function test_period_lock_blocks_booking_and_unlock_allows_it_again(): void
    {
        [, $user] = $this->tenantUser();
        app(FinanceLedgerService::class)->ensureDefaults();
        $bank = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $expense = FinanceCategory::query()->where('code', 'BETRIEBSAUSGABEN')->firstOrFail();

        $this->actingAs($user)->post(route('finance.periods.store'), [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'reason' => 'Monatsabschluss',
        ])->assertRedirect();
        $lock = FinancePeriodLock::query()->firstOrFail();

        $payload = [
            'booking_date' => '2026-09-10',
            'direction' => 'expense',
            'finance_account_id' => $bank->id,
            'finance_category_id' => $expense->id,
            'gross_amount' => 25,
            'tax_rate' => 0,
            'description' => 'Testbuchung',
        ];
        $this->actingAs($user)->post(route('finance.ledger.entries.store'), $payload)->assertSessionHasErrors('booking_date');

        $this->actingAs($user)->patch(route('finance.periods.unlock', $lock), ['reason' => 'Korrektur erforderlich'])->assertRedirect();
        $this->actingAs($user)->post(route('finance.ledger.entries.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('finance_entries', ['description' => 'Testbuchung', 'gross_amount' => 25]);
    }

    public function test_cash_audit_records_expected_and_counted_difference(): void
    {
        [, $user] = $this->tenantUser();
        [$entry, $cash] = $this->manualCashEntry($user, '2026-09-16', 50);
        $this->assertInstanceOf(FinanceEntry::class, $entry);

        $this->actingAs($user)->post(route('finance.cash-audits.store'), [
            'finance_account_id' => $cash->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'result' => 'issues',
            'counted_balance' => 48,
            'findings' => '2 Euro Differenz festgestellt.',
        ])->assertRedirect();

        $audit = FinanceCashAudit::query()->firstOrFail();
        $this->assertSame('50.00', $audit->expected_balance);
        $this->assertSame('48.00', $audit->counted_balance);
        $this->assertSame('-2.00', $audit->difference);
        $this->assertSame(1, $audit->entry_count);
    }

    public function test_receipts_are_tenant_isolated(): void
    {
        Storage::fake('local');
        [$tenantA, $userA] = $this->tenantUser('verein-a');
        [$entry] = $this->manualCashEntry($userA, '2026-09-16', 10);
        $this->actingAs($userA)->post(route('finance.receipts.store', $entry), [
            'receipt' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $receipt = FinanceReceipt::query()->firstOrFail();

        [$tenantB, $userB] = $this->tenantUser('verein-b');
        app(TenantContext::class)->set($tenantB);
        $this->actingAs($userB)->get('/finanzen/belege/'.$receipt->public_id.'/download')->assertNotFound();
        $this->assertNotSame($tenantA->id, $tenantB->id);
    }

    private function manualCashEntry(User $user, string $date, float $amount): array
    {
        app(FinanceLedgerService::class)->ensureDefaults();
        $cash = FinanceAccount::query()->where('code', 'KASSE')->firstOrFail();
        $income = FinanceCategory::query()->where('code', 'SONSTIGE_EINNAHMEN')->firstOrFail();
        $entry = app(FinanceLedgerService::class)->postManual([
            'booking_date' => $date,
            'direction' => 'income',
            'finance_account_id' => $cash->id,
            'finance_category_id' => $income->id,
            'gross_amount' => $amount,
            'tax_rate' => 0,
            'description' => 'Bareinnahme',
        ], $user->id);

        return [$entry, $cash];
    }

    private function tenantUser(string $slug = 'testverein'): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Testverein '.Str::upper($slug),
            'slug' => $slug.'-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
