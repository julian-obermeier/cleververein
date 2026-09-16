<?php

namespace App\Http\Controllers;

use App\Models\ContributionOverride;
use App\Models\ContributionRate;
use App\Models\ContributionRule;
use App\Models\FinanceDunning;
use App\Models\FinanceInvoice;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\OrganizationUnit;
use App\Models\SepaMandate;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\FinanceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FinanceService $finance,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'finance.view');
        $this->refreshDueStatuses();

        $invoiceQuery = FinanceInvoice::query()->with('member.person')->orderByDesc('created_at');
        if ($request->filled('status')) {
            $invoiceQuery->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $invoiceQuery->where(function ($query) use ($q): void {
                $query->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('member.person', function ($person) use ($q): void {
                        $person->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%");
                    });
            });
        }

        $openInvoices = FinanceInvoice::query()->whereIn('status', ['open', 'overdue'])->get();

        return view('finance.index', [
            'invoices' => $invoiceQuery->paginate(30)->withQueryString(),
            'rates' => ContributionRate::query()->orderBy('name')->get(),
            'rules' => ContributionRule::query()->with(['rate', 'memberType', 'organizationUnit'])->orderBy('priority')->get(),
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(500)->get(),
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('name')->get(),
            'organizationUnits' => OrganizationUnit::query()->where('status', 'active')->orderBy('name')->get(),
            'sepaMandates' => SepaMandate::query()->with('member.person')->where('status', 'active')->latest()->limit(20)->get(),
            'metrics' => [
                'open_amount' => $openInvoices->sum(fn (FinanceInvoice $invoice) => $invoice->open_amount),
                'open_count' => $openInvoices->count(),
                'overdue_count' => $openInvoices->where('status', 'overdue')->count(),
                'paid_this_year' => (float) DB::table('finance_payments')->where('tenant_id', $this->tenant->id())->whereYear('paid_at', now()->year)->sum('amount'),
            ],
            'canManage' => $this->allows($request, 'finance.manage'),
            'canSepa' => $this->allows($request, 'finance.sepa'),
            'canDunning' => $this->allows($request, 'finance.dunning'),
        ]);
    }

    public function show(Request $request, FinanceInvoice $invoice): View
    {
        $this->authorizePermission($request, 'finance.view');
        $this->finance->recalculate($invoice);
        $invoice->load(['member.person', 'items.contributionRate', 'payments.recorder', 'dunnings.creator', 'creator', 'issuer']);

        return view('finance.show', [
            'invoice' => $invoice,
            'canManage' => $this->allows($request, 'finance.manage'),
            'canDunning' => $this->allows($request, 'finance.dunning'),
        ]);
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('contribution_rates', 'code')->where('tenant_id', $this->tenant->id())],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'interval' => ['required', 'in:monthly,quarterly,half_yearly,yearly,once'],
            'billing_month' => ['nullable', 'integer', 'between:1,12'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $rate = ContributionRate::query()->create([...$data, 'is_active' => true]);
        $this->audit->record('finance.contribution_rate_created', $rate, new: $rate->only(['name', 'code', 'amount', 'interval']));

        return back()->with('success', 'Beitragssatz wurde angelegt.');
    }

    public function toggleRate(Request $request, ContributionRate $rate): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $rate->update(['is_active' => ! $rate->is_active]);
        $this->audit->record('finance.contribution_rate_updated', $rate, new: ['is_active' => $rate->is_active]);

        return back()->with('success', 'Beitragssatz wurde aktualisiert.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'contribution_rate_id' => ['required', 'integer'],
            'member_type_id' => ['nullable', 'integer'],
            'organization_unit_id' => ['nullable', 'integer'],
            'min_age' => ['nullable', 'integer', 'between:0,120'],
            'max_age' => ['nullable', 'integer', 'between:0,120', 'gte:min_age'],
            'priority' => ['required', 'integer', 'between:1,9999'],
        ]);
        $rate = ContributionRate::query()->where('is_active', true)->findOrFail($data['contribution_rate_id']);
        if (! empty($data['member_type_id'])) {
            MemberType::query()->findOrFail($data['member_type_id']);
        }
        if (! empty($data['organization_unit_id'])) {
            OrganizationUnit::query()->findOrFail($data['organization_unit_id']);
        }

        $rule = ContributionRule::query()->create([...$data, 'contribution_rate_id' => $rate->id, 'is_active' => true]);
        $this->audit->record('finance.contribution_rule_created', $rule, new: $rule->only(['contribution_rate_id', 'member_type_id', 'organization_unit_id', 'min_age', 'max_age', 'priority']));

        return back()->with('success', 'Beitragsregel wurde angelegt.');
    }

    public function toggleRule(Request $request, ContributionRule $rule): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $rule->update(['is_active' => ! $rule->is_active]);
        $this->audit->record('finance.contribution_rule_updated', $rule, new: ['is_active' => $rule->is_active]);

        return back()->with('success', 'Beitragsregel wurde aktualisiert.');
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'contribution_rate_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'is_exempt' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        Member::query()->findOrFail($data['member_id']);
        if (! empty($data['contribution_rate_id'])) {
            ContributionRate::query()->findOrFail($data['contribution_rate_id']);
        }

        $override = ContributionOverride::query()->create([
            ...$data,
            'is_exempt' => (bool) ($data['is_exempt'] ?? false),
        ]);
        $this->audit->record('finance.contribution_override_created', $override, new: $override->only(['member_id', 'contribution_rate_id', 'amount', 'is_exempt', 'valid_from', 'valid_until', 'reason']));

        return back()->with('success', 'Individuelle Beitragsregel wurde gespeichert.');
    }

    public function runContributions(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);
        $processed = 0;
        $withDraft = 0;

        Member::query()->where('status', 'active')->with(['person', 'memberships'])->chunkById(100, function ($members) use ($data, $request, &$processed, &$withDraft): void {
            foreach ($members as $member) {
                $processed++;
                if ($this->finance->createContributionDraft($member, (int) $data['year'], $request->user()->id)) {
                    $withDraft++;
                }
            }
        });

        $this->audit->record('finance.contribution_run', null, new: ['year' => (int) $data['year'], 'processed' => $processed, 'matched' => $withDraft]);

        return back()->with('success', "Beitragslauf abgeschlossen: {$processed} aktive Mitglieder geprüft, {$withDraft} mit passendem Beitrag bzw. vorhandenem Entwurf.");
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! empty($data['member_id'])) {
            Member::query()->findOrFail($data['member_id']);
        }

        $invoice = DB::transaction(function () use ($data, $request): FinanceInvoice {
            $quantity = round((float) $data['quantity'], 2);
            $unitPrice = round((float) $data['unit_price'], 2);
            $net = round($quantity * $unitPrice, 2);
            $tax = round($net * ((float) $data['tax_rate'] / 100), 2);
            $gross = round($net + $tax, 2);

            $invoice = FinanceInvoice::query()->create([
                'public_id' => Str::uuid(),
                'member_id' => $data['member_id'] ?? null,
                'status' => 'draft',
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'net_amount' => $net,
                'tax_amount' => $tax,
                'gross_amount' => $gross,
                'paid_amount' => 0,
                'currency' => 'EUR',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $invoice->items()->create([
                'description' => $data['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $data['tax_rate'],
                'net_amount' => $net,
                'tax_amount' => $tax,
                'gross_amount' => $gross,
                'sort_order' => 10,
            ]);

            return $invoice;
        });
        $this->audit->record('finance.invoice_created', $invoice, new: ['gross_amount' => $invoice->gross_amount, 'member_id' => $invoice->member_id]);

        return redirect()->route('finance.invoices.show', $invoice)->with('success', 'Rechnungsentwurf wurde angelegt.');
    }

    public function issueInvoice(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $invoice = $this->finance->issue($invoice, $request->user()->id);
        $this->audit->record('finance.invoice_issued', $invoice, new: ['invoice_number' => $invoice->invoice_number, 'gross_amount' => $invoice->gross_amount]);

        return back()->with('success', 'Rechnung wurde verbindlich ausgestellt: '.$invoice->invoice_number);
    }

    public function storePayment(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.manage');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:bank_transfer,sepa,cash,card,other'],
            'reference' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $payment = $this->finance->recordPayment($invoice, $data, $request->user()->id);
        $this->audit->record('finance.payment_recorded', $payment, new: ['invoice_id' => $invoice->id, 'amount' => $payment->amount, 'paid_at' => $payment->paid_at]);

        return back()->with('success', 'Zahlung wurde verbucht.');
    }

    public function storeSepa(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.sepa');
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'mandate_reference' => ['required', 'string', 'max:80', Rule::unique('sepa_mandates', 'mandate_reference')->where('tenant_id', $this->tenant->id())],
            'account_holder' => ['required', 'string', 'max:180'],
            'iban' => ['required', 'string', 'min:15', 'max:34', 'regex:/^[A-Za-z]{2}[0-9A-Za-z ]+$/'],
            'bic' => ['nullable', 'string', 'max:11'],
            'signed_at' => ['required', 'date'],
        ]);
        Member::query()->findOrFail($data['member_id']);
        $mandate = SepaMandate::query()->create([
            ...$data,
            'public_id' => Str::uuid(),
            'iban' => strtoupper(str_replace(' ', '', $data['iban'])),
            'bic' => isset($data['bic']) ? strtoupper(str_replace(' ', '', $data['bic'])) : null,
            'status' => 'active',
        ]);
        $this->audit->record('finance.sepa_mandate_created', $mandate, new: ['member_id' => $mandate->member_id, 'mandate_reference' => $mandate->mandate_reference]);

        return back()->with('success', 'SEPA-Mandat wurde gespeichert.');
    }

    public function revokeSepa(Request $request, SepaMandate $mandate): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.sepa');
        $mandate->update(['status' => 'revoked', 'revoked_at' => now()->toDateString()]);
        $this->audit->record('finance.sepa_mandate_revoked', $mandate, new: ['mandate_reference' => $mandate->mandate_reference]);

        return back()->with('success', 'SEPA-Mandat wurde widerrufen.');
    }

    public function storeDunning(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.dunning');
        abort_if(in_array($invoice->status, ['draft', 'paid', 'cancelled'], true), 422, 'Für diese Rechnung kann keine Mahnung erstellt werden.');
        $data = $request->validate([
            'level' => ['required', 'integer', 'between:1,3'],
            'dunned_at' => ['required', 'date'],
            'fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $dunning = FinanceDunning::query()->create([
            ...$data,
            'finance_invoice_id' => $invoice->id,
            'status' => 'sent',
            'created_by' => $request->user()->id,
        ]);
        $this->audit->record('finance.dunning_created', $dunning, new: ['invoice_id' => $invoice->id, 'level' => $dunning->level, 'fee' => $dunning->fee]);

        return back()->with('success', 'Mahnung wurde dokumentiert.');
    }

    private function refreshDueStatuses(): void
    {
        FinanceInvoice::query()
            ->where('status', 'open')
            ->whereDate('due_date', '<', today())
            ->whereColumn('paid_amount', '<', 'gross_amount')
            ->update(['status' => 'overdue', 'updated_at' => now()]);
    }

    private function allows(Request $request, string $permission): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->allows($request, $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
