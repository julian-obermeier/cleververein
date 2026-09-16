<?php

namespace App\Services\Finance;

use App\Models\FinanceCreditNote;
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
}
