<?php

use App\Http\Middleware\EnsureFormEditable;
use App\Http\Middleware\EnsureFormPublishable;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ResolvePublicFormTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/finance_operations.php'));
            Route::middleware('web')->group(base_path('routes/finance_controls.php'));
            Route::middleware('web')->group(base_path('routes/finance_recovery_donations.php'));
            Route::middleware('web')->group(base_path('routes/governance.php'));
            Route::middleware('web')->group(base_path('routes/events_communications.php'));
            Route::middleware('web')->group(base_path('routes/elections.php'));
            Route::middleware('web')->group(base_path('routes/forms.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'installed' => EnsureInstalled::class,
            'tenant' => ResolveTenant::class,
            'public-form-tenant' => ResolvePublicFormTenant::class,
            'form-editable' => EnsureFormEditable::class,
            'form-publishable' => EnsureFormPublishable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
