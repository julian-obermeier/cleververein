<?php

namespace Tests\Feature\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceDonation;
use App\Models\FinanceDonationCertificate;
use App\Models\FinanceInvoice;
use App\Models\FinancePaymentAdjustment;
use App\Models\FinanceSetting;
use App\Models\Member;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceLedgerService;
use App\Services\Finance\FinanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceRecoveryDonationTest extends TestCase
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

    public function test_chargeback_reopens_paid_invoice_and_posts_fee_separately(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-800');
        [$invoice, $payment] = $this->paidInvoice($member, $user, 100);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($user)->post(route('finance.payment-adjustments.store', $payment), [
            'type' => 'chargeback',
            'amount' => 100,
            'fee_amount' => 3.50,
            'adjustment_date' => '2026-09-16',
            'reason' => 'Rückgabe mangels Deckung',
            'reference' => 'RL-100',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('open', $invoice->status);
        $adjustment = FinancePaymentAdjustment::query()->firstOrFail();
        $this->assertSame('chargeback', $adjustment->type);
        $this->assertSame('3.50', $adjustment->fee_amount);
        $this->assertDatabaseHas('finance_entries', ['source_type' => 'payment_adjustment', 'gross_amount' => -100]);
        $this->assertDatabaseHas('finance_entries', ['source_type' => 'chargeback_fee', 'direction' => 'expense', 'gross_amount' => 3.50]);
    }

    public function test_refund_requires_real_credit_and_preserves_paid_status_after_credit_note(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $member = $this->member('M-801');
        [$invoice, $payment] = $this->paidInvoice($member, $user, 100);

        $this->actingAs($user)->post(route('finance.payment-adjustments.store', $payment), [
            'type' => 'refund',
            'amount' => 20,
            'fee_amount' => 0,
            'adjustment_date' => '2026-09-16',
            'reason' => 'Erstattung ohne Guthaben',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('finance_payment_adjustments', 0);

        app(TenantContext::class)->set($tenant);
        app(FinanceService::class)->createCreditNote($invoice->fresh(), 20, 'Teilweise Beitragsminderung', $user->id, false);

        $this->actingAs($user)->post(route('finance.payment-adjustments.store', $payment), [
            'type' => 'refund',
            'amount' => 20,
            'fee_amount' => 0,
            'adjustment_date' => '2026-09-16',
            'reason' => 'Auszahlung des Guthabens',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('80.00', $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);
        $this->assertDatabaseHas('finance_payment_adjustments', ['type' => 'refund', 'amount' => 20]);
        $this->assertDatabaseHas('finance_entries', ['source_type' => 'payment_adjustment', 'gross_amount' => -20]);
    }

    public function test_money_donation_posts_journal_and_certificate_is_private_snapshot(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        app(FinanceLedgerService::class)->ensureDefaults();
        $bank = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $this->validDonationSettings();

        $this->actingAs($user)->post(route('finance.donations.store'), [
            'donor_name' => 'Erika Spenderin',
            'donor_street' => 'Musterweg 4',
            'donor_postal_code' => '35390',
            'donor_city' => 'Gießen',
            'donor_country' => 'DE',
            'donor_email' => 'erika@example.test',
            'donation_kind' => 'money',
            'amount' => 50,
            'donation_date' => '2026-09-16',
            'purpose' => 'Förderung des Sports',
            'finance_account_id' => $bank->id,
            'reference' => 'SPENDE-50',
        ])->assertRedirect();

        $donation = FinanceDonation::query()->firstOrFail();
        $this->assertSame('SP-2026-000001', $donation->donation_number);
        $this->assertDatabaseHas('finance_entries', ['source_type' => 'donation', 'source_id' => $donation->id, 'gross_amount' => 50]);

        $this->actingAs($user)->post(route('finance.donations.certificates.issue', $donation))->assertRedirect();
        $certificate = FinanceDonationCertificate::query()->firstOrFail();
        $this->assertSame('ZB-2026-000001', $certificate->certificate_number);
        $this->assertSame('Testverein e.V.', $certificate->recipient_snapshot['name']);
        Storage::disk('local')->assertExists($certificate->pdf_path);

        FinanceSetting::query()->firstOrFail()->update(['creditor_name' => 'Später geänderter Name']);
        $this->assertSame('Testverein e.V.', $certificate->fresh()->recipient_snapshot['name']);
        $this->actingAs($user)->get(route('finance.donation-certificates.pdf', $certificate))->assertOk();
    }

    public function test_stale_tax_notice_blocks_certificate_issue(): void
    {
        [, $user] = $this->tenantUser();
        app(FinanceLedgerService::class)->ensureDefaults();
        $bank = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $this->validDonationSettings(['tax_notice_type' => '60a', 'tax_notice_date' => '2022-01-01']);
        $donation = $this->donation($user, $bank->id);

        $this->actingAs($user)->post(route('finance.donations.certificates.issue', $donation))
            ->assertSessionHasErrors('settings');
        $this->assertDatabaseCount('finance_donation_certificates', 0);
    }

    public function test_voided_certificate_can_be_reissued_with_new_number(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        app(FinanceLedgerService::class)->ensureDefaults();
        $bank = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $this->validDonationSettings();
        $donation = $this->donation($user, $bank->id);

        $this->actingAs($user)->post(route('finance.donations.certificates.issue', $donation))->assertRedirect();
        $first = FinanceDonationCertificate::query()->firstOrFail();
        $this->actingAs($user)->patch(route('finance.donation-certificates.void', $first), ['reason' => 'Anschrift korrigieren'])->assertRedirect();
        $this->assertSame('voided', $first->fresh()->status);

        $donation->update(['donor_street' => 'Neue Straße 7']);
        $this->actingAs($user)->post(route('finance.donations.certificates.issue', $donation))->assertRedirect();
        $second = FinanceDonationCertificate::query()->where('id', '!=', $first->id)->firstOrFail();
        $this->assertSame('ZB-2026-000002', $second->certificate_number);
        $this->assertSame('Neue Straße 7', $second->donor_snapshot['street']);
    }

    public function test_donations_and_certificates_are_tenant_isolated(): void
    {
        Storage::fake('local');
        [$tenantA, $userA] = $this->tenantUser('verein-a');
        app(FinanceLedgerService::class)->ensureDefaults();
        $bank = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $this->validDonationSettings();
        $donation = $this->donation($userA, $bank->id);
        $this->actingAs($userA)->post(route('finance.donations.certificates.issue', $donation))->assertRedirect();
        $certificate = FinanceDonationCertificate::query()->firstOrFail();

        [$tenantB, $userB] = $this->tenantUser('verein-b');
        app(TenantContext::class)->set($tenantB);
        $this->actingAs($userB)->get('/finanzen/zuwendungsbestaetigungen/'.$certificate->public_id.'/pdf')->assertNotFound();
        $this->actingAs($userB)->post('/finanzen/spenden/'.$donation->public_id.'/zuwendungsbestaetigung')->assertNotFound();
        $this->assertNotSame($tenantA->id, $tenantB->id);
    }

    private function donation(User $user, int $accountId): FinanceDonation
    {
        $this->actingAs($user)->post(route('finance.donations.store'), [
            'donor_name' => 'Max Spender',
            'donor_street' => 'Spendenstraße 1',
            'donor_postal_code' => '35390',
            'donor_city' => 'Gießen',
            'donor_country' => 'DE',
            'donation_kind' => 'money',
            'amount' => 25,
            'donation_date' => '2026-09-16',
            'purpose' => 'Förderung des Sports',
            'finance_account_id' => $accountId,
        ])->assertRedirect();

        return FinanceDonation::query()->latest('id')->firstOrFail();
    }

    private function paidInvoice(Member $member, User $user, float $amount): array
    {
        $invoice = $this->issuedInvoice($member, $user, $amount);
        $payment = app(FinanceService::class)->recordPayment($invoice, [
            'amount' => $amount,
            'paid_at' => '2026-09-16',
            'method' => 'bank_transfer',
            'reference' => $invoice->invoice_number,
        ], $user->id);

        return [$invoice->fresh(), $payment];
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

    private function validDonationSettings(array $overrides = []): FinanceSetting
    {
        return FinanceSetting::query()->create([
            'creditor_name' => 'Testverein e.V.',
            'street' => 'Vereinsweg 1',
            'postal_code' => '35390',
            'city' => 'Gießen',
            'country' => 'DE',
            'tax_number' => '020 250 12345',
            'payment_terms_days' => 14,
            'donation_receipts_enabled' => true,
            'tax_notice_type' => '60a',
            'tax_office' => 'Finanzamt Gießen',
            'tax_notice_date' => '2026-01-15',
            'tax_notice_reference' => 'AZ-60A-2026',
            'tax_exempt_purposes' => 'Förderung des Sports',
            'membership_contributions_deductible' => false,
            ...$overrides,
        ]);
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
