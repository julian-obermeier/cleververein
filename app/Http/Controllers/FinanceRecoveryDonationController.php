<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\FinanceAccount;
use App\Models\FinanceDonation;
use App\Models\FinanceDonationCertificate;
use App\Models\FinancePayment;
use App\Models\FinancePaymentAdjustment;
use App\Models\FinanceSetting;
use App\Models\Member;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\FinanceDocumentService;
use App\Services\Finance\FinanceRecoveryDonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceRecoveryDonationController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FinanceRecoveryDonationService $recovery,
        private FinanceDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAny($request, ['finance.adjustments', 'finance.donations', 'finance.donation_certificates', 'finance.view']);
        $year = (int) ($request->integer('year') ?: now()->year);

        $donationQuery = FinanceDonation::query()
            ->with(['member.person', 'account', 'entry', 'certificates'])
            ->whereYear('donation_date', $year)
            ->orderByDesc('donation_date')
            ->orderByDesc('id');
        if ($request->filled('donation_q')) {
            $term = trim((string) $request->input('donation_q'));
            $donationQuery->where(function ($query) use ($term): void {
                $query->where('donation_number', 'like', "%{$term}%")
                    ->orWhere('donor_name', 'like', "%{$term}%")
                    ->orWhere('purpose', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%");
            });
        }

        $yearDonations = FinanceDonation::query()->whereYear('donation_date', $year)->get(['amount', 'status', 'expense_waiver']);

        return view('finance.recovery-donations', [
            'year' => $year,
            'settings' => FinanceSetting::query()->first(),
            'payments' => FinancePayment::query()
                ->with(['invoice.member.person', 'adjustments.reversalEntry', 'adjustments.feeEntry'])
                ->whereHas('invoice')
                ->orderByDesc('paid_at')->orderByDesc('id')->limit(120)->get(),
            'adjustments' => FinancePaymentAdjustment::query()
                ->with(['payment', 'invoice.member.person', 'reversalEntry', 'feeEntry', 'creator'])
                ->latest('adjustment_date')->latest('id')->limit(60)->get(),
            'donations' => $donationQuery->paginate(30)->withQueryString(),
            'accounts' => FinanceAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'members' => Member::query()->with('person')->where('status', 'active')->orderBy('member_number')->limit(500)->get(),
            'metrics' => [
                'donations' => $yearDonations->where('status', 'received')->count(),
                'amount' => round((float) $yearDonations->where('status', 'received')->sum('amount'), 2),
                'expense_waivers' => $yearDonations->where('status', 'received')->where('expense_waiver', true)->count(),
                'certificates' => FinanceDonationCertificate::query()->whereYear('issue_date', $year)->where('status', 'issued')->count(),
                'adjustments' => FinancePaymentAdjustment::query()->whereYear('adjustment_date', $year)->where('status', 'posted')->count(),
            ],
            'canAdjust' => $this->allows($request, 'finance.adjustments'),
            'canDonate' => $this->allows($request, 'finance.donations'),
            'canCertificates' => $this->allows($request, 'finance.donation_certificates'),
        ]);
    }

    public function storeAdjustment(Request $request, FinancePayment $payment): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.adjustments');
        $data = $request->validate([
            'type' => ['required', 'in:chargeback,refund'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'adjustment_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:180'],
            'bank_transaction_id' => ['nullable', 'integer'],
        ]);
        if (! empty($data['bank_transaction_id'])) {
            BankTransaction::query()->findOrFail($data['bank_transaction_id']);
        }
        $adjustment = $this->recovery->adjustPayment($payment, $data, $request->user()->id);
        $this->audit->record('finance.payment_adjusted', $adjustment, new: $adjustment->only([
            'finance_payment_id', 'finance_invoice_id', 'type', 'amount', 'fee_amount', 'adjustment_date', 'reason',
        ]));

        return back()->with('success', ($adjustment->type === 'chargeback' ? 'Rücklastschrift' : 'Erstattung').' wurde verbindlich gebucht.');
    }

    public function storeDonation(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.donations');
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'donor_name' => ['required', 'string', 'max:180'],
            'donor_street' => ['nullable', 'string', 'max:180'],
            'donor_postal_code' => ['nullable', 'string', 'max:20'],
            'donor_city' => ['nullable', 'string', 'max:120'],
            'donor_country' => ['required', 'string', 'size:2'],
            'donor_email' => ['nullable', 'email', 'max:180'],
            'donation_kind' => ['required', 'in:money,membership_contribution,expense_waiver'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'donation_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:500'],
            'finance_account_id' => ['nullable', 'required_unless:donation_kind,expense_waiver', 'integer'],
            'bank_transaction_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! empty($data['member_id'])) {
            Member::query()->findOrFail($data['member_id']);
        }
        if (! empty($data['finance_account_id'])) {
            FinanceAccount::query()->where('is_active', true)->findOrFail($data['finance_account_id']);
        }
        if (! empty($data['bank_transaction_id'])) {
            BankTransaction::query()->findOrFail($data['bank_transaction_id']);
        }
        $donation = $this->recovery->createDonation($data, $request->user()->id);
        $this->audit->record('finance.donation_created', $donation, new: $donation->only([
            'donation_number', 'member_id', 'donor_name', 'donation_kind', 'amount', 'donation_date', 'purpose',
        ]));

        return back()->with('success', 'Zuwendung '.$donation->donation_number.' wurde erfasst.');
    }

    public function issueCertificate(Request $request, FinanceDonation $donation): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.donation_certificates');
        $certificate = $this->recovery->issueCertificate($donation, $request->user()->id);
        $certificate = $this->documents->donationCertificate($certificate);
        $this->audit->record('finance.donation_certificate_issued', $certificate, new: [
            'certificate_number' => $certificate->certificate_number,
            'finance_donation_id' => $donation->id,
            'amount' => $certificate->amount,
        ]);

        return back()->with('success', 'Zuwendungsbestätigung '.$certificate->certificate_number.' wurde ausgestellt.');
    }

    public function certificatePdf(Request $request, FinanceDonationCertificate $certificate): StreamedResponse
    {
        $this->authorizePermission($request, 'finance.donation_certificates');
        if ($certificate->status === 'voided' || ! $certificate->pdf_path || ! Storage::disk($certificate->pdf_disk ?: 'local')->exists($certificate->pdf_path)) {
            $certificate = $this->documents->donationCertificate($certificate);
        }
        $this->audit->record('finance.donation_certificate_downloaded', $certificate);

        return Storage::disk($certificate->pdf_disk ?: 'local')->download(
            $certificate->pdf_path,
            $certificate->certificate_number.($certificate->status === 'voided' ? '-STORNIERT' : '').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function voidCertificate(Request $request, FinanceDonationCertificate $certificate): RedirectResponse
    {
        $this->authorizePermission($request, 'finance.donation_certificates');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $certificate = $this->recovery->voidCertificate($certificate, $request->user()->id, $data['reason']);
        $this->documents->donationCertificate($certificate);
        $this->audit->record('finance.donation_certificate_voided', $certificate, new: ['reason' => $data['reason']]);

        return back()->with('success', 'Zuwendungsbestätigung wurde nachvollziehbar storniert.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($this->allows($request, $permission), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }

    private function authorizeAny(Request $request, array $permissions): void
    {
        abort_unless($request->user()->is_super_admin || collect($permissions)->contains(fn (string $permission) => $this->permissions->allows($request->user(), $permission)), 403, 'Für diesen Bereich fehlt die Berechtigung.');
    }

    private function allows(Request $request, string $permission): bool
    {
        return $request->user()->is_super_admin || $this->permissions->allows($request->user(), $permission);
    }
}
