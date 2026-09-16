<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceSetting;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Services\Finance\FinanceTaxExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceTaxExportController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private FinanceTaxExportService $exports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission($request);

        return view('finance.tax-export', [
            'settings' => FinanceSetting::query()->first(),
            'accounts' => FinanceAccount::query()->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => FinanceCategory::query()->orderBy('direction')->orderBy('sort_order')->orderBy('name')->get(),
            'year' => (int) ($request->integer('year') ?: now()->year),
            'month' => $request->filled('month') ? (int) $request->integer('month') : null,
        ]);
    }

    public function saveMapping(Request $request): RedirectResponse
    {
        $this->authorizePermission($request);
        $data = $request->validate([
            'datev_consultant_number' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'datev_client_number' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'datev_chart' => ['nullable', 'string', 'max:12'],
            'datev_account_length' => ['required', 'integer', 'between:4,8'],
            'account_map' => ['nullable', 'array'],
            'account_map.*' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'category_map' => ['nullable', 'array'],
            'category_map.*' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
        ]);

        $settings = FinanceSetting::query()->firstOrCreate([], ['payment_terms_days' => 14]);
        $settings->update([
            'datev_consultant_number' => $data['datev_consultant_number'] ?? null,
            'datev_client_number' => $data['datev_client_number'] ?? null,
            'datev_chart' => $data['datev_chart'] ?? null,
            'datev_account_length' => $data['datev_account_length'],
        ]);

        foreach ($data['account_map'] ?? [] as $id => $accountNumber) {
            FinanceAccount::query()->findOrFail((int) $id)->update(['datev_account' => filled($accountNumber) ? trim($accountNumber) : null]);
        }
        foreach ($data['category_map'] ?? [] as $id => $accountNumber) {
            FinanceCategory::query()->findOrFail((int) $id)->update(['datev_account' => filled($accountNumber) ? trim($accountNumber) : null]);
        }

        $this->audit->record('finance.tax_mapping_updated', $settings, new: [
            'datev_consultant_number' => $settings->datev_consultant_number,
            'datev_client_number' => $settings->datev_client_number,
            'datev_chart' => $settings->datev_chart,
            'datev_account_length' => $settings->datev_account_length,
        ]);

        return back()->with('success', 'Steuerberater-Kontenzuordnung wurde gespeichert.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request);
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = $request->filled('month') ? (int) $request->integer('month') : null;
        abort_unless($year >= 2000 && $year <= 2100, 422, 'Ungültiges Exportjahr.');
        abort_if($month !== null && ($month < 1 || $month > 12), 422, 'Ungültiger Exportmonat.');

        $rows = $this->exports->rows($year, $month);
        $settings = $this->exports->settings();
        $this->audit->record('finance.tax_exported', null, new: [
            'year' => $year,
            'month' => $month,
            'rows' => $rows->count(),
            'chart' => $settings?->datev_chart,
        ]);

        return response()->streamDownload(function () use ($rows, $settings): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Exportart', 'cleververein Steuerberater-Arbeitsdatei'], ';');
            fputcsv($out, ['Kontenrahmen', $settings?->datev_chart ?: 'nicht angegeben'], ';');
            fputcsv($out, ['Beraternummer', $settings?->datev_consultant_number ?: ''], ';');
            fputcsv($out, ['Mandantennummer', $settings?->datev_client_number ?: ''], ';');
            fputcsv($out, [], ';');
            fputcsv($out, [
                'Umsatz', 'Soll/Haben', 'Konto', 'Gegenkonto', 'Belegdatum', 'Belegfeld 1', 'Buchungstext',
                'Buchungsnummer', 'Netto', 'Steuer', 'Steuersatz', 'Kategorie', 'Finanzkonto', 'Mitglied',
                'Rechnung', 'Quelle',
            ], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    number_format($row['amount'], 2, ',', ''),
                    $row['side'],
                    $row['account'],
                    $row['contra_account'],
                    $row['document_date'],
                    $row['document_field'],
                    $row['text'],
                    $row['entry_number'],
                    number_format(abs($row['net']), 2, ',', ''),
                    number_format(abs($row['tax']), 2, ',', ''),
                    number_format($row['tax_rate'], 2, ',', ''),
                    $row['category'],
                    $row['finance_account'],
                    $row['member'],
                    $row['invoice'],
                    $row['source'],
                ], ';');
            }
            fclose($out);
        }, 'Steuerberater-Arbeitsdatei-'.$year.($month ? '-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizePermission(Request $request): void
    {
        abort_unless(
            $request->user()->is_super_admin || $this->permissions->allows($request->user(), 'finance.tax_export'),
            403,
            'Für diesen Bereich fehlt die Berechtigung.',
        );
    }
}
