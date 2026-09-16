<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Models\FinanceCashAudit;
use App\Models\FinanceCashClosing;
use App\Models\FinanceEntry;
use App\Models\FinancePeriodLock;
use App\Models\FinanceReceipt;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\FinanceControlService;
use App\Services\Finance\FinanceLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceControlController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FinanceControlService $controls,
        private FinanceLedgerService $ledger,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAny($request, ['finance.cash', 'finance.periods', 'finance.audit', 'finance.receipts', 'finance.view']);
        $this->ledger->ensureDefaults();
        $cashAccounts = FinanceAccount::query()->where('type', 'cash')->orderBy('sort_order')->orderBy('name')->get();
        $selectedCash = $request->filled('cash_account')
            ? $cashAccounts->firstWhere('id', $request->integer('cash_account'))
            : $cashAccounts->first();

        $receiptEntries = FinanceEntry::query()
            ->with(['account', 'category', 'receipts.uploader'])
            ->when($request->filled('receipt_q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('receipt_q'));
                $query->where(fn ($nested) => $nested->where('entry_number', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%"));
            })
            ->orderByDesc('booking_date')->orderByDesc('id')->limit(60)->get();

        $cashEntries = collect();
        $cashBalance = null;
        if ($selectedCash) {
            $cashEntries = FinanceEntry::query()->where('finance_account_id', $selectedCash->id)
                ->with(['category', 'receipts'])->orderByDesc('booking_date')->orderByDesc('id')->limit(100)->get();
            $cashBalance = $this->controls->balance($selectedCash, now()->toDateString());
        }

        return view('finance.cash-controls', [
            'cashAccounts' => $cashAccounts,
            'selectedCash' => $selectedCash,
            'cashEntries' => $cashEntries,
            'cashBalance' => $cashBalance,
            'receiptEntries' => $receiptEntries,
            'closings' => FinanceCashClosing::query()->with(['account', 'closer'])->latest('closing_date')->limit(40)->get(),
            'periodLocks' => FinancePeriodLock::query()->with(['locker', 'unlocker'])->latest('period_end')->limit(40)->get(),
            'cashAudits' => FinanceCashAudit::query()->with(['account', 'auditor'])->latest('audited_at')->limit(40)->get(),
            'canReceipts' => $this->allows($request, 'finance.receipts'),
            'canCash' => $this->allows($request, 'finance.cash'),
            'canPeriods' => $this->allows($request, 'finance.periods'),
            'canAudit' => $this->allows($request, 'finance.audit'),
            'canReports' => $this->allows($request, 'finance.reports') || $this->allows($request, 'finance.cash'),
        ]);
    }

    public function storeReceipt(Request $request, FinanceEntry $entry): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.receipts');
        $data = $request->validate([
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt', 'max:15360'],
            'document_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $data['receipt'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $directory = 'finance/'.$this->tenant->id().'/receipts/'.$entry->public_id;
        $filename = (string) Str::uuid().'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'local');

        $receipt = FinanceReceipt::query()->create([
            'public_id' => Str::uuid(),
            'finance_entry_id' => $entry->id,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'document_date' => $data['document_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
            'uploaded_by' => $request->user()->id,
        ]);
        $this->audit->record('finance.receipt_uploaded', $receipt, new: ['entry_id' => $entry->id, 'name' => $receipt->original_name, 'size' => $receipt->size]);

        return back()->with('success', 'Beleg wurde sicher zu '.$entry->entry_number.' abgelegt.');
    }

    public function downloadReceipt(Request $request, FinanceReceipt $receipt): StreamedResponse
    {
        $this->authorizePermission($request, 'finance.receipts');
        abort_unless(Storage::disk($receipt->disk)->exists($receipt->path), 404);
        $this->audit->record('finance.receipt_downloaded', $receipt);

        return Storage::disk($receipt->disk)->download($receipt->path, $receipt->original_name);
    }

    public function voidReceipt(Request $request, FinanceReceipt $receipt): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.receipts');
        abort_unless($receipt->status === 'active', 422, 'Der Beleg ist bereits ungültig markiert.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $receipt->update([
            'status' => 'voided',
            'voided_by' => $request->user()->id,
            'voided_at' => now(),
            'void_reason' => $data['reason'],
        ]);
        $this->audit->record('finance.receipt_voided', $receipt, new: ['reason' => $data['reason']]);

        return back()->with('success', 'Beleg wurde als ungültig markiert; die Datei bleibt für den Nachweis erhalten.');
    }

    public function closeCash(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.cash');
        $data = $request->validate([
            'finance_account_id' => ['required', 'integer'],
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'counted_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $account = FinanceAccount::query()->where('type', 'cash')->findOrFail($data['finance_account_id']);
        $closing = $this->controls->closeCash($account, $data['closing_date'], (float) $data['counted_balance'], $request->user()->id, $data['notes'] ?? null);
        $this->audit->record('finance.cash_closed', $closing, new: $closing->only(['finance_account_id', 'closing_date', 'system_balance', 'counted_balance', 'difference']));

        return back()->with('success', 'Kassenabschluss für '.$closing->closing_date->format('d.m.Y').' wurde gespeichert.');
    }

    public function lockPeriod(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.periods');
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $lock = $this->controls->lockPeriod($data['period_start'], $data['period_end'], $data['reason'], $request->user()->id);
        $this->audit->record('finance.period_locked', $lock, new: $lock->only(['period_start', 'period_end', 'reason']));

        return back()->with('success', 'Buchungsperiode wurde gesperrt.');
    }

    public function unlockPeriod(Request $request, FinancePeriodLock $lock): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.periods');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->controls->unlockPeriod($lock, $request->user()->id);
        $this->audit->record('finance.period_unlocked', $lock, new: ['reason' => $data['reason']]);

        return back()->with('success', 'Buchungsperiode wurde wieder geöffnet.');
    }

    public function storeAudit(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.audit');
        $data = $request->validate([
            'finance_account_id' => ['nullable', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'result' => ['required', 'in:passed,issues'],
            'counted_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'findings' => ['nullable', 'string', 'max:10000'],
        ]);
        $account = ! empty($data['finance_account_id']) ? FinanceAccount::query()->where('type', 'cash')->findOrFail($data['finance_account_id']) : null;
        $entryQuery = FinanceEntry::query()->whereBetween('booking_date', [$data['period_start'], $data['period_end']]);
        if ($account) {
            $entryQuery->where('finance_account_id', $account->id);
        }
        $expected = $account ? $this->controls->balance($account, $data['period_end']) : null;
        $counted = array_key_exists('counted_balance', $data) && $data['counted_balance'] !== null ? round((float) $data['counted_balance'], 2) : null;
        $audit = FinanceCashAudit::query()->create([
            'public_id' => Str::uuid(),
            'finance_account_id' => $account?->id,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'result' => $data['result'],
            'entry_count' => $entryQuery->count(),
            'expected_balance' => $expected,
            'counted_balance' => $counted,
            'difference' => $expected !== null && $counted !== null ? round($counted - $expected, 2) : null,
            'findings' => $data['findings'] ?? null,
            'audited_by' => $request->user()->id,
            'audited_at' => now(),
        ]);
        $this->audit->record('finance.cash_audited', $audit, new: $audit->only(['finance_account_id', 'period_start', 'period_end', 'result', 'entry_count', 'difference']));

        return back()->with('success', 'Kassenprüfung wurde dokumentiert.');
    }

    public function exportCash(Request $request): StreamedResponse
    {
        $this->authorizeAny($request, ['finance.cash', 'finance.reports']);
        $data = $request->validate([
            'finance_account_id' => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $account = FinanceAccount::query()->where('type', 'cash')->findOrFail($data['finance_account_id']);
        $entries = FinanceEntry::query()->where('finance_account_id', $account->id)
            ->whereBetween('booking_date', [$data['period_start'], $data['period_end']])
            ->with(['category', 'receipts'])->orderBy('booking_date')->orderBy('id')->get();
        $this->audit->record('finance.cashbook_exported', null, new: ['account_id' => $account->id, 'from' => $data['period_start'], 'to' => $data['period_end'], 'rows' => $entries->count()]);

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Buchungsnummer', 'Datum', 'Art', 'Kategorie', 'Beschreibung', 'Referenz', 'Brutto', 'Belege'], ';');
            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->entry_number,
                    $entry->booking_date->format('d.m.Y'),
                    $entry->direction === 'income' ? 'Einnahme' : 'Ausgabe',
                    $entry->category?->name,
                    $entry->description,
                    $entry->reference,
                    number_format((float) $entry->gross_amount, 2, ',', ''),
                    $entry->receipts->where('status', 'active')->count(),
                ], ';');
            }
            fclose($out);
        }, 'Kassenbuch-'.$account->code.'-'.$data['period_start'].'-'.$data['period_end'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->allows($request, $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function authorizeAny(Request $request, array $permissions): void
    {
        abort_unless($request->user()->is_super_admin || collect($permissions)->contains(fn (string $permission) => $this->permissions->allows($request->user(), $permission)), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function allows(Request $request, string $permission): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission);
    }
}
