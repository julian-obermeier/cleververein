<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FunctionDirectoryController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberSpreadsheetController;
use App\Http\Controllers\MemberToolsController;
use App\Http\Controllers\OrganizationController;
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

    Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/mitglieder', [MemberController::class, 'index'])->name('members.index');
        Route::get('/mitglieder/anlegen', [MemberController::class, 'create'])->name('members.create');
        Route::post('/mitglieder', [MemberController::class, 'store'])->name('members.store');
        Route::get('/mitglieder/einstellungen', [MemberToolsController::class, 'settings'])->name('members.settings');
        Route::post('/mitglieder/einstellungen/mitgliedsarten', [MemberToolsController::class, 'storeMemberType'])->name('members.types.store');
        Route::patch('/mitglieder/einstellungen/mitgliedsarten/{memberType}', [MemberToolsController::class, 'toggleMemberType'])->name('members.types.toggle');
        Route::post('/mitglieder/einstellungen/funktionen', [MemberToolsController::class, 'storeFunctionDefinition'])->name('members.functions.definitions.store');
        Route::patch('/mitglieder/einstellungen/funktionen/{function}', [MemberToolsController::class, 'toggleFunctionDefinition'])->name('members.functions.definitions.toggle');

        Route::get('/mitglieder/funktionen', [FunctionDirectoryController::class, 'index'])->name('members.functions.index');
        Route::get('/mitglieder/haushalte', [HouseholdController::class, 'index'])->name('members.households.index');
        Route::post('/mitglieder/haushalte', [HouseholdController::class, 'store'])->name('members.households.central.store');
        Route::put('/mitglieder/haushalte/{household}', [HouseholdController::class, 'update'])->name('members.households.update');
        Route::post('/mitglieder/haushalte/{household}/mitglieder', [HouseholdController::class, 'attachMember'])->name('members.households.members.store');
        Route::delete('/mitglieder/haushalte/{household}/mitglieder/{member}', [HouseholdController::class, 'detachMember'])->name('members.households.members.destroy');
        Route::delete('/mitglieder/haushalte/{household}', [HouseholdController::class, 'destroy'])->name('members.households.archive');

        Route::get('/mitglieder-export.csv', [MemberToolsController::class, 'export'])->name('members.export');
        Route::post('/mitglieder-import', [MemberToolsController::class, 'import'])->name('members.import');
        Route::get('/mitglieder-export.xlsx', [MemberSpreadsheetController::class, 'export'])->name('members.export.xlsx');
        Route::get('/mitglieder-importvorlage.xlsx', [MemberSpreadsheetController::class, 'template'])->name('members.import.template.xlsx');
        Route::post('/mitglieder-import.xlsx', [MemberSpreadsheetController::class, 'import'])->name('members.import.xlsx');

        Route::get('/mitglieder/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('/mitglieder/{member}/bearbeiten', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/mitglieder/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/mitglieder/{member}', [MemberController::class, 'archive'])->name('members.archive');
        Route::post('/mitglieder/{member}/wiederherstellen', [MemberController::class, 'restore'])->whereNumber('member')->name('members.restore');
        Route::post('/mitglieder/{member}/mitgliedschaften', [MemberController::class, 'storeMembership'])->name('members.memberships.store');
        Route::delete('/mitglieder/{member}/mitgliedschaften/{membership}', [MemberController::class, 'destroyMembership'])->name('members.memberships.destroy');
        Route::post('/mitglieder/{member}/funktionen', [MemberToolsController::class, 'storeFunctionAssignment'])->name('members.functions.store');
        Route::delete('/mitglieder/{member}/funktionen/{assignment}', [MemberToolsController::class, 'destroyFunctionAssignment'])->name('members.functions.destroy');
        Route::post('/mitglieder/{member}/haushalte', [MemberToolsController::class, 'storeHousehold'])->name('members.households.store');
        Route::delete('/mitglieder/{member}/haushalte/{household}', [MemberToolsController::class, 'detachHousehold'])->name('members.households.destroy');

        Route::get('/organisation', [OrganizationController::class, 'index'])->name('organization.index');
        Route::post('/organisation/typen', [OrganizationController::class, 'storeType'])->name('organization.types.store');
        Route::post('/organisation/einheiten', [OrganizationController::class, 'storeUnit'])->name('organization.units.store');
        Route::put('/organisation/einheiten/{unit}', [OrganizationController::class, 'updateUnit'])->name('organization.units.update');
        Route::delete('/organisation/einheiten/{unit}', [OrganizationController::class, 'archiveUnit'])->name('organization.units.archive');
    });
});
