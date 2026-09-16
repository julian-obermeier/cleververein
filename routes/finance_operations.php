<?php

use App\Http\Controllers\FinanceOperationsController;
use App\Http\Controllers\HouseholdContributionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/finanzen/operationen', [FinanceOperationsController::class, 'index'])->name('finance.operations.index');
    Route::put('/finanzen/operationen/stammdaten', [FinanceOperationsController::class, 'saveSettings'])->name('finance.operations.settings');

    Route::get('/finanzen/rechnungen/{invoice}/pdf', [FinanceOperationsController::class, 'invoicePdf'])->name('finance.invoices.pdf');
    Route::post('/finanzen/rechnungen/{invoice}/gutschrift', [FinanceOperationsController::class, 'storeCredit'])->name('finance.credits.store');
    Route::get('/finanzen/gutschriften/{creditNote}/pdf', [FinanceOperationsController::class, 'creditPdf'])->name('finance.credits.pdf');
    Route::get('/finanzen/mahnungen/{dunning}/pdf', [FinanceOperationsController::class, 'dunningPdf'])->name('finance.dunnings.pdf');

    Route::post('/finanzen/haushaltsbeitraege', [HouseholdContributionController::class, 'storeRate'])->name('finance.household-rates.store');
    Route::post('/finanzen/haushaltsbeitraege/lauf', [FinanceOperationsController::class, 'runHouseholds'])->name('finance.households.run');

    Route::post('/finanzen/sepa-laeufe', [FinanceOperationsController::class, 'createSepa'])->name('finance.sepa.batches.store');
    Route::get('/finanzen/sepa-laeufe/{batch}/download', [FinanceOperationsController::class, 'downloadSepa'])->name('finance.sepa.batches.download');
    Route::patch('/finanzen/sepa-laeufe/{batch}/eingereicht', [FinanceOperationsController::class, 'submitSepa'])->name('finance.sepa.batches.submit');

    Route::post('/finanzen/bankimport', [FinanceOperationsController::class, 'importBank'])->name('finance.bank.import');
    Route::post('/finanzen/bankumsatz/{transaction}/zuordnen', [FinanceOperationsController::class, 'assignBank'])->name('finance.bank.assign');
    Route::patch('/finanzen/bankumsatz/{transaction}/ignorieren', [FinanceOperationsController::class, 'ignoreBank'])->name('finance.bank.ignore');
});
