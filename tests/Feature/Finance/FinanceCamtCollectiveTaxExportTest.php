<?php

namespace Tests\Feature\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceDonation;
use App\Models\FinanceDonationCollectiveCertificate;
use App\Models\FinanceInvoice;
use App\Models\FinanceSetting;
use App\Models\Member;
use App\Models\Person;
use App\Models\SepaMandate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceLedgerService;
use App\Services\Finance\FinanceService;
use App\Services\Finance\FinanceTaxExportService;
use App\Services\Finance\SepaExportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceCamtCollectiveTaxExportTest extends TestCase
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

    public function test_camt_053_import_parses_message_and_transaction_references(): void
    {
        [, $user] = $this->tenantUser();
        $xml = $this->camtXml('camt.053.001.14', 'BkToCstmrStmt', 'Stmt', 'CRDT', 12.34, 'E2E-CREDIT-1', 'MSG-CAMT-053');
        $file = UploadedFile::fake()->createWithContent('statement.xml', $xml);

        $this->actingAs($user)->post(route('finance.bank.import'), ['bank_file' => $file])->assertRedirect();

        $this->assertDatabaseHas('bank_import_batches', [
            'file_type' => 'camt053',
            'message_id' => 'MSG-CAMT-053',
            'row_count' => 1,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'amount' => 12.34,
            'currency' => 'EUR',
            'end_to_end_id' => 'E2E-CREDIT-1',
            'status' => 'unmatched',
        ]);
    }

    public function test_camt_054_exact_sepa_return_reopens_paid_invoice(): void
    {
        Storage::fake('local');
        [$tenant, $user] = $this->tenantUser();
        $this->sepaSettings();
        $member = $this->member('M-901');
        $invoice = $this->issuedInvoice($member, $user, 25);
        SepaMandate::query()->create([
            'public_id' => Str::uuid(),
            'member_id' => $member->id,
            'mandate_reference' => 'MANDAT-901',
            'account_holder' => 'Max Mustermann',
            'iban' => 'DE12500105170648489890',
            'bic' => 'INGDDEFFXXX',
            'signed_at' => '2026-01-01',
            'status' => 'active',
        ]);

        $sepa = app(SepaExportService::class);
        $batch = $sepa->generate($sepa->createBatch('2026-09-25', $user->id));
        $sepa->submit($batch);
        $item = $batch->items()->firstOrFail();

        app(FinanceService::class)->recordPayment($invoice, [
            'amount' => 25,
            'paid_at' => '2026-09-15',
            'method' => 'sepa',
            'reference' => $item->end_to_end_id,
        ], $user->id);
        $this->assertSame('paid', $invoice->fresh()->status);

        $xml = $this->camtXml('camt.054.001.14', 'BkToCstmrDbtCdtNtfctn', 'Ntfctn', 'DBIT', 25, $item->end_to_end_id, 'MSG-RETURN-1', true);
        $file = UploadedFile::fake()->createWithContent('returns.xml', $xml);
        $this->actingAs($user)->post(route('finance.bank.import'), ['bank_file' => $file])->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $invoice->refresh();
        $this->assertSame('open', $invoice->status);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertDatabaseHas('bank_transactions', [
            'status' => 'chargeback',
            'finance_invoice_id' => $invoice->id,
            'end_to_end_id' => $item->end_to_end_id,
            'return_reason_code' => 'AM04',
        ]);
        $this->assertDatabaseHas('finance_payment_adjustments', [
            'finance_invoice_id' => $invoice->id,
            'type' => 'chargeback',
            'amount' => 25,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('finance_entries', [
            'finance_invoice_id' => $invoice->id,
            'source_type' => 'payment_adjustment',
            'gross_amount' => -25,
        ]);
    }

    public function test_unknown_negative_camt_transaction_stays_unmatched(): void
    {
        [, $user] = $this->tenantUser();
        $xml = $this->camtXml('camt.054.001.14', 'BkToCstmrDbtCdtNtfctn', 'Ntfctn', 'DBIT', 44.10, 'UNKNOWN-E2E', 'MSG-RETURN-UNKNOWN', true);
        $file = UploadedFile::fake()->createWithContent('unknown-return.xml', $xml);

        $this->actingAs($user)->post(route('finance.bank.import'), ['bank_file' => $file])->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', [
            'status' => 'unmatched',
            'amount' => -44.10,
            'end_to_end_id' => 'UNKNOWN-E2E',
        ]);
        $this->assertDatabaseCount('finance_payment_adjustments', 0);
    }

    public function test_collective_certificate_contains_two_donations_and_blocks_single_certificate(): void
    {
        Storage::fake('local');
        [$tenant, $user] = $this->tenantUser();
        $this->validDonationSettings();
        $first = $this->donation($user, 25, '2026-03-01');
        $second = $this->donation($user, 35, '2026-08-15');

        $this->actingAs($user)->post(route('finance.donations.collective-certificates.issue'), [
            'donations' => [$first->id, $second->id],
        ])->assertRedirect();

        app(TenantContext::class)->set($tenant);
        $certificate = FinanceDonationCollectiveCertificate::query()->with('items')->firstOrFail();
        $this->assertSame('60.00', $certificate->total_amount);
        $this->assertSame(2, $certificate->items->count());
        $this->assertSame('ZB-2026-000001', $certificate->certificate_number);
        Storage::disk('local')->assertExists($certificate->pdf_path);

        $this->actingAs($user)->post(route('finance.donations.certificates.issue', $first))
            ->assertSessionHasErrors('donation');
        $this->assertDatabaseCount('finance_donation_certificates', 0);
    }

    public function test_collective_certificate_rejects_mixed_donors(): void
    {
        [, $user] = $this->tenantUser();
        $this->validDonationSettings();
        $first = $this->donation($user, 20, '2026-01-10');
        $second = $this->donation($user, 30, '2026-02-10', ['donor_street' => 'Andere Straße 99']);

        $this->actingAs($user)->post(route('finance.donations.collective-certificates.issue'), [
            'donations' => [$first->id, $second->id],
        ])->assertSessionHasErrors('donations');

        $this->assertDatabaseCount('finance_donation_collective_certificates', 0);
    }

    public function test_tax_adviser_export_requires_mapping_and_returns_mapped_rows(): void
    {
        [, $user] = $this->tenantUser();
        $ledger = app(FinanceLedgerService::class);
        $ledger->ensureDefaults();
        $bank = FinanceAccount::query()->where('code', 'BANK')->firstOrFail();
        $category = FinanceCategory::query()->where('code', 'BETRIEBSAUSGABEN')->firstOrFail();
        $ledger->postManual([
            'booking_date' => '2026-09-16',
            'direction' => 'expense',
            'finance_account_id' => $bank->id,
            'finance_category_id' => $category->id,
            'gross_amount' => 119,
            'tax_rate' => 19,
            'description' => 'Büromaterial',
            'reference' => 'BEL-9001',
        ], $user->id);

        try {
            app(FinanceTaxExportService::class)->rows(2026, 9);
            $this->fail('Fehlende Kontenzuordnung muss den Export blockieren.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('export', $exception->errors());
        }

        $bank->update(['datev_account' => '1200']);
        $category->update(['datev_account' => '4930']);
        $rows = app(FinanceTaxExportService::class)->rows(2026, 9);
        $this->assertCount(1, $rows);
        $this->assertSame('4930', $rows->first()['account']);
        $this->assertSame('1200', $rows->first()['contra_account']);
        $this->assertSame('S', $rows->first()['side']);
        $this->assertSame(119.0, $rows->first()['amount']);

        $this->actingAs($user)->get(route('finance.tax-export.csv', ['year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function camtXml(
        string $version,
        string $container,
        string $statementTag,
        string $indicator,
        float $amount,
        string $endToEndId,
        string $messageId,
        bool $returned = false,
    ): string {
        $returnInfo = $returned ? '<RtrInf><Rsn><Cd>AM04</Cd></Rsn><AddtlInf>Unzureichende Deckung</AddtlInf></RtrInf>' : '';
        $namespace = 'urn:iso:std:iso:20022:tech:xsd:'.$version;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="{$namespace}">
  <{$container}>
    <GrpHdr><MsgId>{$messageId}</MsgId><CreDtTm>2026-09-16T10:00:00</CreDtTm></GrpHdr>
    <{$statementTag}>
      <Id>STATEMENT-1</Id>
      <Ntry>
        <Amt Ccy="EUR">{$amount}</Amt>
        <CdtDbtInd>{$indicator}</CdtDbtInd>
        <BookgDt><Dt>2026-09-16</Dt></BookgDt>
        <ValDt><Dt>2026-09-16</Dt></ValDt>
        <BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RDDT</Cd><SubFmlyCd>RRTN</SubFmlyCd></Fmly></Domn></BkTxCd>
        <NtryDtls>
          <TxDtls>
            <Refs><EndToEndId>{$endToEndId}</EndToEndId><MndtId>MANDAT-901</MndtId><TxId>TX-1</TxId></Refs>
            {$returnInfo}
            <RltdPties><Cdtr><Nm>Max Mustermann</Nm></Cdtr><CdtrAcct><Id><IBAN>DE12500105170648489890</IBAN></Id></CdtrAcct></RltdPties>
            <RmtInf><Ustrd>Mitgliedsbeitrag {$endToEndId}</Ustrd></RmtInf>
          </TxDtls>
        </NtryDtls>
      </Ntry>
    </{$statementTag}>
  </{$container}>
</Document>
XML;
    }

    private function donation(User $user, float $amount, string $date, array $overrides = []): FinanceDonation
    {
        return FinanceDonation::query()->create([
            'public_id' => Str::uuid(),
            'donation_number' => 'SP-TEST-'.Str::upper(Str::random(6)),
            'donor_name' => 'Erika Spenderin',
            'donor_street' => 'Musterweg 4',
            'donor_postal_code' => '35390',
            'donor_city' => 'Gießen',
            'donor_country' => 'DE',
            'donation_kind' => 'money',
            'amount' => $amount,
            'donation_date' => $date,
            'purpose' => 'Förderung des Sports',
            'expense_waiver' => false,
            'status' => 'received',
            'received_by' => $user->id,
            ...$overrides,
        ]);
    }

    private function sepaSettings(): FinanceSetting
    {
        return FinanceSetting::query()->create([
            'creditor_name' => 'Testverein e.V.',
            'street' => 'Vereinsweg 1',
            'postal_code' => '35390',
            'city' => 'Gießen',
            'country' => 'DE',
            'creditor_id' => 'DE98ZZZ09999999999',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'payment_terms_days' => 14,
        ]);
    }

    private function validDonationSettings(): FinanceSetting
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
        ]);
    }

    private function issuedInvoice(Member $member, User $user, float $amount): FinanceInvoice
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
            'description' => 'Mitgliedsbeitrag',
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'net_amount' => $amount,
            'tax_amount' => 0,
            'gross_amount' => $amount,
            'sort_order' => 10,
        ]);

        return app(FinanceService::class)->issue($invoice, $user->id);
    }

    private function member(string $number): Member
    {
        $person = Person::factory()->create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'contact_data' => [
                'street' => 'Musterweg 2',
                'postal_code' => '35644',
                'city' => 'Hohenahr',
                'country' => 'DE',
            ],
        ]);

        return Member::query()->create([
            'public_id' => Str::uuid(),
            'person_id' => $person->id,
            'member_number' => $number,
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => Str::uuid(),
            'name' => 'Testverein',
            'slug' => 'testverein-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
            'is_super_admin' => true,
        ]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
