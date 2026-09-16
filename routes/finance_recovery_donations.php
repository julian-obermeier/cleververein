<?php

use App\Http\Controllers\FinanceDonationCollectiveController;
use App\Http\Controllers\FinanceRecoveryDonationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/finanzen/spenden-erstattungen', [FinanceRecoveryDonationController::class, 'index'])->name('finance.recovery.index');
    Route::post('/finanzen/zahlungen/{payment}/korrekturen', [FinanceRecoveryDonationController::class, 'storeAdjustment'])->name('finance.payment-adjustments.store');

    Route::post('/finanzen/spenden', [FinanceRecoveryDonationController::class, 'storeDonation'])->name('finance.donations.store');
    Route::post('/finanzen/spenden/{donation}/zuwendungsbestaetigung', [FinanceRecoveryDonationController::class, 'issueCertificate'])->name('finance.donations.certificates.issue');
    Route::get('/finanzen/sammelbestaetigungen', [FinanceDonationCollectiveController::class, 'index'])->name('finance.donation-collective.index');
    Route::post('/finanzen/spenden/sammelbestaetigung', [FinanceRecoveryDonationController::class, 'issueCollectiveCertificate'])->name('finance.donations.collective-certificates.issue');
    Route::get('/finanzen/zuwendungsbestaetigungen/{certificate}/pdf', [FinanceRecoveryDonationController::class, 'certificatePdf'])->name('finance.donation-certificates.pdf');
    Route::patch('/finanzen/zuwendungsbestaetigungen/{certificate}/storno', [FinanceRecoveryDonationController::class, 'voidCertificate'])->name('finance.donation-certificates.void');
    Route::get('/finanzen/sammelbestaetigungen/{collectiveCertificate}/pdf', [FinanceRecoveryDonationController::class, 'collectiveCertificatePdf'])->name('finance.donation-collective-certificates.pdf');
    Route::patch('/finanzen/sammelbestaetigungen/{collectiveCertificate}/storno', [FinanceRecoveryDonationController::class, 'voidCollectiveCertificate'])->name('finance.donation-collective-certificates.void');
});
