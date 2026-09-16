<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'show'])->name('install.index');
Route::get('/install/{step}', [InstallController::class, 'show'])->name('install.show');
Route::post('/install/{step}', [InstallController::class, 'store'])->middleware('throttle:10,1')->name('install.store');

Route::middleware('installed')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::middleware('guest')->group(function (): void {
        Route::get('/anmelden', [AuthController::class, 'create'])->name('login');
        Route::post('/anmelden', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
        Route::get('/passwort-vergessen', [PasswordController::class, 'requestForm'])->name('password.request');
        Route::post('/passwort-vergessen', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/passwort-zuruecksetzen/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
        Route::post('/passwort-zuruecksetzen', [PasswordController::class, 'reset'])->name('password.update');
    });
    Route::post('/abmelden', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
    Route::middleware('auth')->group(function (): void {
        Route::get('/email-bestaetigen', [VerifyEmailController::class, 'notice'])->name('verification.notice');
        Route::get('/email-bestaetigen/{id}/{hash}', [VerifyEmailController::class, 'verify'])->middleware('signed')->name('verification.verify');
        Route::post('/email-bestaetigung-senden', [VerifyEmailController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');
    });
    Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'tenant'])->name('dashboard');
});
