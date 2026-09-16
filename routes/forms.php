<?php

use App\Http\Controllers\FormController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\PublicFormController;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('/formulare', [FormController::class, 'index'])->name('forms.index');
    Route::post('/formulare', [FormController::class, 'store'])->name('forms.store');
    Route::get('/formulare/{form}/bearbeiten', [FormController::class, 'edit'])->name('forms.edit');
    Route::put('/formulare/{form}', [FormController::class, 'update'])->name('forms.update');
    Route::post('/formulare/{form}/veroeffentlichen', [FormController::class, 'publish'])->name('forms.publish');
    Route::patch('/formulare/{form}/archivieren', [FormController::class, 'archive'])->name('forms.archive');

    Route::post('/formulare/{form}/felder', [FormController::class, 'storeField'])->name('forms.fields.store');
    Route::put('/formulare/{form}/felder/{field}', [FormController::class, 'updateField'])->name('forms.fields.update');
    Route::delete('/formulare/{form}/felder/{field}', [FormController::class, 'destroyField'])->name('forms.fields.destroy');
    Route::post('/formulare/{form}/felder/reihenfolge', [FormController::class, 'reorderFields'])->name('forms.fields.reorder');

    Route::post('/formulare/{form}/workflows', [FormController::class, 'storeWorkflow'])->name('forms.workflows.store');
    Route::patch('/formulare/{form}/workflows/{workflow}/aktivieren', [FormController::class, 'activateWorkflow'])->name('forms.workflows.activate');
    Route::post('/formulare/{form}/workflows/{workflow}/schritte', [FormController::class, 'storeWorkflowStep'])->name('forms.workflow-steps.store');
    Route::put('/formulare/{form}/workflows/{workflow}/schritte/{step}', [FormController::class, 'updateWorkflowStep'])->name('forms.workflow-steps.update');
    Route::delete('/formulare/{form}/workflows/{workflow}/schritte/{step}', [FormController::class, 'destroyWorkflowStep'])->name('forms.workflow-steps.destroy');
    Route::post('/formulare/{form}/workflows/{workflow}/reihenfolge', [FormController::class, 'reorderWorkflowSteps'])->name('forms.workflow-steps.reorder');

    Route::get('/formulare/{form}/ausfuellen', [FormSubmissionController::class, 'fill'])->name('forms.fill');
    Route::post('/formulare/{form}/einreichen', [FormSubmissionController::class, 'store'])->name('forms.submit');

    Route::get('/formular-einreichungen', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');
    Route::get('/formular-einreichungen/{submission}', [FormSubmissionController::class, 'show'])->name('forms.submissions.show');
    Route::post('/formular-einreichungen/{submission}/schritte/{step}', [FormSubmissionController::class, 'process'])->name('forms.submissions.process');
    Route::patch('/formular-einreichungen/{submission}/zuweisen', [FormSubmissionController::class, 'reassign'])->name('forms.submissions.reassign');
    Route::post('/formular-einreichungen/{submission}/abschliessen', [FormSubmissionController::class, 'complete'])->name('forms.submissions.complete');
    Route::get('/formular-einreichungen/{submission}/anlagen/{attachment}', [FormSubmissionController::class, 'downloadAttachment'])->name('forms.attachments.download');
});

Route::middleware(['installed', 'public-form-tenant', 'throttle:20,1'])->group(function (): void {
    Route::get('/f/{token}', [PublicFormController::class, 'show'])->name('forms.public.show');
    Route::post('/f/{token}', [PublicFormController::class, 'store'])->name('forms.public.store');
});
