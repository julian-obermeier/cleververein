<?php

namespace App\Http\Controllers;

use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\ContributionRate;
use App\Models\FinanceCreditNote;
use App\Models\FinanceDunning;
use App\Models\FinanceInvoice;
use App\Models\FinanceSetting;
use App\Models\Household;
use App\Models\SepaBatch;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\BankImportService;
use App\Services\Finance\FinanceDocumentService;
use App\Services\Finance\FinanceService;
use App\Services\Finance\SepaExportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceOperationsController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FinanceService $finance,
        private FinanceDocumentService $documents,
        private SepaExportService $sepa,
        private BankImportService $bank,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'finance.view');

        return view('finance.operations', [
            'settings' => FinanceSetting::query()->first(),
            'sepaBatches' => SepaBatch::query()->with('creator')->latest()->limit(20)->get(),
            'bankBatches' => BankImportBatch::query()->with('importer')->latest()->limit(20)->get(),
            'unmatched' => BankTransaction::query()->with('batch')->where('status', 'unmatched')->latest('booking_date')->limit(100)->get(),
            'openInvoices' => FinanceInvoice::query()->with(['member.person', 'creditNotes'])->whereIn('status', ['open', 'overdue'])->orderBy('due_date')->limit(500)->get(),
            'creditNotes' => FinanceCreditNote::query()->with('invoice')->latest()->limit(20)->get(),
            'households' => Household::query()->withCount('members')->orderBy('name')->get(),
            'householdRates' => ContributionRate::query()->where('scope', 'household')->where('is_active', true)->orderBy('name')->get(),
            'canManage' => $this->allows($request, 'finance.manage'),
            'canSepa' => $this->allows($request, 'finance.sepa'),
            'canBank' => $this->allows($request, 'finance.bank'),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'creditor_name' => ['required', 'string', 'max:180'],
            'street' => ['nullable', 'string', 'max:180'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'vat_id' => ['nullable', 'string', 'max:40'],
            'creditor_id' => ['nullable', 'string', 'max:80'],
            'iban' => ['nullable', 'string', 'min:15', 'max:34', 'regex:/^[A-Za-z]{2}[0-9A-Za-z ]+$/'],
            'bic' => ['nullable', 'string', 'max:11'],
            'payment_terms_days' => ['required', 'integer', 'between:1,180'],
            'invoice_footer' => ['nullable', 'string', 'max:3000'],
        ]);
        $settings = FinanceSetting::query()->firstOrNew();
        $settings->fill([
            ...$data,
            'country' => strtoupper($data['country']),
            'iban' => filled($data['iban'] ?? null) ? strtoupper(str_replace(' ', '', $data['iban'])) : $settings->iban,
            'bic' => filled($data['bic'] ?? null) ? strtoupper(str_replace(' ', '', $data['bic'])) : $settings->bic,
        ])->save();
        $this->audit->record('finance.settings_updated', $settings, new: $settings->only(['creditor_name', 'city', 'country', 'creditor_id', 'payment_terms_days']));

        return back()->with('success', 'Finanzstammdaten wurden gespeichert.');
    }

    public function invoicePdf(Request $request, FinanceInvoice $invoice): StreamedResponse
    {
        $this->authorizePermission($request, 'finance.documents');
        if (! $invoice->pdf_path || ! Storage::disk($invoice->pdf_disk ?: 'local')->exists($invoice->pdf_path)) {
            $invoice = $this->documents->invoice($invoice);
        }
        $this->audit->record('finance.invoice_pdf_downloaded', $invoice);

        return Storage::disk($invoice->pdf_disk)->download($invoice->pdf_path, ($invoice->invoice_number ?: 'rechnung').'.pdf');
    }

    public function dunningPdf(Request $request, FinanceDunning $dunning): StreamedResponse
    {
        $this->authorizePermission($request, 'finance.documents');
        if (! $dunning->pdf_path || ! Storage::disk($dunning->pdf_disk ?: 'local')->exists($dunning->pdf_path)) {
            $dunning = $this->documents->dunning($dunning);
        }
        $this->audit->record('finance.dunning_pdf_downloaded', $dunning);

        return Storage::disk($dunning->pdf_disk)->download($dunning->pdf_path, 'Mahnung-'.$dunning->invoice?->invoice_number.'-'.$dunning->level.'.pdf');
    }

    public function storeCredit(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
            'cancel_invoice' => ['nullable', 'boolean'],
        ]);
        $credit = $this->finance->createCreditNote($invoice, (float) $data['amount'], $data['reason'], $request->user()->id, (bool) ($data['cancel_invoice'] ?? false));
        $this->audit->record('finance.credit_note_created', $credit, new: ['credit_number' => $credit->credit_number, 'amount' => $credit->amount, 'invoice_id' => $invoice->id]);

        return back()->with('success', 'Gutschrift '.$credit->credit_number.' wurde erstellt.');
    }

    public function creditPdf(Request $request, FinanceCreditNote $creditNote): StreamedResponse
    {
        $this->authorizePermission($request, 'finance.documents');
        if (! $creditNote->pdf_path || ! Storage::disk($creditNote->pdf_disk ?: 'local')->exists($creditNote->pdf_path)) {
            $creditNote = $this->documents->creditNote($creditNote);
        }
        $this->audit->record('finance.credit_pdf_downloaded', $creditNote);

        return Storage::disk($creditNote->pdf_disk)->download($creditNote->pdf_path, $creditNote->credit_number.'.pdf');
    }

    public function runHouseholds(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'contribution_rate_id' => ['required', 'integer'],
        ]);
        $rate = ContributionRate::query()->where('scope', 'household')->where('is_active', true)->findOrFail($data['contribution_rate_id']);
        $created = 0;
        $checked = 0;
        Household::query()->with('members.person')->chunkById(100, function ($households) use ($rate, $data, $request, &$created, &$checked): void {
            foreach ($households as $household) {
                $checked++;
                $before = FinanceInvoice::query()->where('household_id', $household->id)->whereYear('invoice_date', $data['year'])
                    ->whereHas('items', fn ($query) => $query->where('contribution_rate_id', $rate->id))->exists();
                $this->finance->createHouseholdContributionDraft($household, $rate, (int) $data['year'], $request->user()->id);
                if (! $before) { $created++; }
            }
        });
        $this->audit->record('finance.household_contribution_run', null, new: ['year' => (int) $data['year'], 'rate_id' => $rate->id, 'checked' => $checked, 'created' => $created]);

        return back()->with('success', "Haushaltsbeitragslauf abgeschlossen: {$checked} Haushalte geprüft, {$created} neue Entwürfe erzeugt.");
    }

    public function createSepa(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.sepa');
        $data = $request->validate(['collection_date' => ['required', 'date', 'after_or_equal:today']]);
        $batch = $this->sepa->createBatch($data['collection_date'], $request->user()->id);
        $batch = $this->sepa->generate($batch);
        $this->audit->record('finance.sepa_batch_generated', $batch, new: ['transactions' => $batch->transaction_count, 'amount' => $batch->total_amount, 'collection_date' => $batch->collection_date]);

        return back()->with('success', 'SEPA-Lauf '.$batch->batch_reference.' wurde erzeugt.');
    }

    public function downloadSepa(Request $request, SepaBatch $batch): StreamedResponse
    {
        $this->authorizePermission($request, 'finance.sepa');
        if (! $batch->file_path || ! Storage::disk($batch->file_disk ?: 'local')->exists($batch->file_path)) {
            $batch = $this->sepa->generate($batch);
        }
        if ($batch->status === 'generated') { $batch->update(['status' => 'exported']); }
        $this->audit->record('finance.sepa_batch_downloaded', $batch);

        return Storage::disk($batch->file_disk)->download($batch->file_path, $batch->batch_reference.'.xml', ['Content-Type' => 'application/xml']);
    }

    public function submitSepa(Request $request, SepaBatch $batch): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.sepa');
        $batch = $this->sepa->submit($batch);
        $this->audit->record('finance.sepa_batch_submitted', $batch);

        return back()->with('success', 'SEPA-Lauf wurde als eingereicht markiert.');
    }

    public function importBank(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.bank');
        $data = $request->validate(['bank_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $batch = $this->bank->import($data['bank_file'], $request->user()->id);
        $this->audit->record('finance.bank_imported', $batch, new: ['rows' => $batch->row_count, 'matched' => $batch->matched_count]);

        return back()->with('success', "Bankimport abgeschlossen: {$batch->row_count} Umsätze, {$batch->matched_count} automatisch zugeordnet.");
    }

    public function assignBank(Request $request, BankTransaction $transaction): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.bank');
        $data = $request->validate(['finance_invoice_id' => ['required', 'integer']]);
        $invoice = FinanceInvoice::query()->findOrFail($data['finance_invoice_id']);
        $this->bank->assign($transaction, $invoice, $request->user()->id);
        $this->audit->record('finance.bank_transaction_matched', $transaction, new: ['invoice_id' => $invoice->id]);

        return back()->with('success', 'Bankumsatz wurde der Rechnung zugeordnet.');
    }

    public function ignoreBank(Request $request, BankTransaction $transaction): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.bank');
        abort_if($transaction->status === 'matched', 422, 'Bereits gebuchte Umsätze können nicht ignoriert werden.');
        $transaction->update(['status' => 'ignored', 'match_reason' => 'Manuell ignoriert']);
        $this->audit->record('finance.bank_transaction_ignored', $transaction);

        return back()->with('success', 'Bankumsatz wurde ignoriert.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->allows($request, $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function allows(Request $request, string $permission): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission);
    }
}
