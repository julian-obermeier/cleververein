<?php

use App\Http\Controllers\GovernanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/gremien', [GovernanceController::class, 'index'])->name('governance.index');
    Route::post('/gremien', [GovernanceController::class, 'storeCommittee'])->name('governance.committees.store');
    Route::get('/gremien/{committee}', [GovernanceController::class, 'showCommittee'])->name('governance.committees.show');
    Route::post('/gremien/{committee}/mitglieder', [GovernanceController::class, 'addCommitteeMember'])->name('governance.committees.members.store');
    Route::patch('/gremien/{committee}/mitglieder/{assignment}/beenden', [GovernanceController::class, 'endCommitteeMember'])->name('governance.committees.members.end');

    Route::post('/sitzungen', [GovernanceController::class, 'storeMeeting'])->name('governance.meetings.store');
    Route::get('/sitzungen/{meeting}', [GovernanceController::class, 'showMeeting'])->name('governance.meetings.show');
    Route::put('/sitzungen/{meeting}', [GovernanceController::class, 'updateMeeting'])->name('governance.meetings.update');
    Route::post('/sitzungen/{meeting}/teilnehmer', [GovernanceController::class, 'addParticipant'])->name('governance.participants.store');
    Route::put('/sitzungen/{meeting}/teilnehmer/{participant}', [GovernanceController::class, 'updateParticipant'])->name('governance.participants.update');
    Route::post('/sitzungen/{meeting}/tagesordnung', [GovernanceController::class, 'storeAgendaItem'])->name('governance.agenda.store');
    Route::post('/sitzungen/{meeting}/antraege', [GovernanceController::class, 'storeMotion'])->name('governance.motions.store');
    Route::post('/sitzungen/{meeting}/beschluesse', [GovernanceController::class, 'storeResolution'])->name('governance.resolutions.store');
    Route::put('/sitzungen/{meeting}/protokoll', [GovernanceController::class, 'saveMinutes'])->name('governance.minutes.update');
    Route::post('/sitzungen/{meeting}/protokoll/freigeben', [GovernanceController::class, 'approveMinutes'])->name('governance.minutes.approve');
    Route::post('/sitzungen/{meeting}/aufgaben', [GovernanceController::class, 'storeTask'])->name('governance.tasks.store');
    Route::put('/gremien-aufgaben/{task}', [GovernanceController::class, 'updateTask'])->name('governance.tasks.update');
});
