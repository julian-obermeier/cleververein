<?php

namespace App\Services\Finance;

use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\FinanceInvoice;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankImportService
{
    public function __construct(
        private TenantContext $tenant,
        private FinanceService $finance,
    ) {}

    public function import(UploadedFile $file, int $userId): BankImportBatch
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['bank_file' => 'Die Bankdatei konnte nicht gelesen werden.']);
        }
        $hash = hash('sha256', $contents);
        if (BankImportBatch::query()->where('file_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['bank_file' => 'Diese Bankdatei wurde bereits importiert.']);
        }
        $rows = $this->csvRows($contents);
        if ($rows === []) {
            throw ValidationException::withMessages(['bank_file' => 'Die CSV-Datei enthält keine verwertbaren Umsätze.']);
        }

        return DB::transaction(function () use ($file, $hash, $rows, $userId): BankImportBatch {
            $batch = BankImportBatch::query()->create([
                'public_id' => Str::uuid(), 'original_name' => $file->getClientOriginalName(),
                'file_hash' => $hash, 'row_count' => 0, 'matched_count' => 0, 'imported_by' => $userId,
            ]);
            $stored = 0;
            $matched = 0;
            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row);
                if (! $normalized) {
                    continue;
                }
                $externalId = hash('sha256', implode('|', [
                    $normalized['booking_date'], $normalized['value_date'] ?? '', $normalized['amount'],
                    $normalized['payer_name'] ?? '', $normalized['reference'] ?? '', $normalized['payer_iban'] ?? '',
                ]));
                if (BankTransaction::query()->where('external_id', $externalId)->exists()) {
                    continue;
                }
                $transaction = $batch->transactions()->create([...$normalized, 'external_id' => $externalId, 'status' => 'unmatched']);
                $stored++;
                if ($this->autoMatch($transaction, $userId)) {
                    $matched++;
                }
            }
            $batch->update(['row_count' => $stored, 'matched_count' => $matched]);

            return $batch->fresh(['transactions.invoice.member.person']);
        });
    }

    public function assign(BankTransaction $transaction, FinanceInvoice $invoice, int $userId, bool $manual = true): BankTransaction
    {
        if ($transaction->status === 'matched') {
            throw ValidationException::withMessages(['bank' => 'Dieser Umsatz wurde bereits zugeordnet.']);
        }
        if ((float) $transaction->amount <= 0) {
            throw ValidationException::withMessages(['bank' => 'Nur Zahlungseingänge können einer Rechnung zugeordnet werden.']);
        }
        $invoice->loadMissing('creditNotes');
        if (! in_array($invoice->status, ['open', 'overdue'], true) || $invoice->open_amount <= 0) {
            throw ValidationException::withMessages(['bank' => 'Die gewählte Rechnung hat keinen offenen Betrag.']);
        }

        $payment = $this->finance->recordPayment($invoice, [
            'amount' => (float) $transaction->amount,
            'paid_at' => $transaction->booking_date->toDateString(),
            'method' => 'bank_transfer',
            'reference' => Str::limit((string) $transaction->reference, 180, ''),
            'notes' => 'Bankimport: '.$transaction->batch?->original_name,
            'bank_transaction_id' => $transaction->id,
        ], $userId);
        $transaction->update([
            'status' => 'matched', 'finance_invoice_id' => $invoice->id, 'member_id' => $invoice->member_id,
            'finance_payment_id' => $payment->id, 'match_confidence' => $manual ? 100 : $transaction->match_confidence,
            'match_reason' => $manual ? 'Manuell zugeordnet' : $transaction->match_reason,
        ]);

        return $transaction->fresh();
    }

    private function autoMatch(BankTransaction $transaction, int $userId): bool
    {
        if ((float) $transaction->amount <= 0) {
            return false;
        }
        $reference = (string) $transaction->reference;
        if (preg_match('/RE-\d{4}-\d{6}/i', $reference, $match)) {
            $invoice = FinanceInvoice::query()->with('creditNotes')->where('invoice_number', strtoupper($match[0]))->first();
            if ($invoice && in_array($invoice->status, ['open', 'overdue'], true) && (float) $transaction->amount <= $invoice->open_amount + 0.01) {
                $transaction->update(['match_confidence' => 98, 'match_reason' => 'Rechnungsnummer im Verwendungszweck']);
                $this->assign($transaction, $invoice, $userId, false);

                return true;
            }
        }

        if (preg_match('/M-\d{1,12}/i', $reference, $match)) {
            $invoices = FinanceInvoice::query()->with(['member', 'creditNotes'])
                ->whereHas('member', fn ($query) => $query->where('member_number', strtoupper($match[0])))
                ->whereIn('status', ['open', 'overdue'])->get()
                ->filter(fn (FinanceInvoice $invoice) => abs($invoice->open_amount - (float) $transaction->amount) < 0.01);
            if ($invoices->count() === 1) {
                $transaction->update(['match_confidence' => 85, 'match_reason' => 'Mitgliedsnummer und Betrag stimmen überein']);
                $this->assign($transaction, $invoices->first(), $userId, false);

                return true;
            }
        }

        return false;
    }

    private function csvRows(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $firstLine = strtok($contents, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        if (! $headers) {
            fclose($handle);

            return [];
        }
        $headers = array_map(fn ($header) => mb_strtolower(trim((string) $header)), $headers);
        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
        }
        fclose($handle);

        return $rows;
    }

    private function normalizeRow(array $row): ?array
    {
        $date = $this->value($row, ['buchungstag', 'buchungsdatum', 'booking_date', 'datum', 'date']);
        $amountRaw = $this->value($row, ['betrag', 'amount', 'umsatz']);
        if (! $date || $amountRaw === null || trim((string) $amountRaw) === '') {
            return null;
        }
        $bookingDate = $this->date($date);
        $amount = $this->amount((string) $amountRaw);
        if (! $bookingDate || $amount === null) {
            return null;
        }
        $valueDateRaw = $this->value($row, ['valutadatum', 'wertstellung', 'value_date']);

        return [
            'booking_date' => $bookingDate,
            'value_date' => $valueDateRaw ? $this->date($valueDateRaw) : null,
            'amount' => $amount,
            'currency' => strtoupper((string) ($this->value($row, ['waehrung', 'währung', 'currency']) ?: 'EUR')),
            'payer_name' => $this->value($row, ['name zahlungsbeteiligter', 'auftraggeber', 'empfaenger', 'empfänger', 'payer_name', 'name']),
            'payer_iban' => preg_replace('/\s+/', '', (string) ($this->value($row, ['iban', 'payer_iban']) ?: '')) ?: null,
            'reference' => $this->value($row, ['verwendungszweck', 'buchungstext', 'reference', 'zweck', 'purpose']),
        ];
    }

    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    private function date(string $value): ?string
    {
        foreach (['d.m.Y', 'Y-m-d', 'd.m.y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, trim($value))->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function amount(string $value): ?float
    {
        $value = trim(str_replace(["\u{00A0}", '€', ' '], '', $value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
