<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\Audit\AuditService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditService $audit, TenantContext $context): RedirectResponse
    {
        $key = Str::lower($request->string('email')).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Zu viele Anmeldeversuche. Bitte warten Sie '.RateLimiter::availableIn($key).' Sekunden.'])->onlyInput('email');
        }
        if (! Auth::attempt($request->safe()->only(['email', 'password']), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.'])->onlyInput('email');
        }
        if ($request->user()->locked_at) {
            Auth::logout();

            return back()->withErrors(['email' => 'Dieses Benutzerkonto ist gesperrt.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $request->user()->current_tenant_id);
        $request->user()->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        if ($request->user()->currentTenant) {
            $context->set($request->user()->currentTenant);
            $audit->record('auth.login', $request->user());
            $context->clear();
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
