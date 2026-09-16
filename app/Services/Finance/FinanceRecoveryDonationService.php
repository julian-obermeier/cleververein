<?php

namespace App\Services\Finance;

use App\Models\FinanceDonation;
use App\Models\FinanceDonationCertificate;
use App\Models\FinanceDonationCollectiveCertificate;
use App\Models\FinanceDonationCollectiveItem;
use App\Models\FinancePayment;
use App\Models\FinancePaymentAdjustment;
use App\Models\FinanceSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceRecoveryDonationService
{
    public function __construct(
        private TenantContext $tenant,
        private FinanceLedgerService $ledger,
        private FinanceService $finance,
    ) {}

    public function adjustPayment(FinancePayment $payment, array $data, int $userId): FinancePaymentAdjustment
    {
        $payment->loadMissing(['invoice.creditNotes', 'adjustments']);
        $invoice = $payment->invoice;
        if ($invoice === null) {
            throw ValidationException::withMessages(['payment' => 'Der Zahlungseingang ist keiner Rechnung mehr zugeordnet.']);
        }

        $type = (string) $data['type'];
        if (in_array($type, ['chargeback', 'refund'], true) === false) {
            throw ValidationException::withMessages(['type' => 'Unbekannte Zahlungskorrektur.']);
        }

        $alreadyAdjusted = (float) $payment->adjustments->where('status', 'posted')->sum('amount');
        $remaining = max(0, round((float) $payment->amount - $alreadyAdjusted, 2));
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0 || $amount > $remaining + 0.001) {
            throw ValidationException::withMessages(['amount' => 'Der Betrag darf den noch wirksamen Zahlungseingang nicht übersteigen.']);
        }

        if ($type === 'refund') {
            $effectivePaid = max(0, (float) $invoice->payments()->sum('amount') - (float) $invoice->paymentAdjustments()->where('status', 'posted')->sum('amount'));
            $credit = (float) $invoice->creditNotes()->where('status', 'issued')->sum('amount');
            $refundable = max(0, round($effectivePaid + $credit - (float) $invoice->gross_amount, 2));
            if ($amount > $refundable + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Die Erstattung übersteigt das vorhandene Guthaben der Rechnung. Erstellen Sie bei Bedarf zuerst eine Gutschrift.']);
            }
        }

        return DB::transaction(function () use ($payment, $invoice, $data, $userId, $type, $amount): FinancePaymentAdjustment {
            $adjustment = FinancePaymentAdjustment::query()->create([
                'public_id' => Str::uuid(),
                'finance_payment_id' => $payment->id,
                'finance_invoice_id' => $invoice->id,
                'member_id' => $invoice->member_id,
                'type' => $type,
                'amount' => $amount,
                'fee_amount' => $type === 'chargeback' ? round((float) ($data['fee_amount'] ?? 0), 2) : 0,
                'adjustment_date' => $data['adjustment_date'],
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
                'bank_transaction_id' => $data['bank_transaction_id'] ?? null,
                'status' => 'posted',
                'created_by' => $userId,
            ]);

            [$reversal, $fee] = $this->ledger->postPaymentAdjustment($adjustment, $payment, $userId);
            $adjustment->update([
                'reversal_entry_id' => $reversal->id,
                'fee_entry_id' => $fee?->id,
            ]);
            $this->finance->recalculate($invoice);

            return $adjustment->fresh(['payment', 'invoice', 'reversalEntry', 'feeEntry']);
        });
    }

    public function createDonation(array $data, int $userId): FinanceDonation
    {
        $year = (int) substr((string) $data['donation_date'], 0, 4);

        return DB::transaction(function () use ($data, $userId, $year): FinanceDonation {
            $number = $this->nextSequence('donation', $year);
            $kind = (string) ($data['donation_kind'] ?? 'money');
            $expenseWaiver = $kind === 'expense_waiver';

            $donation = FinanceDonation::query()->create([
                'public_id' => Str::uuid(),
                'donation_number' => sprintf('SP-%d-%06d', $year, $number),
                'member_id' => $data['member_id'] ?? null,
                'donor_name' => $data['donor_name'],
                'donor_street' => $data['donor_street'] ?? null,
                'donor_postal_code' => $data['donor_postal_code'] ?? null,
                'donor_city' => $data['donor_city'] ?? null,
                'donor_country' => strtoupper($data['donor_country'] ?? 'DE'),
                'donor_email' => $data['donor_email'] ?? null,
                'donation_kind' => $kind,
                'amount' => round((float) $data['amount'], 2),
                'donation_date' => $data['donation_date'],
                'purpose' => $data['purpose'],
                'expense_waiver' => $expenseWaiver,
                'finance_account_id' => $expenseWaiver ? null : $data['finance_account_id'],
                'bank_transaction_id' => $data['bank_transaction_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'received',
                'received_by' => $userId,
            ]);

            $entry = $this->ledger->postDonation($donation, $userId);
            if ($entry) {
                $donation->update(['finance_entry_id' => $entry->id]);
            }

            return $donation->fresh(['member.person', 'entry', 'certificates']);
        });
    }

    public function issueCertificate(FinanceDonation $donation, int $userId): FinanceDonationCertificate
    {
        $donation->loadMissing('certificates');
        $this->assertDonationEligible($donation);
        if ($this->hasActiveCollectiveCertificate($donation->id)) {
            throw ValidationException::withMessages(['donation' => 'Diese Zuwendung ist bereits Bestandteil einer gültigen Sammelbestätigung.']);
        }
        $settings = $this->validatedDonationSettings([$donation]);

        return DB::transaction(function () use ($donation, $settings, $userId): FinanceDonationCertificate {
            $year = now()->year;
            $number = $this->nextSequence('donation_certificate', $year);

            return FinanceDonationCertificate::query()->create([
                'public_id' => Str::uuid(),
                'finance_donation_id' => $donation->id,
                'certificate_number' => sprintf('ZB-%d-%06d', $year, $number),
                'issue_date' => now()->toDateString(),
                'status' => 'issued',
                'amount' => $donation->amount,
                'donation_date' => $donation->donation_date,
                'donation_kind' => $donation->donation_kind,
                'purpose' => $donation->purpose,
                'expense_waiver' => $donation->expense_waiver,
                'donor_snapshot' => $this->donorSnapshot($donation),
                'recipient_snapshot' => $this->recipientSnapshot($settings),
                'tax_snapshot' => $this->taxSnapshot($settings),
                'issued_by' => $userId,
                'issued_at' => now(),
            ]);
        });
    }

    public function issueCollectiveCertificate(array $donationIds, int $userId): FinanceDonationCollectiveCertificate
    {
        $ids = collect($donationIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->count() < 2) {
            throw ValidationException::withMessages(['donations' => 'Für eine Sammelbestätigung müssen mindestens zwei Zuwendungen ausgewählt werden.']);
        }

        $donations = FinanceDonation::query()
            ->with(['certificates', 'collectiveItems.certificate'])
            ->whereIn('id', $ids)
            ->orderBy('donation_date')
            ->orderBy('id')
            ->get();
        if ($donations->count() !== $ids->count()) {
            throw ValidationException::withMessages(['donations' => 'Mindestens eine ausgewählte Zuwendung gehört nicht zum aktuellen Mandanten.']);
        }
        foreach ($donations as $donation) {
            $this->assertDonationEligible($donation);
            if ($this->hasActiveCollectiveCertificate($donation->id)) {
                throw ValidationException::withMessages(['donations' => 'Mindestens eine ausgewählte Zuwendung ist bereits Bestandteil einer gültigen Sammelbestätigung.']);
            }
        }

        $first = $donations->firstOrFail();
        $donorKey = $this->donorIdentityKey($first);
        if ($donations->contains(fn (FinanceDonation $donation) => $this->donorIdentityKey($donation) !== $donorKey)) {
            throw ValidationException::withMessages(['donations' => 'Eine Sammelbestätigung darf nur Zuwendungen derselben Person mit derselben Anschrift enthalten.']);
        }
        $settings = $this->validatedDonationSettings($donations);

        return DB::transaction(function () use ($donations, $first, $settings, $userId): FinanceDonationCollectiveCertificate {
            $year = now()->year;
            $number = $this->nextSequence('donation_certificate', $year);
            $certificate = FinanceDonationCollectiveCertificate::query()->create([
                'public_id' => Str::uuid(),
                'certificate_number' => sprintf('ZB-%d-%06d', $year, $number),
                'issue_date' => now()->toDateString(),
                'period_from' => $donations->min('donation_date'),
                'period_to' => $donations->max('donation_date'),
                'status' => 'issued',
                'total_amount' => round((float) $donations->sum('amount'), 2),
                'donor_snapshot' => $this->donorSnapshot($first),
                'recipient_snapshot' => $this->recipientSnapshot($settings),
                'tax_snapshot' => $this->taxSnapshot($settings),
                'issued_by' => $userId,
                'issued_at' => now(),
            ]);

            foreach ($donations as $donation) {
                $certificate->items()->create([
                    'finance_donation_id' => $donation->id,
                    'donation_date' => $donation->donation_date,
                    'amount' => $donation->amount,
                    'donation_kind' => $donation->donation_kind,
                    'purpose' => $donation->purpose,
                    'expense_waiver' => $donation->expense_waiver,
                ]);
            }

            return $certificate->fresh(['items.donation']);
        });
    }

    public function voidCertificate(FinanceDonationCertificate $certificate, int $userId, string $reason): FinanceDonationCertificate
    {
        if ($certificate->status !== 'issued') {
            throw ValidationException::withMessages(['certificate' => 'Nur eine gültige Zuwendungsbestätigung kann storniert werden.']);
        }

        $certificate->update([
            'status' => 'voided',
            'voided_by' => $userId,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        return $certificate->fresh();
    }

    public function voidCollectiveCertificate(FinanceDonationCollectiveCertificate $certificate, int $userId, string $reason): FinanceDonationCollectiveCertificate
    {
        if ($certificate->status !== 'issued') {
            throw ValidationException::withMessages(['certificate' => 'Nur eine gültige Sammelbestätigung kann storniert werden.']);
        }

        $certificate->update([
            'status' => 'voided',
            'voided_by' => $userId,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        return $certificate->fresh('items');
    }

    private function assertDonationEligible(FinanceDonation $donation): void
    {
        if ($donation->status !== 'received') {
            throw ValidationException::withMessages(['donation' => 'Nur vereinnahmte Zuwendungen können bescheinigt werden.']);
        }
        if ($donation->certificates->contains('status', 'issued')) {
            throw ValidationException::withMessages(['donation' => 'Für diese Zuwendung existiert bereits eine gültige Zuwendungsbestätigung.']);
        }
        if (filled($donation->donor_street) === false || filled($donation->donor_postal_code) === false || filled($donation->donor_city) === false) {
            throw ValidationException::withMessages(['donor' => 'Für eine Zuwendungsbestätigung wird die vollständige Anschrift des Zuwendenden benötigt.']);
        }
    }

    private function validatedDonationSettings(iterable $donations): FinanceSetting
    {
        $settings = FinanceSetting::query()->first();
        if (($settings?->donation_receipt_ready ?? false) === false) {
            throw ValidationException::withMessages(['settings' => 'Die steuerlichen Stammdaten für Zuwendungsbestätigungen sind noch nicht vollständig und ausdrücklich aktiviert.']);
        }
        $maxAgeYears = $settings->tax_notice_type === '60a' ? 3 : 5;
        if ($settings->tax_notice_date->lt(now()->subYears($maxAgeYears)->startOfDay())) {
            throw ValidationException::withMessages([
                'settings' => 'Der hinterlegte steuerliche Bescheid ist für die Ausstellung von Zuwendungsbestätigungen zu alt. Bitte hinterlegen Sie einen aktuellen Bescheid.',
            ]);
        }
        foreach ($donations as $donation) {
            if ($donation->donation_kind === 'membership_contribution' && $settings->membership_contributions_deductible === false) {
                throw ValidationException::withMessages(['donation' => 'Mitgliedsbeiträge sind in den Finanzstammdaten nicht als steuerlich abzugsfähig freigegeben.']);
            }
        }

        return $settings;
    }

    private function hasActiveCollectiveCertificate(int $donationId): bool
    {
        return FinanceDonationCollectiveItem::query()
            ->where('finance_donation_id', $donationId)
            ->whereHas('certificate', fn ($query) => $query->where('status', 'issued'))
            ->exists();
    }

    private function donorIdentityKey(FinanceDonation $donation): string
    {
        return mb_strtolower(implode('|', array_map(
            fn ($value) => trim((string) $value),
            [$donation->donor_name, $donation->donor_street, $donation->donor_postal_code, $donation->donor_city, $donation->donor_country],
        )));
    }

    private function donorSnapshot(FinanceDonation $donation): array
    {
        return [
            'name' => $donation->donor_name,
            'street' => $donation->donor_street,
            'postal_code' => $donation->donor_postal_code,
            'city' => $donation->donor_city,
            'country' => $donation->donor_country,
        ];
    }

    private function recipientSnapshot(FinanceSetting $settings): array
    {
        $tenant = $this->tenant->tenant();

        return [
            'name' => $settings->creditor_name ?: $tenant->name,
            'street' => $settings->street,
            'postal_code' => $settings->postal_code,
            'city' => $settings->city,
            'country' => $settings->country,
        ];
    }

    private function taxSnapshot(FinanceSetting $settings): array
    {
        return [
            'tax_number' => $settings->tax_number,
            'notice_type' => $settings->tax_notice_type,
            'tax_office' => $settings->tax_office,
            'notice_date' => $settings->tax_notice_date?->toDateString(),
            'notice_reference' => $settings->tax_notice_reference,
            'notice_years' => $settings->tax_notice_years,
            'purposes' => $settings->tax_exempt_purposes,
            'membership_contributions_deductible' => (bool) $settings->membership_contributions_deductible,
        ];
    }

    private function nextSequence(string $key, int $year): int
    {
        DB::table('finance_sequences')->insertOrIgnore([
            'tenant_id' => $this->tenant->id(),
            'sequence_key' => $key,
            'year' => $year,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('finance_sequences')
            ->where('tenant_id', $this->tenant->id())
            ->where('sequence_key', $key)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $number = (int) $sequence->next_value;
        DB::table('finance_sequences')->where('id', $sequence->id)->update([
            'next_value' => $number + 1,
            'updated_at' => now(),
        ]);

        return $number;
    }
}
