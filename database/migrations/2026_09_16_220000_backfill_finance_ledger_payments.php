<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_entries') || ! Schema::hasTable('finance_payments')) {
            return;
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $accounts = DB::table('finance_accounts')->where('tenant_id', $tenantId)->pluck('id', 'code');
            $categories = DB::table('finance_categories')->where('tenant_id', $tenantId)->pluck('id', 'code');
            if ($accounts->isEmpty() || $categories->isEmpty()) {
                continue;
            }

            $payments = DB::table('finance_payments as payment')
                ->leftJoin('finance_invoices as invoice', 'invoice.id', '=', 'payment.finance_invoice_id')
                ->where('payment.tenant_id', $tenantId)
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('finance_entries as entry')->whereColumn('entry.finance_payment_id', 'payment.id');
                })
                ->orderBy('payment.paid_at')
                ->orderBy('payment.id')
                ->get([
                    'payment.id', 'payment.finance_invoice_id', 'payment.member_id', 'payment.amount', 'payment.paid_at',
                    'payment.method', 'payment.reference', 'payment.notes', 'payment.recorded_by',
                    'invoice.invoice_number', 'invoice.gross_amount as invoice_gross', 'invoice.tax_amount as invoice_tax',
                ]);

            foreach ($payments as $payment) {
                $year = (int) substr((string) $payment->paid_at, 0, 4);
                $accountCode = $payment->method === 'cash' ? 'KASSE' : ($payment->method === 'other' ? 'VERRECHNUNG' : 'BANK');
                $accountId = $accounts[$accountCode] ?? $accounts->first();
                $hasContribution = $payment->finance_invoice_id
                    ? DB::table('finance_invoice_items')->where('finance_invoice_id', $payment->finance_invoice_id)->whereNotNull('contribution_rate_id')->exists()
                    : false;
                $categoryCode = $hasContribution ? 'MITGLIEDSBEITRAEGE' : 'SONSTIGE_EINNAHMEN';
                $categoryId = $categories[$categoryCode] ?? $categories->first();
                $gross = round((float) $payment->amount, 2);
                $invoiceGross = (float) ($payment->invoice_gross ?? 0);
                $invoiceTax = (float) ($payment->invoice_tax ?? 0);
                $tax = $invoiceGross > 0 ? round($gross * ($invoiceTax / $invoiceGross), 2) : 0.0;
                $net = round($gross - $tax, 2);
                $rates = $payment->finance_invoice_id
                    ? DB::table('finance_invoice_items')->where('finance_invoice_id', $payment->finance_invoice_id)->pluck('tax_rate')->map(fn ($value) => round((float) $value, 2))->unique()->values()
                    : collect();
                $taxRate = $rates->count() === 1 ? (float) $rates->first() : 0.0;
                $bankTransactionId = Schema::hasTable('bank_transactions')
                    ? DB::table('bank_transactions')->where('tenant_id', $tenantId)->where('finance_payment_id', $payment->id)->value('id')
                    : null;
                $number = $this->nextSequence((int) $tenantId, $year);

                DB::table('finance_entries')->insert([
                    'public_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'entry_number' => sprintf('BU-%d-%06d', $year, $number),
                    'booking_date' => $payment->paid_at,
                    'value_date' => null,
                    'direction' => 'income',
                    'finance_account_id' => $accountId,
                    'finance_category_id' => $categoryId,
                    'member_id' => $payment->member_id,
                    'finance_invoice_id' => $payment->finance_invoice_id,
                    'finance_payment_id' => $payment->id,
                    'bank_transaction_id' => $bankTransactionId,
                    'net_amount' => $net,
                    'tax_amount' => $tax,
                    'gross_amount' => $gross,
                    'tax_rate' => $taxRate,
                    'description' => 'Zahlung '.($payment->invoice_number ?: 'Rechnung #'.($payment->finance_invoice_id ?: 'ohne Zuordnung')),
                    'reference' => $payment->reference,
                    'source_type' => $bankTransactionId ? 'bank_import' : 'payment',
                    'source_id' => $bankTransactionId ?: $payment->id,
                    'status' => 'posted',
                    'reversal_of_id' => null,
                    'created_by' => $payment->recorded_by,
                    'posted_at' => now(),
                    'reversed_at' => null,
                    'notes' => $payment->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('finance_entries')) {
            return;
        }

        DB::table('finance_entries')->whereIn('source_type', ['payment', 'bank_import'])->delete();
    }

    private function nextSequence(int $tenantId, int $year): int
    {
        DB::table('finance_sequences')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'sequence_key' => 'ledger',
            'year' => $year,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('finance_sequences')
            ->where('tenant_id', $tenantId)
            ->where('sequence_key', 'ledger')
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
};
