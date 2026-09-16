<?php

namespace App\Services\Finance;

use App\Models\FinanceInvoice;
use App\Models\FinanceSetting;
use App\Models\SepaBatch;
use App\Models\SepaMandate;
use App\Support\Tenancy\TenantContext;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SepaExportService
{
    public function __construct(private TenantContext $tenant) {}

    public function createBatch(string $collectionDate, int $userId): SepaBatch
    {
        $settings = FinanceSetting::query()->first();
        $this->assertSettings($settings);

        $invoices = FinanceInvoice::query()
            ->with(['member.person', 'creditNotes'])
            ->whereIn('status', ['open', 'overdue'])
            ->whereNotNull('member_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('sepa_batch_items')
                    ->whereColumn('sepa_batch_items.finance_invoice_id', 'finance_invoices.id')
                    ->whereIn('sepa_batch_items.status', ['pending', 'generated', 'submitted']);
            })
            ->orderBy('id')
            ->limit(1000)
            ->get();

        $eligible = [];
        foreach ($invoices as $invoice) {
            if ($invoice->open_amount <= 0) {
                continue;
            }
            $mandate = SepaMandate::query()->where('member_id', $invoice->member_id)->where('status', 'active')->latest('id')->first();
            if (! $mandate) {
                continue;
            }
            $eligible[] = [$invoice, $mandate];
        }
        if ($eligible === []) {
            throw ValidationException::withMessages(['sepa' => 'Es gibt keine offenen Rechnungen mit aktivem SEPA-Mandat.']);
        }

        return DB::transaction(function () use ($collectionDate, $userId, $eligible): SepaBatch {
            $batch = SepaBatch::query()->create([
                'public_id' => Str::uuid(),
                'batch_reference' => 'SDD-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'collection_date' => $collectionDate,
                'status' => 'draft',
                'created_by' => $userId,
            ]);
            $total = 0.0;
            foreach ($eligible as [$invoice, $mandate]) {
                $amount = round($invoice->open_amount, 2);
                $batch->items()->create([
                    'finance_invoice_id' => $invoice->id,
                    'sepa_mandate_id' => $mandate->id,
                    'amount' => $amount,
                    'sequence_type' => $mandate->collection_count > 0 ? 'RCUR' : 'FRST',
                    'end_to_end_id' => $this->endToEnd($invoice),
                    'status' => 'pending',
                ]);
                $total += $amount;
            }
            $batch->update(['transaction_count' => count($eligible), 'total_amount' => round($total, 2)]);

            return $batch->fresh(['items.invoice.member.person', 'items.mandate']);
        });
    }

    public function generate(SepaBatch $batch): SepaBatch
    {
        $settings = FinanceSetting::query()->first();
        $this->assertSettings($settings);
        $batch->loadMissing(['items.invoice.member.person', 'items.mandate']);
        abort_if($batch->items->isEmpty(), 422, 'Der SEPA-Lauf enthält keine Positionen.');

        $xml = $this->xml($batch, $settings);
        $path = 'finance-documents/'.$this->tenant->id().'/sepa/'.$batch->public_id.'.xml';
        Storage::disk('local')->put($path, $xml);
        $batch->update([
            'status' => 'generated', 'file_disk' => 'local', 'file_path' => $path,
            'file_size' => strlen($xml), 'generated_at' => now(),
        ]);
        $batch->items()->update(['status' => 'generated']);

        return $batch->fresh();
    }

    public function submit(SepaBatch $batch): SepaBatch
    {
        if (! in_array($batch->status, ['generated', 'exported'], true)) {
            throw ValidationException::withMessages(['sepa' => 'Nur erzeugte SEPA-Läufe können als eingereicht markiert werden.']);
        }

        DB::transaction(function () use ($batch): void {
            $batch->loadMissing('items.mandate');
            foreach ($batch->items as $item) {
                $item->mandate->increment('collection_count');
                $item->mandate->update(['last_collected_at' => $batch->collection_date]);
                $item->update(['status' => 'submitted']);
            }
            $batch->update(['status' => 'submitted']);
        });

        return $batch->fresh();
    }

    private function xml(SepaBatch $batch, FinanceSetting $settings): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $document = $dom->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.008.001.08', 'Document');
        $dom->appendChild($document);
        $root = $document->appendChild($dom->createElement('CstmrDrctDbtInitn'));
        $group = $root->appendChild($dom->createElement('GrpHdr'));
        $this->node($dom, $group, 'MsgId', $batch->batch_reference);
        $this->node($dom, $group, 'CreDtTm', now()->format('Y-m-d\TH:i:sP'));
        $this->node($dom, $group, 'NbOfTxs', (string) $batch->transaction_count);
        $this->node($dom, $group, 'CtrlSum', number_format((float) $batch->total_amount, 2, '.', ''));
        $party = $group->appendChild($dom->createElement('InitgPty'));
        $this->node($dom, $party, 'Nm', $settings->creditor_name ?: $this->tenant->tenant()->name);

        foreach ($batch->items->groupBy('sequence_type') as $sequence => $items) {
            $pmt = $root->appendChild($dom->createElement('PmtInf'));
            $this->node($dom, $pmt, 'PmtInfId', $batch->batch_reference.'-'.$sequence);
            $this->node($dom, $pmt, 'PmtMtd', 'DD');
            $this->node($dom, $pmt, 'BtchBookg', 'true');
            $this->node($dom, $pmt, 'NbOfTxs', (string) $items->count());
            $this->node($dom, $pmt, 'CtrlSum', number_format((float) $items->sum('amount'), 2, '.', ''));
            $type = $pmt->appendChild($dom->createElement('PmtTpInf'));
            $svc = $type->appendChild($dom->createElement('SvcLvl')); $this->node($dom, $svc, 'Cd', 'SEPA');
            $local = $type->appendChild($dom->createElement('LclInstrm')); $this->node($dom, $local, 'Cd', 'CORE');
            $this->node($dom, $type, 'SeqTp', $sequence);
            $this->node($dom, $pmt, 'ReqdColltnDt', $batch->collection_date->format('Y-m-d'));

            $creditor = $pmt->appendChild($dom->createElement('Cdtr'));
            $this->node($dom, $creditor, 'Nm', $settings->creditor_name ?: $this->tenant->tenant()->name);
            $this->postalAddress($dom, $creditor, $settings->street, $settings->postal_code, $settings->city, $settings->country ?: 'DE');
            $creditorAccount = $pmt->appendChild($dom->createElement('CdtrAcct'))->appendChild($dom->createElement('Id'));
            $this->node($dom, $creditorAccount, 'IBAN', preg_replace('/\s+/', '', (string) $settings->iban));
            $creditorAgent = $pmt->appendChild($dom->createElement('CdtrAgt'))->appendChild($dom->createElement('FinInstnId'));
            $this->agent($dom, $creditorAgent, $settings->bic);
            $this->node($dom, $pmt, 'ChrgBr', 'SLEV');
            $scheme = $pmt->appendChild($dom->createElement('CdtrSchmeId'))->appendChild($dom->createElement('Id'))->appendChild($dom->createElement('PrvtId'))->appendChild($dom->createElement('Othr'));
            $this->node($dom, $scheme, 'Id', $settings->creditor_id);
            $schemeName = $scheme->appendChild($dom->createElement('SchmeNm')); $this->node($dom, $schemeName, 'Prtry', 'SEPA');

            foreach ($items as $item) {
                $mandate = $item->mandate;
                $invoice = $item->invoice;
                $transaction = $pmt->appendChild($dom->createElement('DrctDbtTxInf'));
                $paymentId = $transaction->appendChild($dom->createElement('PmtId'));
                $this->node($dom, $paymentId, 'EndToEndId', $item->end_to_end_id);
                $amount = $dom->createElement('InstdAmt', number_format((float) $item->amount, 2, '.', ''));
                $amount->setAttribute('Ccy', 'EUR'); $transaction->appendChild($amount);
                $mandateInfo = $transaction->appendChild($dom->createElement('DrctDbtTx'))->appendChild($dom->createElement('MndtRltdInf'));
                $this->node($dom, $mandateInfo, 'MndtId', $mandate->mandate_reference);
                $this->node($dom, $mandateInfo, 'DtOfSgntr', $mandate->signed_at->format('Y-m-d'));
                $debtorAgent = $transaction->appendChild($dom->createElement('DbtrAgt'))->appendChild($dom->createElement('FinInstnId'));
                $this->agent($dom, $debtorAgent, $mandate->bic);
                $debtor = $transaction->appendChild($dom->createElement('Dbtr'));
                $this->node($dom, $debtor, 'Nm', $mandate->account_holder);
                $contact = $invoice->member?->person?->contact_data ?? [];
                $this->postalAddress($dom, $debtor, $contact['street'] ?? null, $contact['postal_code'] ?? null, $contact['city'] ?? null, $contact['country'] ?? 'DE');
                $debtorAccount = $transaction->appendChild($dom->createElement('DbtrAcct'))->appendChild($dom->createElement('Id'));
                $this->node($dom, $debtorAccount, 'IBAN', preg_replace('/\s+/', '', (string) $mandate->iban));
                $remittance = $transaction->appendChild($dom->createElement('RmtInf'));
                $this->node($dom, $remittance, 'Ustrd', Str::limit('Rechnung '.$invoice->invoice_number, 140, ''));
            }
        }

        return $dom->saveXML();
    }

    private function postalAddress(DOMDocument $dom, \DOMElement $party, ?string $street, ?string $postal, ?string $city, ?string $country): void
    {
        if (! $street && ! $postal && ! $city) {
            return;
        }
        $address = $party->appendChild($dom->createElement('PstlAdr'));
        if ($street) { $this->node($dom, $address, 'StrtNm', $street); }
        if ($postal) { $this->node($dom, $address, 'PstCd', $postal); }
        if ($city) { $this->node($dom, $address, 'TwnNm', $city); }
        if ($country) { $this->node($dom, $address, 'Ctry', strtoupper(substr($country, 0, 2))); }
    }

    private function agent(DOMDocument $dom, \DOMElement $institution, ?string $bic): void
    {
        if ($bic) {
            $this->node($dom, $institution, 'BICFI', strtoupper(preg_replace('/\s+/', '', $bic)));
            return;
        }
        $other = $institution->appendChild($dom->createElement('Othr'));
        $this->node($dom, $other, 'Id', 'NOTPROVIDED');
    }

    private function node(DOMDocument $dom, \DOMElement $parent, string $name, string|int|float|null $value): \DOMElement
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode((string) $value));
        $parent->appendChild($node);

        return $node;
    }

    private function endToEnd(FinanceInvoice $invoice): string
    {
        $raw = 'INV-'.($invoice->invoice_number ?: $invoice->id);
        $clean = preg_replace('/[^A-Za-z0-9+?\/:().,\' -]/', '-', $raw) ?: 'NOTPROVIDED';

        return Str::limit($clean, 35, '');
    }

    private function assertSettings(?FinanceSetting $settings): void
    {
        if (! $settings || ! $settings->creditor_name || ! $settings->creditor_id || ! $settings->iban) {
            throw ValidationException::withMessages(['sepa' => 'Für SEPA müssen Gläubigername, Gläubiger-ID und Gläubiger-IBAN in den Finanzstammdaten gepflegt sein.']);
        }
    }
}
