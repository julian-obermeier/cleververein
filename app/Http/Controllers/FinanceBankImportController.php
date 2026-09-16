<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\BankImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinanceBankImportController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private BankImportService $bank,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->is_super_admin || $this->permissions->allows($request->user(), 'finance.bank'),
            403,
            'Für diese Aktion fehlt die Berechtigung.',
        );
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
}
