<?php

use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRsvpController;
use Illuminate\Support\Facades\Route;

Route::middleware('installed')->group(function (): void {
    Route::get('/teilnahme/{token}', [EventRsvpController::class, 'show'])->name('events.rsvp.show');
    Route::post('/teilnahme/{token}', [EventRsvpController::class, 'update'])->name('events.rsvp.update');
});

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/veranstaltungen', [EventController::class, 'index'])->name('events.index');
    Route::post('/veranstaltungen', [EventController::class, 'store'])->name('events.store');
    Route::get('/veranstaltungen/{event}', [EventController::class, 'show'])->name('events.show');
    Route::put('/veranstaltungen/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/veranstaltungen/{event}/einladen', [EventController::class, 'inviteAudience'])->name('events.invite');
    Route::post('/veranstaltungen/{event}/gaeste', [EventController::class, 'addGuest'])->name('events.guests.store');
    Route::post('/veranstaltungen/{event}/anmeldungen/{registration}/antwort', [EventController::class, 'respond'])->name('events.registrations.respond');
    Route::patch('/veranstaltungen/{event}/anmeldungen/{registration}/anwesenheit', [EventController::class, 'attendance'])->name('events.registrations.attendance');
    Route::get('/veranstaltungen/{event}/teilnehmer.csv', [EventController::class, 'participantsCsv'])->name('events.participants.csv');
    Route::get('/veranstaltungen/{event}/kalender.ics', [EventController::class, 'ics'])->name('events.ics');

    Route::get('/kommunikation', [CommunicationController::class, 'index'])->name('communications.index');
    Route::post('/kommunikation/vorlagen', [CommunicationController::class, 'storeTemplate'])->name('communications.templates.store');
    Route::patch('/kommunikation/vorlagen/{template}/status', [CommunicationController::class, 'toggleTemplate'])->name('communications.templates.toggle');
    Route::post('/kommunikation/kampagnen', [CommunicationController::class, 'storeCampaign'])->name('communications.campaigns.store');
    Route::get('/kommunikation/kampagnen/{campaign}', [CommunicationController::class, 'showCampaign'])->name('communications.campaigns.show');
    Route::post('/kommunikation/kampagnen/{campaign}/vorbereiten', [CommunicationController::class, 'prepare'])->name('communications.campaigns.prepare');
    Route::post('/kommunikation/kampagnen/{campaign}/senden', [CommunicationController::class, 'sendBatch'])->name('communications.campaigns.send');
});
