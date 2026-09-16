<?php

use App\Http\Controllers\FinanceControlController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/finanzen/kasse-pruefung', [FinanceControlController::class, 'index'])->name('finance.controls.index');

    Route::post('/finanzen/buchungen/{entry}/belege', [FinanceControlController::class, 'storeReceipt'])->name('finance.receipts.store');
    Route::get('/finanzen/belege/{receipt}/download', [FinanceControlController::class, 'downloadReceipt'])->name('finance.receipts.download');
    Route::patch('/finanzen/belege/{receipt}/ungueltig', [FinanceControlController::class, 'voidReceipt'])->name('finance.receipts.void');

    Route::post('/finanzen/kassenabschluesse', [FinanceControlController::class, 'closeCash'])->name('finance.cash.closings.store');
    Route::get('/finanzen/kassenbuch/export', [FinanceControlController::class, 'exportCash'])->name('finance.cash.export');

    Route::post('/finanzen/periodensperren', [FinanceControlController::class, 'lockPeriod'])->name('finance.periods.store');
    Route::patch('/finanzen/periodensperren/{lock}/oeffnen', [FinanceControlController::class, 'unlockPeriod'])->name('finance.periods.unlock');

    Route::post('/finanzen/kassenpruefungen', [FinanceControlController::class, 'storeAudit'])->name('finance.cash-audits.store');
});
