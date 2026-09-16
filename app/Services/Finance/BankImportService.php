<?php

namespace App\Services\Finance;

use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\FinancePaymentAdjustment;
use App\Models\SepaBatchItem;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankImportService
{
    public function __construct(
        private TenantContext $tenant,
        private FinanceService $finance,
        private FinanceRecoveryDonationService $recovery,
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

        [$rows, $fileType, $messageId] = $this->parse($contents, $file->getClientOriginalExtension());
        if ($rows === []) {
            throw ValidationException::withMessages(['bank_file' => 'Die Bankdatei enthält keine verwertbaren Umsätze.']);
        }

        return DB::transaction(function () use ($file, $hash, $rows, $fileType, $messageId, $userId): BankImportBatch {
            $batch = BankImportBatch::query()->create([
                'public_id' => Str::uuid(),
                'original_name' => $file->getClientOriginalName(),
                'file_hash' => $hash,
                'file_type' => $fileType,
                'message_id' => $messageId,
                'row_count' => 0,
                'matched_count' => 0,
                'imported_by' => $userId,
            ]);
            $stored = 0;
            $matched = 0;
            foreach ($rows as $row) {
                $normalized = $fileType === 'csv' ? $this->normalizeCsvRow($row) : $row;
                if (! $normalized) {
                    continue;
                }
                $externalId = hash('sha256', implode('|', [
                    $normalized['booking_date'],
                    $normalized['value_date'] ?? '',
                    $normalized['amount'],
                    $normalized['currency'] ?? 'EUR',
                    $normalized['payer_name'] ?? '',
                    $normalized['reference'] ?? '',
                    $normalized['payer_iban'] ?? '',
                    $normalized['end_to_end_id'] ?? '',
                    $normalized['mandate_reference'] ?? '',
                    $normalized['raw_details']['transaction_id'] ?? '',
                    $normalized['raw_details']['account_servicer_reference'] ?? '',
                ]));
                if (BankTransaction::query()->where('external_id', $externalId)->exists()) {
                    continue;
                }
                $transaction = $batch->transactions()->create([
                    ...$normalized,
                    'external_id' => $externalId,
                    'status' => 'unmatched',
                ]);
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
        if ($transaction->status !== 'unmatched') {
            throw ValidationException::withMessages(['bank' => 'Dieser Umsatz wurde bereits verarbeitet.']);
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
            'status' => 'matched',
            'finance_invoice_id' => $invoice->id,
            'member_id' => $invoice->member_id,
            'finance_payment_id' => $payment->id,
            'match_confidence' => $manual ? 100 : $transaction->match_confidence,
            'match_reason' => $manual ? 'Manuell zugeordnet' : $transaction->match_reason,
        ]);

        return $transaction->fresh();
    }

    private function autoMatch(BankTransaction $transaction, int $userId): bool
    {
        if ((float) $transaction->amount < 0) {
            return $this->autoMatchChargeback($transaction, $userId);
        }
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

    private function autoMatchChargeback(BankTransaction $transaction, int $userId): bool
    {
        $endToEndId = trim((string) $transaction->end_to_end_id);
        if ($endToEndId === '' || strtoupper($endToEndId) === 'NOTPROVIDED') {
            return false;
        }

        $item = SepaBatchItem::query()
            ->with(['invoice.member', 'batch'])
            ->where('end_to_end_id', $endToEndId)
            ->where('status', 'submitted')
            ->latest('id')
            ->first();
        if (! $item || ! $item->invoice) {
            return false;
        }

        $amount = abs(round((float) $transaction->amount, 2));
        if (abs((float) $item->amount - $amount) > 0.01) {
            return false;
        }

        $payment = FinancePayment::query()
            ->with('adjustments')
            ->where('finance_invoice_id', $item->finance_invoice_id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->first(function (FinancePayment $payment) use ($amount): bool {
                $alreadyAdjusted = (float) $payment->adjustments->where('status', 'posted')->sum('amount');

                return round((float) $payment->amount - $alreadyAdjusted, 2) + 0.001 >= $amount;
            });
        if (! $payment || FinancePaymentAdjustment::query()->where('bank_transaction_id', $transaction->id)->exists()) {
            return false;
        }

        $reasonParts = array_filter([
            $transaction->return_reason_code ? 'Rückgabegrund '.$transaction->return_reason_code : null,
            $transaction->return_reason_text,
            'SEPA-Rücklastschrift aus CAMT-Import',
        ]);
        $adjustment = $this->recovery->adjustPayment($payment, [
            'type' => 'chargeback',
            'amount' => $amount,
            'fee_amount' => 0,
            'adjustment_date' => $transaction->booking_date->toDateString(),
            'reason' => Str::limit(implode(' · ', $reasonParts), 255, ''),
            'reference' => Str::limit((string) ($transaction->reference ?: $endToEndId), 180, ''),
            'bank_transaction_id' => $transaction->id,
        ], $userId);

        $transaction->update([
            'status' => 'chargeback',
            'finance_invoice_id' => $item->finance_invoice_id,
            'member_id' => $item->invoice->member_id,
            'finance_payment_id' => $payment->id,
            'match_confidence' => 100,
            'match_reason' => 'SEPA-EndToEnd-ID und Betrag stimmen mit eingereichtem Lastschriftlauf überein; Korrektur '.$adjustment->public_id,
        ]);

        return true;
    }

    private function parse(string $contents, string $extension): array
    {
        $trimmed = ltrim($contents, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
        if (str_starts_with($trimmed, '<') || strtolower($extension) === 'xml') {
            return $this->camtRows($contents);
        }

        return [$this->csvRows($contents), 'csv', null];
    }

    private function camtRows(string $contents): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw ValidationException::withMessages(['bank_file' => 'Die XML-Datei ist nicht wohlgeformt.']);
        }

        $xpath = new DOMXPath($dom);
        $documentElement = $dom->documentElement;
        $namespace = $documentElement?->namespaceURI ?? '';
        $xml = $dom->saveXML() ?: '';
        $type = str_contains($namespace, 'camt.053') || str_contains($xml, 'BkToCstmrStmt') ? 'camt053'
            : (str_contains($namespace, 'camt.054') || str_contains($xml, 'BkToCstmrDbtCdtNtfctn') ? 'camt054' : 'xml');
        if ($type === 'xml') {
            throw ValidationException::withMessages(['bank_file' => 'Unterstützt werden CAMT.053- und CAMT.054-Dateien.']);
        }

        $messageId = $this->string($xpath, $dom, '(//*[local-name()="GrpHdr"]/*[local-name()="MsgId"])[1]');
        $entries = $xpath->query('//*[local-name()="Ntry"]');
        $rows = [];
        foreach ($entries ?: [] as $entry) {
            if (! $entry instanceof DOMElement) {
                continue;
            }
            $txNodes = $xpath->query('./*[local-name()="NtryDtls"]//*[local-name()="TxDtls"]', $entry);
            if ($txNodes && $txNodes->length > 0) {
                foreach ($txNodes as $tx) {
                    if ($tx instanceof DOMElement) {
                        $row = $this->camtRow($xpath, $entry, $tx);
                        if ($row) {
                            $rows[] = $row;
                        }
                    }
                }
            } else {
                $row = $this->camtRow($xpath, $entry, $entry);
                if ($row) {
                    $rows[] = $row;
                }
            }
        }

        return [$rows, $type, $messageId ?: null];
    }

    private function camtRow(DOMXPath $xpath, DOMElement $entry, DOMElement $tx): ?array
    {
        $entryAmountNode = $this->node($xpath, $entry, './*[local-name()="Amt"][1]');
        $txAmountNode = $tx === $entry ? null : $this->node($xpath, $tx, '(.//*[local-name()="AmtDtls"]/*[local-name()="TxAmt"]/*[local-name()="Amt"])[1]');
        $amountNode = $txAmountNode ?: $entryAmountNode;
        if (! $amountNode) {
            return null;
        }
        $amount = is_numeric(trim($amountNode->textContent)) ? round((float) trim($amountNode->textContent), 2) : null;
        if ($amount === null) {
            return null;
        }

        $indicator = strtoupper($this->string($xpath, $tx, '(.//*[local-name()="CdtDbtInd"])[1]') ?: $this->string($xpath, $entry, './*[local-name()="CdtDbtInd"][1]'));
        if ($indicator === 'DBIT') {
            $amount *= -1;
        }
        $bookingDate = $this->camtDate($this->string($xpath, $entry, './*[local-name()="BookgDt"]/*[local-name()="Dt" or local-name()="DtTm"][1]'));
        if (! $bookingDate) {
            return null;
        }
        $valueDate = $this->camtDate($this->string($xpath, $entry, './*[local-name()="ValDt"]/*[local-name()="Dt" or local-name()="DtTm"][1]'));

        $partyName = $indicator === 'CRDT'
            ? $this->string($xpath, $tx, '(.//*[local-name()="RltdPties"]/*[local-name()="Dbtr"]/*[local-name()="Nm"])[1]')
            : $this->string($xpath, $tx, '(.//*[local-name()="RltdPties"]/*[local-name()="Cdtr"]/*[local-name()="Nm"])[1]');
        $partyName = $partyName ?: $this->string($xpath, $tx, '(.//*[local-name()="RltdPties"]//*[local-name()="Nm"])[1]');
        $partyIban = $indicator === 'CRDT'
            ? $this->string($xpath, $tx, '(.//*[local-name()="RltdPties"]/*[local-name()="DbtrAcct"]//*[local-name()="IBAN"])[1]')
            : $this->string($xpath, $tx, '(.//*[local-name()="RltdPties"]/*[local-name()="CdtrAcct"]//*[local-name()="IBAN"])[1]');
        $partyIban = $partyIban ?: $this->string($xpath, $tx, '(.//*[local-name()="RltdPties"]//*[local-name()="IBAN"])[1]');

        $references = [];
        $remittanceNodes = $xpath->query('.//*[local-name()="RmtInf"]/*[local-name()="Ustrd"]', $tx);
        foreach ($remittanceNodes ?: [] as $node) {
            $value = trim($node->textContent);
            if ($value !== '') {
                $references[] = $value;
            }
        }
        $additional = $this->string($xpath, $tx, '(.//*[local-name()="AddtlTxInf"])[1]');
        if ($additional) {
            $references[] = $additional;
        }

        $domain = $this->string($xpath, $entry, '(.//*[local-name()="BkTxCd"]/*[local-name()="Domn"]/*[local-name()="Cd"])[1]');
        $family = $this->string($xpath, $entry, '(.//*[local-name()="BkTxCd"]/*[local-name()="Domn"]/*[local-name()="Fmly"]/*[local-name()="Cd"])[1]');
        $subFamily = $this->string($xpath, $entry, '(.//*[local-name()="BkTxCd"]/*[local-name()="Domn"]/*[local-name()="Fmly"]/*[local-name()="SubFmlyCd"])[1]');
        $proprietary = $this->string($xpath, $entry, '(.//*[local-name()="BkTxCd"]/*[local-name()="Prtry"]/*[local-name()="Cd"])[1]');
        $bankCode = implode('/', array_filter([$domain, $family, $subFamily])) ?: $proprietary;

        $returnReason = $this->string($xpath, $tx, '(.//*[local-name()="RtrInf"]/*[local-name()="Rsn"]/*[local-name()="Cd"])[1]')
            ?: $this->string($xpath, $tx, '(.//*[local-name()="RtrInf"]/*[local-name()="Rsn"]/*[local-name()="Prtry"])[1]');
        $returnText = $this->string($xpath, $tx, '(.//*[local-name()="RtrInf"]/*[local-name()="AddtlInf"])[1]');

        return [
            'booking_date' => $bookingDate,
            'value_date' => $valueDate,
            'amount' => $amount,
            'currency' => strtoupper($amountNode instanceof DOMElement && $amountNode->hasAttribute('Ccy') ? $amountNode->getAttribute('Ccy') : 'EUR'),
            'payer_name' => $partyName ?: null,
            'payer_iban' => $partyIban ? preg_replace('/\s+/', '', $partyIban) : null,
            'reference' => $references !== [] ? Str::limit(implode(' · ', array_unique($references)), 2000, '') : null,
            'end_to_end_id' => $this->string($xpath, $tx, '(.//*[local-name()="Refs"]/*[local-name()="EndToEndId"])[1]') ?: null,
            'mandate_reference' => $this->string($xpath, $tx, '(.//*[local-name()="Refs"]/*[local-name()="MndtId"])[1]') ?: null,
            'bank_transaction_code' => $bankCode ?: null,
            'return_reason_code' => $returnReason ?: null,
            'return_reason_text' => $returnText ?: null,
            'raw_details' => array_filter([
                'account_servicer_reference' => $this->string($xpath, $tx, '(.//*[local-name()="Refs"]/*[local-name()="AcSvcrRef"])[1]') ?: $this->string($xpath, $entry, './*[local-name()="AcSvcrRef"][1]'),
                'instruction_id' => $this->string($xpath, $tx, '(.//*[local-name()="Refs"]/*[local-name()="InstrId"])[1]'),
                'transaction_id' => $this->string($xpath, $tx, '(.//*[local-name()="Refs"]/*[local-name()="TxId"])[1]'),
            ]),
        ];
    }

    private function node(DOMXPath $xpath, DOMNode $context, string $expression): ?DOMNode
    {
        $nodes = $xpath->query($expression, $context);

        return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function string(DOMXPath $xpath, DOMNode $context, string $expression): string
    {
        $node = $this->node($xpath, $context, $expression);

        return $node ? trim($node->textContent) : '';
    }

    private function camtDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
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

    private function normalizeCsvRow(array $row): ?array
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
            'end_to_end_id' => $this->value($row, ['endtoendid', 'end_to_end_id', 'ende-zu-ende-referenz']),
            'mandate_reference' => $this->value($row, ['mandatsreferenz', 'mandate_reference']),
            'bank_transaction_code' => null,
            'return_reason_code' => null,
            'return_reason_text' => null,
            'raw_details' => null,
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
