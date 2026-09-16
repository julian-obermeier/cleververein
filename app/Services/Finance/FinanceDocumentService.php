<?php

namespace App\Services\Finance;

use App\Models\FinanceCreditNote;
use App\Models\FinanceDonationCertificate;
use App\Models\FinanceDunning;
use App\Models\FinanceInvoice;
use App\Models\FinanceSetting;
use App\Support\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class FinanceDocumentService
{
    public function __construct(
        private TenantContext $tenant,
        private FinanceRecipientService $recipients,
    ) {}

    public function invoice(FinanceInvoice $invoice): FinanceInvoice
    {
        abort_if($invoice->status === 'draft', 422, 'Entwürfe können noch nicht als verbindliche Rechnung ausgegeben werden.');
        $invoice->loadMissing(['member.person', 'household.members.person', 'items', 'payments', 'creditNotes']);
        if (! $invoice->recipient_snapshot) {
            $invoice->update(['recipient_snapshot' => $this->recipients->snapshot($invoice->member, $invoice->household)]);
        }

        $pdf = $this->render('finance.pdf.invoice', [
            'invoice' => $invoice->fresh(['items', 'creditNotes']),
            'settings' => FinanceSetting::query()->first(),
            'tenant' => $this->tenant->tenant(),
        ]);
        $path = 'finance-documents/'.$this->tenant->id().'/invoices/'.$invoice->public_id.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $invoice->update(['pdf_disk' => 'local', 'pdf_path' => $path, 'pdf_size' => strlen($pdf), 'pdf_generated_at' => now()]);

        return $invoice->fresh();
    }

    public function dunning(FinanceDunning $dunning): FinanceDunning
    {
        $dunning->loadMissing(['invoice.member.person', 'invoice.household.members.person', 'invoice.items', 'invoice.creditNotes']);
        $invoice = $dunning->invoice;
        if (! $invoice->recipient_snapshot) {
            $invoice->update(['recipient_snapshot' => $this->recipients->snapshot($invoice->member, $invoice->household)]);
        }
        $pdf = $this->render('finance.pdf.dunning', [
            'dunning' => $dunning,
            'invoice' => $invoice->fresh(['items', 'creditNotes']),
            'settings' => FinanceSetting::query()->first(),
            'tenant' => $this->tenant->tenant(),
        ]);
        $path = 'finance-documents/'.$this->tenant->id().'/dunnings/'.$dunning->id.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $dunning->update(['pdf_disk' => 'local', 'pdf_path' => $path, 'pdf_size' => strlen($pdf), 'pdf_generated_at' => now()]);

        return $dunning->fresh();
    }

    public function creditNote(FinanceCreditNote $creditNote): FinanceCreditNote
    {
        $creditNote->loadMissing(['invoice', 'member.person', 'household.members.person']);
        if (! $creditNote->recipient_snapshot) {
            $creditNote->update(['recipient_snapshot' => $this->recipients->snapshot($creditNote->member, $creditNote->household)]);
        }
        $pdf = $this->render('finance.pdf.credit-note', [
            'creditNote' => $creditNote,
            'settings' => FinanceSetting::query()->first(),
            'tenant' => $this->tenant->tenant(),
        ]);
        $path = 'finance-documents/'.$this->tenant->id().'/credits/'.$creditNote->public_id.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $creditNote->update(['pdf_disk' => 'local', 'pdf_path' => $path, 'pdf_size' => strlen($pdf), 'pdf_generated_at' => now()]);

        return $creditNote->fresh();
    }

    public function donationCertificate(FinanceDonationCertificate $certificate): FinanceDonationCertificate
    {
        abort_unless(in_array($certificate->status, ['issued', 'voided'], true), 422, 'Die Zuwendungsbestätigung ist nicht ausgabefähig.');

        $pdf = $this->render('finance.pdf.donation-certificate', [
            'certificate' => $certificate,
            'amountWords' => $this->amountInWords((float) $certificate->amount),
        ]);
        $path = 'finance-documents/'.$this->tenant->id().'/donation-certificates/'.$certificate->public_id.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $certificate->update([
            'pdf_disk' => 'local',
            'pdf_path' => $path,
            'pdf_size' => strlen($pdf),
            'pdf_generated_at' => now(),
        ]);

        return $certificate->fresh();
    }

    private function render(string $view, array $data): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function amountInWords(float $amount): string
    {
        $rounded = round($amount, 2);
        $euros = (int) floor($rounded);
        $cents = (int) round(($rounded - $euros) * 100);
        if ($cents === 100) {
            $euros++;
            $cents = 0;
        }

        $words = ucfirst($this->integerInGerman($euros, true)).' Euro';
        if ($cents > 0) {
            $words .= ' und '.$this->integerInGerman($cents, true).' Cent';
        }

        return $words;
    }

    private function integerInGerman(int $number, bool $standalone = false): string
    {
        if ($number === 0) {
            return 'null';
        }
        if ($number < 0) {
            return 'minus '.$this->integerInGerman(abs($number), $standalone);
        }
        if ($number >= 1000000000) {
            $billions = intdiv($number, 1000000000);
            $rest = $number % 1000000000;
            $prefix = $billions === 1 ? 'eine Milliarde' : $this->integerInGerman($billions, true).' Milliarden';

            return $prefix.($rest ? ' '.$this->integerInGerman($rest, $standalone) : '');
        }
        if ($number >= 1000000) {
            $millions = intdiv($number, 1000000);
            $rest = $number % 1000000;
            $prefix = $millions === 1 ? 'eine Million' : $this->integerInGerman($millions, true).' Millionen';

            return $prefix.($rest ? ' '.$this->integerInGerman($rest, $standalone) : '');
        }
        if ($number >= 1000) {
            $thousands = intdiv($number, 1000);
            $rest = $number % 1000;
            $prefix = $thousands === 1 ? 'eintausend' : $this->integerInGerman($thousands).'tausend';

            return $prefix.($rest ? $this->integerInGerman($rest, $standalone) : '');
        }
        if ($number >= 100) {
            $hundreds = intdiv($number, 100);
            $rest = $number % 100;
            $prefix = ($hundreds === 1 ? 'ein' : $this->integerInGerman($hundreds)).'hundert';

            return $prefix.($rest ? $this->integerInGerman($rest, $standalone) : '');
        }

        $ones = [
            0 => '', 1 => 'ein', 2 => 'zwei', 3 => 'drei', 4 => 'vier', 5 => 'fünf',
            6 => 'sechs', 7 => 'sieben', 8 => 'acht', 9 => 'neun', 10 => 'zehn',
            11 => 'elf', 12 => 'zwölf', 13 => 'dreizehn', 14 => 'vierzehn', 15 => 'fünfzehn',
            16 => 'sechzehn', 17 => 'siebzehn', 18 => 'achtzehn', 19 => 'neunzehn',
        ];
        if ($number < 20) {
            if ($number === 1 && $standalone) {
                return 'eins';
            }

            return $ones[$number];
        }

        $tens = [2 => 'zwanzig', 3 => 'dreißig', 4 => 'vierzig', 5 => 'fünfzig', 6 => 'sechzig', 7 => 'siebzig', 8 => 'achtzig', 9 => 'neunzig'];
        $ten = intdiv($number, 10);
        $one = $number % 10;
        if ($one === 0) {
            return $tens[$ten];
        }

        return $ones[$one].'und'.$tens[$ten];
    }
}
