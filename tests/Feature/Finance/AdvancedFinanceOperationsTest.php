<?php

namespace Tests\Feature\Finance;

use App\Models\ContributionRate;
use App\Models\FinanceInvoice;
use App\Models\FinanceSetting;
use App\Models\Household;
use App\Models\Member;
use App\Models\Person;
use App\Models\SepaBatch;
use App\Models\SepaMandate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\FinanceService;
use App\Services\Finance\SepaExportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvancedFinanceOperationsTest extends TestCase
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

    public function test_finance_settings_encrypt_creditor_iban(): void
    {
        [, $user] = $this->tenantUser();
        $this->actingAs($user)->put(route('finance.operations.settings'), [
            'creditor_name' => 'Testverein', 'country' => 'DE', 'creditor_id' => 'DE98ZZZ09999999999',
            'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'payment_terms_days' => 14,
        ])->assertRedirect();

        $settings = FinanceSetting::query()->firstOrFail();
        $raw = DB::table('finance_settings')->where('id', $settings->id)->value('iban');
        $this->assertSame('DE89370400440532013000', $settings->iban);
        $this->assertNotSame($settings->iban, $raw);
    }

    public function test_issued_invoice_can_be_rendered_as_private_pdf(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        $invoice = $this->issuedInvoice($this->member('M-501'), $user, 42.50);

        $this->actingAs($user)->get(route('finance.invoices.pdf', $invoice))->assertOk();
        $invoice->refresh();
        $this->assertNotNull($invoice->pdf_path);
        $this->assertGreaterThan(100, $invoice->pdf_size);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->assertSame('Max Mustermann', $invoice->recipient_snapshot['name']);
    }

    public function test_credit_note_reduces_open_amount_and_can_cancel_invoice(): void
    {
        [, $user] = $this->tenantUser();
        $invoice = $this->issuedInvoice($this->member('M-502'), $user, 100);

        $credit = app(FinanceService::class)->createCreditNote($invoice, 40, 'Korrektur', $user->id);
        $invoice->refresh();
        $this->assertSame('GS-2026-000001', $credit->credit_number);
        $this->assertSame(60.0, $invoice->open_amount);

        app(FinanceService::class)->createCreditNote($invoice, 60, 'Reststorno', $user->id, true);
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_household_contribution_creates_only_one_draft_per_year_and_rate(): void
    {
        [, $user] = $this->tenantUser();
        $member = $this->member('M-503');
        $household = Household::query()->create(['public_id' => Str::uuid(), 'name' => 'Familie Mustermann']);
        $household->members()->attach($member->id, ['tenant_id' => $member->tenant_id, 'relationship' => 'Hauptkontakt', 'is_primary_contact' => true]);
        $rate = ContributionRate::query()->create(['name' => 'Familienbeitrag', 'code' => 'FAM', 'amount' => 150, 'interval' => 'yearly', 'scope' => 'household', 'is_active' => true]);

        $service = app(FinanceService::class);
        $first = $service->createHouseholdContributionDraft($household, $rate, 2026, $user->id);
        $second = $service->createHouseholdContributionDraft($household, $rate, 2026, $user->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($household->id, $first->household_id);
        $this->assertDatabaseCount('finance_invoices', 1);
    }

    public function test_sepa_export_uses_pain_008_and_submission_advances_mandate(): void
    {
        Storage::fake('local');
        [, $user] = $this->tenantUser();
        FinanceSetting::query()->create([
            'creditor_name' => 'Testverein', 'street' => 'Musterweg 1', 'postal_code' => '35644', 'city' => 'Hohenahr',
            'country' => 'DE', 'creditor_id' => 'DE98ZZZ09999999999', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX',
        ]);
        $member = $this->member('M-504');
        $invoice = $this->issuedInvoice($member, $user, 25);
        $mandate = SepaMandate::query()->create([
            'public_id' => Str::uuid(), 'member_id' => $member->id, 'mandate_reference' => 'MANDAT-504',
            'account_holder' => 'Max Mustermann', 'iban' => 'DE12500105170648489890', 'bic' => 'INGDDEFFXXX',
            'signed_at' => '2026-01-01', 'status' => 'active',
        ]);

        $service = app(SepaExportService::class);
        $batch = $service->generate($service->createBatch('2026-09-25', $user->id));
        $xml = Storage::disk('local')->get($batch->file_path);
        $this->assertStringContainsString('pain.008.001.08', $xml);
        $this->assertStringContainsString('<SeqTp>FRST</SeqTp>', $xml);
        $this->assertStringContainsString($invoice->invoice_number, $xml);
        $service->submit($batch);
        $mandate->refresh();
        $this->assertSame(1, $mandate->collection_count);
        $this->assertSame('submitted', SepaBatch::query()->find($batch->id)->status);
    }

    public function test_bank_csv_auto_matches_invoice_number_and_records_payment(): void
    {
        [, $user] = $this->tenantUser();
        $invoice = $this->issuedInvoice($this->member('M-505'), $user, 33.33);
        $csv = "Buchungstag;Betrag;Verwendungszweck;Name Zahlungsbeteiligter\n16.09.2026;33,33;Beitrag {$invoice->invoice_number};Max Mustermann\n";
        $file = UploadedFile::fake()->createWithContent('bank.csv', $csv);

        $this->actingAs($user)->post(route('finance.bank.import'), ['bank_file' => $file])->assertRedirect();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('33.33', $invoice->paid_amount);
        $this->assertDatabaseHas('bank_transactions', ['status' => 'matched', 'finance_invoice_id' => $invoice->id]);
    }

    private function issuedInvoice(Member $member, User $user, float $amount): FinanceInvoice
    {
        $invoice = FinanceInvoice::query()->create([
            'public_id' => Str::uuid(), 'member_id' => $member->id, 'status' => 'draft',
            'invoice_date' => '2026-09-16', 'due_date' => '2026-09-30', 'net_amount' => $amount,
            'tax_amount' => 0, 'gross_amount' => $amount, 'paid_amount' => 0, 'currency' => 'EUR', 'created_by' => $user->id,
        ]);
        $invoice->items()->create(['description' => 'Test', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0, 'net_amount' => $amount, 'tax_amount' => 0, 'gross_amount' => $amount, 'sort_order' => 10]);

        return app(FinanceService::class)->issue($invoice, $user->id);
    }

    private function member(string $number): Member
    {
        $person = Person::factory()->create([
            'first_name' => 'Max', 'last_name' => 'Mustermann',
            'contact_data' => ['street' => 'Musterweg 2', 'postal_code' => '35644', 'city' => 'Hohenahr', 'country' => 'DE'],
        ]);

        return Member::query()->create(['public_id' => Str::uuid(), 'person_id' => $person->id, 'member_number' => $number, 'status' => 'active', 'joined_at' => '2026-01-01']);
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => 'Testverein', 'slug' => 'testverein', 'status' => 'active']);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id, 'is_super_admin' => true]);
        $tenant->users()->attach($user->id, ['status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$tenant, $user];
    }
}
