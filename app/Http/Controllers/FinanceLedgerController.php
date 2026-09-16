<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\Member;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\FinanceLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceLedgerController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FinanceLedgerService $ledger,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAny($request, ['finance.accounting', 'finance.reports', 'finance.view']);
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = $request->filled('month') ? (int) $request->integer('month') : null;

        $query = $this->filteredQuery($request, $year, $month)
            ->with(['account', 'category', 'member.person', 'invoice', 'creator'])
            ->orderByDesc('booking_date')
            ->orderByDesc('id');

        $yearEntries = FinanceEntry::query()
            ->whereYear('booking_date', $year)
            ->whereIn('status', ['posted', 'reversed'])
            ->with(['account', 'category'])
            ->get();

        return view('finance.ledger', [
            'entries' => $query->paginate(40)->withQueryString(),
            'accounts' => FinanceAccount::query()->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => FinanceCategory::query()->orderBy('direction')->orderBy('sort_order')->orderBy('name')->get(),
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(500)->get(),
            'year' => $year,
            'month' => $month,
            'metrics' => $this->metrics($yearEntries),
            'monthly' => $this->monthly($yearEntries),
            'categoryTotals' => $this->categoryTotals($yearEntries),
            'accountBalances' => $this->accountBalances($year),
            'canManage' => $this->allows($request, 'finance.accounting'),
            'canReports' => $this->allows($request, 'finance.reports') || $this->allows($request, 'finance.accounting'),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.accounting');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('finance_accounts', 'code')->where('tenant_id', $this->tenant->id())],
            'type' => ['required', 'in:bank,cash,clearing,other'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
        ]);
        $account = FinanceAccount::query()->create([
            ...$data,
            'code' => strtoupper($data['code']),
            'currency' => strtoupper($data['currency']),
            'is_default' => false,
            'is_active' => true,
        ]);
        $this->audit->record('finance.account_created', $account, new: $account->only(['name', 'code', 'type', 'opening_balance']));

        return back()->with('success', 'Finanzkonto wurde angelegt.');
    }

    public function toggleAccount(Request $request, FinanceAccount $account): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.accounting');
        $account->update(['is_active' => ! $account->is_active]);
        $this->audit->record('finance.account_updated', $account, new: ['is_active' => $account->is_active]);

        return back()->with('success', 'Finanzkonto wurde aktualisiert.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.accounting');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('finance_categories', 'code')->where('tenant_id', $this->tenant->id())],
            'direction' => ['required', 'in:income,expense'],
            'default_tax_rate' => ['required', 'numeric', 'between:0,100'],
        ]);
        $category = FinanceCategory::query()->create([
            ...$data,
            'code' => strtoupper($data['code']),
            'is_active' => true,
        ]);
        $this->audit->record('finance.category_created', $category, new: $category->only(['name', 'code', 'direction', 'default_tax_rate']));

        return back()->with('success', 'Buchungskategorie wurde angelegt.');
    }

    public function toggleCategory(Request $request, FinanceCategory $category): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.accounting');
        $category->update(['is_active' => ! $category->is_active]);
        $this->audit->record('finance.category_updated', $category, new: ['is_active' => $category->is_active]);

        return back()->with('success', 'Buchungskategorie wurde aktualisiert.');
    }

    public function storeEntry(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.accounting');
        $data = $request->validate([
            'booking_date' => ['required', 'date'],
            'value_date' => ['nullable', 'date'],
            'direction' => ['required', 'in:income,expense'],
            'finance_account_id' => ['required', 'integer'],
            'finance_category_id' => ['required', 'integer'],
            'member_id' => ['nullable', 'integer'],
            'gross_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! empty($data['member_id'])) {
            Member::query()->findOrFail($data['member_id']);
        }
        $entry = $this->ledger->postManual($data, $request->user()->id);
        $this->audit->record('finance.entry_posted', $entry, new: $entry->only(['entry_number', 'booking_date', 'direction', 'gross_amount', 'finance_account_id', 'finance_category_id']));

        return back()->with('success', 'Buchung '.$entry->entry_number.' wurde erfasst.');
    }

    public function reverseEntry(Request $request, FinanceEntry $entry): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.accounting');
        abort_unless($entry->source_type === 'manual', 422, 'Automatisch erzeugte Zahlungsbuchungen müssen über den zugehörigen Finanzvorgang korrigiert werden.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $reversal = $this->ledger->reverse($entry, $request->user()->id, $data['reason']);
        $this->audit->record('finance.entry_reversed', $entry, new: ['reversal_entry_id' => $reversal->id, 'reason' => $data['reason']]);

        return back()->with('success', 'Buchung wurde durch '.$reversal->entry_number.' storniert.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAny($request, ['finance.reports', 'finance.accounting']);
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = $request->filled('month') ? (int) $request->integer('month') : null;
        $entries = $this->filteredQuery($request, $year, $month)
            ->with(['account', 'category', 'member.person', 'invoice'])
            ->orderBy('booking_date')->orderBy('id')->get();
        $this->audit->record('finance.journal_exported', null, new: ['year' => $year, 'month' => $month, 'rows' => $entries->count()]);

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Buchungsnummer', 'Buchungsdatum', 'Art', 'Konto', 'Kategorie', 'Mitglied', 'Rechnung', 'Beschreibung', 'Referenz', 'Netto', 'Steuer', 'Brutto', 'Status'], ';');
            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->entry_number,
                    $entry->booking_date->format('d.m.Y'),
                    $entry->direction === 'income' ? 'Einnahme' : 'Ausgabe',
                    $entry->account?->name,
                    $entry->category?->name,
                    $entry->member?->person?->display_name,
                    $entry->invoice?->invoice_number,
                    $entry->description,
                    $entry->reference,
                    number_format((float) $entry->net_amount, 2, ',', ''),
                    number_format((float) $entry->tax_amount, 2, ',', ''),
                    number_format((float) $entry->gross_amount, 2, ',', ''),
                    $entry->status,
                ], ';');
            }
            fclose($out);
        }, 'Finanzjournal-'.$year.($month ? '-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request, int $year, ?int $month): Builder
    {
        $query = FinanceEntry::query()->whereYear('booking_date', $year);
        if ($month) {
            $query->whereMonth('booking_date', $month);
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction'));
        }
        if ($request->filled('account')) {
            $query->where('finance_account_id', $request->integer('account'));
        }
        if ($request->filled('category')) {
            $query->where('finance_category_id', $request->integer('category'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('entry_number', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('invoice_number', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    private function metrics(Collection $entries): array
    {
        $income = round((float) $entries->where('direction', 'income')->sum('gross_amount'), 2);
        $expense = round((float) $entries->where('direction', 'expense')->sum('gross_amount'), 2);

        return [
            'income' => $income,
            'expense' => $expense,
            'result' => round($income - $expense, 2),
            'tax' => round((float) $entries->sum('tax_amount'), 2),
            'count' => $entries->count(),
        ];
    }

    private function monthly(Collection $entries): array
    {
        $result = [];
        foreach (range(1, 12) as $month) {
            $monthEntries = $entries->filter(fn (FinanceEntry $entry) => (int) $entry->booking_date->format('n') === $month);
            $income = round((float) $monthEntries->where('direction', 'income')->sum('gross_amount'), 2);
            $expense = round((float) $monthEntries->where('direction', 'expense')->sum('gross_amount'), 2);
            $result[$month] = ['income' => $income, 'expense' => $expense, 'result' => round($income - $expense, 2)];
        }

        return $result;
    }

    private function categoryTotals(Collection $entries): Collection
    {
        return $entries->groupBy('finance_category_id')->map(function (Collection $group): array {
            $category = $group->first()?->category;

            return [
                'name' => $category?->name ?: 'Ohne Kategorie',
                'direction' => $category?->direction ?: $group->first()?->direction,
                'amount' => round((float) $group->sum('gross_amount'), 2),
            ];
        })->sortByDesc('amount')->values();
    }

    private function accountBalances(int $year): Collection
    {
        $end = now()->year === $year ? now()->toDateString() : $year.'-12-31';

        return FinanceAccount::query()->orderBy('sort_order')->orderBy('name')->get()->map(function (FinanceAccount $account) use ($end): array {
            $entries = $account->entries()->whereDate('booking_date', '<=', $end)->get(['direction', 'gross_amount']);
            $income = (float) $entries->where('direction', 'income')->sum('gross_amount');
            $expense = (float) $entries->where('direction', 'expense')->sum('gross_amount');

            return [
                'account' => $account,
                'balance' => round((float) $account->opening_balance + $income - $expense, 2),
            ];
        });
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
