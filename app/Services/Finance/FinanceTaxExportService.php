<?php

namespace App\Services\Finance;

use App\Models\FinanceEntry;
use App\Models\FinanceSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FinanceTaxExportService
{
    public function rows(int $year, ?int $month = null): Collection
    {
        $query = FinanceEntry::query()
            ->with(['account', 'category', 'member.person', 'invoice'])
            ->whereYear('booking_date', $year)
            ->whereIn('status', ['posted', 'reversed']);
        if ($month) {
            $query->whereMonth('booking_date', $month);
        }
        $entries = $query->orderBy('booking_date')->orderBy('id')->get();
        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Für den gewählten Zeitraum liegen keine Buchungen vor.']);
        }

        $missing = [];
        foreach ($entries as $entry) {
            if (! filled($entry->account?->datev_account)) {
                $missing['Konto '.$entry->account?->code] = $entry->account?->name ?: 'Unbekanntes Finanzkonto';
            }
            if (! filled($entry->category?->datev_account)) {
                $missing['Kategorie '.$entry->category?->code] = $entry->category?->name ?: 'Ohne Kategorie';
            }
        }
        if ($missing !== []) {
            $labels = collect($missing)->map(fn (string $name, string $key) => $key.' ('.$name.')')->values()->take(12)->implode(', ');
            throw ValidationException::withMessages([
                'export' => 'Für den DATEV-nahen Export fehlen Kontenzuordnungen: '.$labels.($missing && count($missing) > 12 ? ' …' : ''),
            ]);
        }

        return $entries->map(function (FinanceEntry $entry): array {
            $negative = (float) $entry->gross_amount < 0;
            $side = $entry->direction === 'income' ? 'H' : 'S';
            if ($negative) {
                $side = $side === 'H' ? 'S' : 'H';
            }

            return [
                'amount' => abs((float) $entry->gross_amount),
                'side' => $side,
                'account' => $entry->category?->datev_account,
                'contra_account' => $entry->account?->datev_account,
                'document_date' => $entry->booking_date->format('d.m.Y'),
                'document_field' => $entry->invoice?->invoice_number ?: ($entry->reference ?: $entry->entry_number),
                'text' => $entry->description,
                'entry_number' => $entry->entry_number,
                'net' => (float) $entry->net_amount,
                'tax' => (float) $entry->tax_amount,
                'tax_rate' => (float) $entry->tax_rate,
                'category' => $entry->category?->name,
                'finance_account' => $entry->account?->name,
                'member' => $entry->member?->person?->display_name,
                'invoice' => $entry->invoice?->invoice_number,
                'source' => $entry->source_type,
            ];
        });
    }

    public function settings(): ?FinanceSetting
    {
        return FinanceSetting::query()->first();
    }
}
