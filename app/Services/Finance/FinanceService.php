<?php

namespace App\Services\Finance;

use App\Models\ContributionOverride;
use App\Models\ContributionRate;
use App\Models\ContributionRule;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\Member;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function __construct(private TenantContext $tenant) {}

    public function resolveContribution(Member $member, ?CarbonImmutable $date = null): ?array
    {
        $date ??= CarbonImmutable::today();
        $member->loadMissing(['person', 'memberships.memberType', 'memberships.organizationUnit']);

        $override = ContributionOverride::query()
            ->with('rate')
            ->where('member_id', $member->id)
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $date->toDateString());
            })
            ->latest('id')
            ->first();

        if ($override?->is_exempt) {
            return ['exempt' => true, 'reason' => $override->reason];
        }

        if ($override) {
            $rate = $override->rate;
            if ($rate && $rate->is_active) {
                return [
                    'exempt' => false,
                    'rate' => $rate,
                    'amount' => $override->amount !== null ? (float) $override->amount : (float) $rate->amount,
                    'reason' => $override->reason,
                    'source' => 'override',
                ];
            }
        }

        $membership = $member->memberships
            ->where('status', 'active')
            ->sortByDesc('is_primary')
            ->first() ?? $member->memberships->sortByDesc('is_primary')->first();
        $age = $member->person?->birth_date?->age;

        $rules = ContributionRule::query()
            ->with('rate')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $rule = $rules->first(function (ContributionRule $rule) use ($membership, $age): bool {
            if (! $rule->rate?->is_active) {
                return false;
            }
            if ($rule->member_type_id && $rule->member_type_id !== $membership?->member_type_id) {
                return false;
            }
            if ($rule->organization_unit_id && $rule->organization_unit_id !== $membership?->organization_unit_id) {
                return false;
            }
            if ($rule->min_age !== null && ($age === null || $age < $rule->min_age)) {
                return false;
            }
            if ($rule->max_age !== null && ($age === null || $age > $rule->max_age)) {
                return false;
            }

            return true;
        });

        if (! $rule) {
            return null;
        }

        return [
            'exempt' => false,
            'rate' => $rule->rate,
            'amount' => (float) $rule->rate->amount,
            'reason' => null,
            'source' => 'rule',
        ];
    }

    public function createContributionDraft(Member $member, int $year, int $userId): ?FinanceInvoice
    {
        $resolved = $this->resolveContribution($member, CarbonImmutable::create($year, 1, 1));
        if (! $resolved || $resolved['exempt']) {
            return null;
        }

        /** @var ContributionRate $rate */
        $rate = $resolved['rate'];
        $existing = FinanceInvoice::query()
            ->where('member_id', $member->id)
            ->whereYear('invoice_date', $year)
            ->whereHas('items', fn ($query) => $query->where('contribution_rate_id', $rate->id))
            ->whereNotIn('status', ['cancelled'])
            ->first();
        if ($existing) {
            return $existing;
        }

        $multiplier = match ($rate->interval) {
            'monthly' => 12,
            'quarterly' => 4,
            'half_yearly' => 2,
            default => 1,
        };
        $unitAmount = (float) $resolved['amount'];
        $gross = round($unitAmount * $multiplier, 2);
        $description = $rate->name.' · Beitragsjahr '.$year;
        if ($multiplier > 1) {
            $description .= ' · '.$multiplier.' × '.number_format($unitAmount, 2, ',', '.').' €';
        }

        return DB::transaction(function () use ($member, $year, $userId, $rate, $multiplier, $unitAmount, $gross, $description): FinanceInvoice {
            $invoice = FinanceInvoice::query()->create([
                'public_id' => Str::uuid(),
                'member_id' => $member->id,
                'status' => 'draft',
                'invoice_date' => $year.'-01-01',
                'due_date' => $year.'-01-31',
                'net_amount' => $gross,
                'tax_amount' => 0,
                'gross_amount' => $gross,
                'paid_amount' => 0,
                'currency' => 'EUR',
                'created_by' => $userId,
            ]);
            $invoice->items()->create([
                'contribution_rate_id' => $rate->id,
                'description' => $description,
                'quantity' => $multiplier,
                'unit_price' => $unitAmount,
                'tax_rate' => 0,
                'net_amount' => $gross,
                'tax_amount' => 0,
                'gross_amount' => $gross,
                'sort_order' => 10,
            ]);

            return $invoice->fresh(['items', 'member.person']);
        });
    }

    public function issue(FinanceInvoice $invoice, int $userId): FinanceInvoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['invoice' => 'Nur Rechnungsentwürfe können ausgestellt werden.']);
        }
        if ($invoice->items()->count() === 0 || (float) $invoice->gross_amount <= 0) {
            throw ValidationException::withMessages(['invoice' => 'Die Rechnung benötigt mindestens eine Position mit einem Betrag größer 0.']);
        }

        return DB::transaction(function () use ($invoice, $userId): FinanceInvoice {
            $year = (int) ($invoice->invoice_date?->format('Y') ?: now()->year);
            $sequence = DB::table('finance_sequences')
                ->where('tenant_id', $this->tenant->id())
                ->where('sequence_key', 'invoice')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('finance_sequences')->insert([
                    'tenant_id' => $this->tenant->id(),
                    'sequence_key' => 'invoice',
                    'year' => $year,
                    'next_value' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $number = 1;
            } else {
                $number = (int) $sequence->next_value;
                DB::table('finance_sequences')->where('id', $sequence->id)->update([
                    'next_value' => $number + 1,
                    'updated_at' => now(),
                ]);
            }

            $invoice->update([
                'invoice_number' => sprintf('RE-%d-%06d', $year, $number),
                'status' => 'open',
                'issued_by' => $userId,
                'issued_at' => now(),
                'invoice_date' => $invoice->invoice_date ?: now()->toDateString(),
                'due_date' => $invoice->due_date ?: now()->addDays(14)->toDateString(),
            ]);

            return $invoice->fresh();
        });
    }

    public function recalculate(FinanceInvoice $invoice): FinanceInvoice
    {
        $net = round((float) $invoice->items()->sum('net_amount'), 2);
        $tax = round((float) $invoice->items()->sum('tax_amount'), 2);
        $gross = round((float) $invoice->items()->sum('gross_amount'), 2);
        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $status = $invoice->status;

        if (! in_array($status, ['draft', 'cancelled'], true)) {
            if ($paid >= $gross && $gross > 0) {
                $status = 'paid';
            } elseif ($invoice->due_date?->isPast() && $paid < $gross) {
                $status = 'overdue';
            } else {
                $status = 'open';
            }
        }

        $invoice->update([
            'net_amount' => $net,
            'tax_amount' => $tax,
            'gross_amount' => $gross,
            'paid_amount' => $paid,
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    public function recordPayment(FinanceInvoice $invoice, array $data, int $userId): FinancePayment
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            throw ValidationException::withMessages(['invoice' => 'Für Entwürfe oder stornierte Rechnungen können keine Zahlungen gebucht werden.']);
        }

        return DB::transaction(function () use ($invoice, $data, $userId): FinancePayment {
            $payment = FinancePayment::query()->create([
                'public_id' => Str::uuid(),
                'finance_invoice_id' => $invoice->id,
                'member_id' => $invoice->member_id,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $userId,
            ]);
            $this->recalculate($invoice);

            return $payment;
        });
    }
}
