<?php

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceLedgerService
{
    public function __construct(private TenantContext $tenant) {}

    public function ensureDefaults(): void
    {
        $accounts = [
            ['name' => 'Bank', 'code' => 'BANK', 'type' => 'bank', 'is_default' => true, 'sort_order' => 10],
            ['name' => 'Kasse', 'code' => 'KASSE', 'type' => 'cash', 'is_default' => false, 'sort_order' => 20],
            ['name' => 'Verrechnung', 'code' => 'VERRECHNUNG', 'type' => 'clearing', 'is_default' => false, 'sort_order' => 90],
        ];
        foreach ($accounts as $account) {
            FinanceAccount::query()->firstOrCreate(
                ['code' => $account['code']],
                [...$account, 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true],
            );
        }

        $categories = [
            ['name' => 'Mitgliedsbeiträge', 'code' => 'MITGLIEDSBEITRAEGE', 'direction' => 'income', 'sort_order' => 10],
            ['name' => 'Spenden', 'code' => 'SPENDEN', 'direction' => 'income', 'sort_order' => 20],
            ['name' => 'Sonstige Einnahmen', 'code' => 'SONSTIGE_EINNAHMEN', 'direction' => 'income', 'sort_order' => 90],
            ['name' => 'Betriebsausgaben', 'code' => 'BETRIEBSAUSGABEN', 'direction' => 'expense', 'sort_order' => 110],
            ['name' => 'Gebühren', 'code' => 'GEBUEHREN', 'direction' => 'expense', 'sort_order' => 120],
            ['name' => 'Sonstige Ausgaben', 'code' => 'SONSTIGE_AUSGABEN', 'direction' => 'expense', 'sort_order' => 190],
        ];
        foreach ($categories as $category) {
            FinanceCategory::query()->firstOrCreate(
                ['code' => $category['code']],
                [...$category, 'default_tax_rate' => 0, 'is_active' => true],
            );
        }
    }

    public function postManual(array $data, int $userId): FinanceEntry
    {
        $this->ensureDefaults();
        $account = FinanceAccount::query()->where('is_active', true)->findOrFail($data['finance_account_id']);
        $category = FinanceCategory::query()->where('is_active', true)->findOrFail($data['finance_category_id']);
        if ($category->direction !== $data['direction']) {
            throw ValidationException::withMessages(['finance_category_id' => 'Die Kategorie passt nicht zur gewählten Buchungsart.']);
        }

        $gross = round((float) $data['gross_amount'], 2);
        $taxRate = round((float) ($data['tax_rate'] ?? $category->default_tax_rate ?? 0), 2);
        $net = $taxRate > 0 ? round($gross / (1 + ($taxRate / 100)), 2) : $gross;
        $tax = round($gross - $net, 2);

        return DB::transaction(fn (): FinanceEntry => $this->create([
            'booking_date' => $data['booking_date'],
            'value_date' => $data['value_date'] ?? null,
            'direction' => $data['direction'],
            'finance_account_id' => $account->id,
            'finance_category_id' => $category->id,
            'member_id' => $data['member_id'] ?? null,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'gross_amount' => $gross,
            'tax_rate' => $taxRate,
            'description' => $data['description'],
            'reference' => $data['reference'] ?? null,
            'source_type' => 'manual',
            'created_by' => $userId,
            'posted_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]));
    }

    public function postPayment(FinancePayment $payment, FinanceInvoice $invoice, int $userId, ?BankTransaction $transaction = null): FinanceEntry
    {
        $this->ensureDefaults();
        $existing = FinanceEntry::query()->where('finance_payment_id', $payment->id)->first();
        if ($existing) {
            if ($transaction && ! $existing->bank_transaction_id) {
                $existing->update(['bank_transaction_id' => $transaction->id]);
            }

            return $existing->fresh();
        }

        $invoice->loadMissing(['items', 'creditNotes']);
        $accountCode = $payment->method === 'cash' ? 'KASSE' : ($payment->method === 'other' ? 'VERRECHNUNG' : 'BANK');
        $account = FinanceAccount::query()->where('code', $accountCode)->first()
            ?? FinanceAccount::query()->where('is_default', true)->first()
            ?? FinanceAccount::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $hasContribution = $invoice->items->contains(fn ($item) => $item->contribution_rate_id !== null);
        $categoryCode = $hasContribution ? 'MITGLIEDSBEITRAEGE' : 'SONSTIGE_EINNAHMEN';
        $category = FinanceCategory::query()->where('code', $categoryCode)->first()
            ?? FinanceCategory::query()->where('direction', 'income')->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        $gross = round((float) $payment->amount, 2);
        $invoiceGross = (float) $invoice->gross_amount;
        $invoiceTax = (float) $invoice->tax_amount;
        $tax = $invoiceGross > 0 ? round($gross * ($invoiceTax / $invoiceGross), 2) : 0.0;
        $net = round($gross - $tax, 2);
        $rates = $invoice->items->pluck('tax_rate')->map(fn ($value) => round((float) $value, 2))->unique()->values();
        $taxRate = $rates->count() === 1 ? (float) $rates->first() : 0.0;

        return $this->create([
            'booking_date' => $payment->paid_at->toDateString(),
            'direction' => 'income',
            'finance_account_id' => $account->id,
            'finance_category_id' => $category->id,
            'member_id' => $invoice->member_id,
            'finance_invoice_id' => $invoice->id,
            'finance_payment_id' => $payment->id,
            'bank_transaction_id' => $transaction?->id,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'gross_amount' => $gross,
            'tax_rate' => $taxRate,
            'description' => 'Zahlung '.($invoice->invoice_number ?: 'Rechnung #'.$invoice->id),
            'reference' => $payment->reference,
            'source_type' => $transaction ? 'bank_import' : 'payment',
            'source_id' => $transaction?->id ?: $payment->id,
            'created_by' => $userId,
            'posted_at' => now(),
            'notes' => $payment->notes,
        ]);
    }

    public function attachBankTransaction(FinancePayment $payment, BankTransaction $transaction): void
    {
        FinanceEntry::query()->where('finance_payment_id', $payment->id)->whereNull('bank_transaction_id')->update([
            'bank_transaction_id' => $transaction->id,
            'source_type' => 'bank_import',
            'source_id' => $transaction->id,
        ]);
    }

    public function reverse(FinanceEntry $entry, int $userId, string $reason): FinanceEntry
    {
        if (FinanceEntry::query()->where('reversal_of_id', $entry->id)->exists()) {
            throw ValidationException::withMessages(['entry' => 'Diese Buchung wurde bereits storniert.']);
        }

        return DB::transaction(function () use ($entry, $userId, $reason): FinanceEntry {
            $reversal = $this->create([
                'booking_date' => now()->toDateString(),
                'direction' => $entry->direction,
                'finance_account_id' => $entry->finance_account_id,
                'finance_category_id' => $entry->finance_category_id,
                'member_id' => $entry->member_id,
                'finance_invoice_id' => $entry->finance_invoice_id,
                'net_amount' => -1 * (float) $entry->net_amount,
                'tax_amount' => -1 * (float) $entry->tax_amount,
                'gross_amount' => -1 * (float) $entry->gross_amount,
                'tax_rate' => $entry->tax_rate,
                'description' => 'Storno: '.$entry->description,
                'reference' => $entry->entry_number,
                'source_type' => 'reversal',
                'source_id' => $entry->id,
                'status' => 'posted',
                'reversal_of_id' => $entry->id,
                'created_by' => $userId,
                'posted_at' => now(),
                'notes' => $reason,
            ]);
            $entry->update(['status' => 'reversed', 'reversed_at' => now()]);

            return $reversal;
        });
    }

    private function create(array $data): FinanceEntry
    {
        $year = (int) substr((string) $data['booking_date'], 0, 4);
        $number = $this->nextSequence('ledger', $year);

        return FinanceEntry::query()->create([
            ...$data,
            'public_id' => Str::uuid(),
            'entry_number' => sprintf('BU-%d-%06d', $year, $number),
        ]);
    }

    private function nextSequence(string $key, int $year): int
    {
        DB::table('finance_sequences')->insertOrIgnore([
            'tenant_id' => $this->tenant->id(),
            'sequence_key' => $key,
            'year' => $year,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('finance_sequences')
            ->where('tenant_id', $this->tenant->id())
            ->where('sequence_key', $key)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $number = (int) $sequence->next_value;
        DB::table('finance_sequences')->where('id', $sequence->id)->update([
            'next_value' => $number + 1,
            'updated_at' => now(),
        ]);

        return $number;
    }
}
