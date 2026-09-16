<?php

namespace App\Http\Controllers;

use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\FinanceInvoice;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\BankImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceBankImportController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private BankImportService $bank,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request);

        return view('finance.bank-import', [
            'batches' => BankImportBatch::query()->withCount([
                'transactions',
                'transactions as chargeback_count' => fn ($query) => $query->where('status', 'chargeback'),
                'transactions as unmatched_count' => fn ($query) => $query->where('status', 'unmatched'),
            ])->latest()->limit(40)->get(),
            'unmatched' => BankTransaction::query()->with('batch')->where('status', 'unmatched')->latest('booking_date')->limit(150)->get(),
            'chargebacks' => BankTransaction::query()->with(['batch', 'invoice', 'payment', 'adjustments'])
                ->where('status', 'chargeback')->latest('booking_date')->limit(80)->get(),
            'openInvoices' => FinanceInvoice::query()->with(['member.person', 'creditNotes'])
                ->whereIn('status', ['open', 'overdue'])->orderBy('due_date')->limit(500)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $request->validate([
            'bank_file' => ['required', 'file', 'mimes:csv,txt,xml', 'max:10240'],
        ]);
        $batch = $this->bank->import($data['bank_file'], $request->user()->id);
        $this->audit->record('finance.bank_imported', $batch, new: [
            'file_type' => $batch->file_type,
            'message_id' => $batch->message_id,
            'rows' => $batch->row_count,
            'matched' => $batch->matched_count,
        ]);

        $automatic = $batch->transactions()->where('status', 'chargeback')->count();
        $suffix = $automatic > 0 ? ", {$automatic} Rücklastschrift(en) automatisch verarbeitet" : '';

        return back()->with('success', "Bankimport abgeschlossen: {$batch->row_count} Umsätze, {$batch->matched_count} automatisch verarbeitet{$suffix}.");
    }

    private function authorizePermission(Request $request): void
    {
        abort_unless(
            $request->user()->is_super_admin || $this->permissions->allows($request->user(), 'finance.bank'),
            403,
            'Für diesen Bereich fehlt die Berechtigung.',
        );
    }
}
