<?php

use App\Http\Controllers\ElectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/wahlen', [ElectionController::class, 'index'])->name('elections.index');
    Route::post('/wahlen', [ElectionController::class, 'storeElection'])->name('elections.store');
    Route::get('/wahlen/{election}', [ElectionController::class, 'show'])->name('elections.show');
    Route::patch('/wahlen/{election}/status', [ElectionController::class, 'updateStatus'])->name('elections.status.update');
    Route::post('/wahlen/{election}/wahlberechtigte/importieren', [ElectionController::class, 'seedVoters'])->name('elections.voters.seed');
    Route::post('/wahlen/{election}/wahlberechtigte', [ElectionController::class, 'storeVoter'])->name('elections.voters.store');
    Route::put('/wahlen/{election}/wahlberechtigte/{voter}', [ElectionController::class, 'updateVoter'])->name('elections.voters.update');
    Route::post('/wahlen/{election}/vollmachten', [ElectionController::class, 'storeProxy'])->name('elections.proxies.store');
    Route::patch('/wahlen/{election}/vollmachten/{proxy}/widerrufen', [ElectionController::class, 'revokeProxy'])->name('elections.proxies.revoke');

    Route::post('/wahlen/{election}/aemter', [ElectionController::class, 'storeOffice'])->name('elections.offices.store');
    Route::post('/wahlen/{election}/aemter/{office}/kandidaturen', [ElectionController::class, 'storeCandidate'])->name('elections.candidates.store');
    Route::patch('/wahlen/{election}/aemter/{office}/kandidaturen/{candidate}', [ElectionController::class, 'updateCandidate'])->name('elections.candidates.update');
    Route::post('/wahlen/{election}/aemter/{office}/wahlgaenge', [ElectionController::class, 'startRound'])->name('elections.rounds.store');
    Route::post('/wahlen/{election}/aemter/{office}/wahlgaenge/{round}/abschliessen', [ElectionController::class, 'finalizeRound'])->name('elections.rounds.finalize');

    Route::post('/wahlen/{election}/feststellen', [ElectionController::class, 'finalizeElection'])->name('elections.finalize');
    Route::post('/wahlen/{election}/protokolle', [ElectionController::class, 'generateProtocol'])->name('elections.protocols.store');
    Route::get('/wahlen/{election}/protokolle/{protocol}/download', [ElectionController::class, 'downloadProtocol'])->name('elections.protocols.download');

    Route::post('/delegiertenmandate', [ElectionController::class, 'storeDelegate'])->name('delegates.store');
    Route::patch('/delegiertenmandate/{mandate}/beenden', [ElectionController::class, 'endDelegate'])->name('delegates.end');
});
