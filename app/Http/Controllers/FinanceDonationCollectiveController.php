<?php

namespace App\Http\Controllers;

use App\Models\FinanceDonation;
use App\Models\FinanceDonationCollectiveCertificate;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDonationCollectiveController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function index(Request $request): View
    {
        abort_unless(
            $request->user()->is_super_admin || $this->permissions->allows($request->user(), 'finance.donation_certificates'),
            403,
            'Für diesen Bereich fehlt die Berechtigung.',
        );
        $year = (int) ($request->integer('year') ?: now()->year);

        $eligible = FinanceDonation::query()
            ->with(['certificates', 'collectiveItems.certificate'])
            ->where('status', 'received')
            ->whereYear('donation_date', $year)
            ->whereDoesntHave('certificates', fn ($query) => $query->where('status', 'issued'))
            ->whereDoesntHave('collectiveItems.certificate', fn ($query) => $query->where('status', 'issued'))
            ->orderBy('donor_name')
            ->orderBy('donation_date')
            ->get();

        return view('finance.donation-collective', [
            'year' => $year,
            'eligible' => $eligible,
            'certificates' => FinanceDonationCollectiveCertificate::query()
                ->with('items.donation')
                ->whereYear('issue_date', $year)
                ->latest('issue_date')
                ->latest('id')
                ->get(),
        ]);
    }
}
