<?php

namespace App\Services\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceCashClosing;
use App\Models\FinanceEntry;
use App\Models\FinancePeriodLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceControlService
{
    public function assertOpen(string $date, ?int $accountId = null): void
    {
        $periodLocked = FinancePeriodLock::query()
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
        if ($periodLocked) {
            throw ValidationException::withMessages(['booking_date' => 'Das Buchungsdatum liegt in einer gesperrten Periode.']);
        }

        if ($accountId) {
            $account = FinanceAccount::query()->find($accountId);
            if ($account?->type === 'cash') {
                $closed = FinanceCashClosing::query()
                    ->where('finance_account_id', $accountId)
                    ->where('status', 'closed')
                    ->whereDate('closing_date', '>=', $date)
                    ->exists();
                if ($closed) {
                    throw ValidationException::withMessages(['booking_date' => 'Für dieses Kassenkonto wurde der Buchungstag bereits abgeschlossen.']);
                }
            }
        }
    }

    public function balance(FinanceAccount $account, string $date): float
    {
        $entries = FinanceEntry::query()
            ->where('finance_account_id', $account->id)
            ->whereDate('booking_date', '<=', $date)
            ->get(['direction', 'gross_amount']);
        $income = (float) $entries->where('direction', 'income')->sum('gross_amount');
        $expense = (float) $entries->where('direction', 'expense')->sum('gross_amount');

        return round((float) $account->opening_balance + $income - $expense, 2);
    }

    public function closeCash(FinanceAccount $account, string $date, float $countedBalance, int $userId, ?string $notes = null, ?array $denominations = null): FinanceCashClosing
    {
        if ($account->type !== 'cash') {
            throw ValidationException::withMessages(['finance_account_id' => 'Ein Kassenabschluss ist nur für Kassenkonten möglich.']);
        }
        if (FinanceCashClosing::query()->where('finance_account_id', $account->id)->whereDate('closing_date', $date)->where('status', 'closed')->exists()) {
            throw ValidationException::withMessages(['closing_date' => 'Für dieses Kassenkonto existiert an diesem Datum bereits ein Abschluss.']);
        }
        $latest = FinanceCashClosing::query()->where('finance_account_id', $account->id)->where('status', 'closed')->max('closing_date');
        if ($latest && $date <= $latest) {
            throw ValidationException::withMessages(['closing_date' => 'Der neue Kassenabschluss muss nach dem letzten abgeschlossenen Kassenstand liegen.']);
        }
        $system = $this->balance($account, $date);
        $counted = round($countedBalance, 2);

        return FinanceCashClosing::query()->create([
            'public_id' => Str::uuid(),
            'finance_account_id' => $account->id,
            'closing_date' => $date,
            'system_balance' => $system,
            'counted_balance' => $counted,
            'difference' => round($counted - $system, 2),
            'denomination_counts' => $denominations,
            'notes' => $notes,
            'status' => 'closed',
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);
    }

    public function lockPeriod(string $start, string $end, string $reason, int $userId): FinancePeriodLock
    {
        $overlap = FinancePeriodLock::query()->where('status', 'locked')
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['period_start' => 'Der gewählte Zeitraum überschneidet sich mit einer bereits gesperrten Periode.']);
        }

        return DB::transaction(fn (): FinancePeriodLock => FinancePeriodLock::query()->create([
            'public_id' => Str::uuid(),
            'period_start' => $start,
            'period_end' => $end,
            'reason' => $reason,
            'status' => 'locked',
            'locked_by' => $userId,
            'locked_at' => now(),
        ]));
    }

    public function unlockPeriod(FinancePeriodLock $lock, int $userId): FinancePeriodLock
    {
        if ($lock->status !== 'locked') {
            throw ValidationException::withMessages(['period' => 'Diese Periode ist bereits geöffnet.']);
        }
        $lock->update([
            'status' => 'unlocked',
            'unlocked_by' => $userId,
            'unlocked_at' => now(),
        ]);

        return $lock->fresh();
    }
}
