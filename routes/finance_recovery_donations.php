<?php

use App\Http\Controllers\FinanceRecoveryDonationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/finanzen/spenden-erstattungen', [FinanceRecoveryDonationController::class, 'index'])->name('finance.recovery.index');
    Route::post('/finanzen/zahlungen/{payment}/korrekturen', [FinanceRecoveryDonationController::class, 'storeAdjustment'])->name('finance.payment-adjustments.store');

    Route::post('/finanzen/spenden', [FinanceRecoveryDonationController::class, 'storeDonation'])->name('finance.donations.store');
    Route::post('/finanzen/spenden/{donation}/zuwendungsbestaetigung', [FinanceRecoveryDonationController::class, 'issueCertificate'])->name('finance.donations.certificates.issue');
    Route::get('/finanzen/zuwendungsbestaetigungen/{certificate}/pdf', [FinanceRecoveryDonationController::class, 'certificatePdf'])->name('finance.donation-certificates.pdf');
    Route::patch('/finanzen/zuwendungsbestaetigungen/{certificate}/storno', [FinanceRecoveryDonationController::class, 'voidCertificate'])->name('finance.donation-certificates.void');
});
