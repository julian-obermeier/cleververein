<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Installation\EnvironmentWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PDO;
use Throwable;

class InstallController extends Controller
{
    private const STEPS = ['requirements', 'database', 'application', 'account', 'finish'];

    public function show(string $step = 'requirements'): View|RedirectResponse
    {
        if (file_exists(storage_path('app/installed')) && $step !== 'finish') {
            return redirect()->route('login');
        }
        abort_unless(in_array($step, self::STEPS, true), 404);

        return view('install.wizard', [
            'step' => $step,
            'steps' => self::STEPS,
            'requirements' => $this->requirements(),
        ]);
    }

    public function store(Request $request, string $step, EnvironmentWriter $environment): RedirectResponse
    {
        abort_if(file_exists(storage_path('app/installed')), 404);

        if ($step === 'requirements') {
            abort_unless(collect($this->requirements())->every('ok'), 422, 'Nicht alle Systemvoraussetzungen sind erfüllt.');
            return redirect()->route('install.show', 'database');
        }

        if ($step === 'database') {
            $data = $request->validate([
                'db_host' => ['required', 'string', 'max:255'], 'db_port' => ['required', 'integer', 'between:1,65535'],
                'db_database' => ['required', 'string', 'max:64'], 'db_username' => ['required', 'string', 'max:128'], 'db_password' => ['nullable', 'string', 'max:512'],
            ]);
            try {
                new PDO("mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4", $data['db_username'], $data['db_password'] ?? '', [PDO::ATTR_TIMEOUT => 5]);
            } catch (Throwable) {
                return back()->withErrors(['db_host' => 'Keine sichere Verbindung zur Datenbank möglich. Bitte Zugangsdaten und Freigaben prüfen.'])->withInput($request->except('db_password'));
            }
            $environment->write(['DB_CONNECTION' => 'mysql', 'DB_HOST' => $data['db_host'], 'DB_PORT' => $data['db_port'], 'DB_DATABASE' => $data['db_database'], 'DB_USERNAME' => $data['db_username'], 'DB_PASSWORD' => $data['db_password'] ?? '']);
            return redirect()->route('install.show', 'application');
        }

        if ($step === 'application') {
            $data = $request->validate(['app_name' => ['required', 'string', 'max:80'], 'app_url' => ['required', 'url', 'max:255'], 'mail_from_address' => ['required', 'email'], 'mail_from_name' => ['required', 'string', 'max:80']]);
            $environment->write([
                'APP_NAME' => $data['app_name'], 'APP_URL' => $data['app_url'],
                'MAIL_FROM_ADDRESS' => $data['mail_from_address'], 'MAIL_FROM_NAME' => $data['mail_from_name'],
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            ]);
            return redirect()->route('install.show', 'account');
        }

        abort_unless($step === 'account', 404);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'confirmed', 'min:12', 'max:255'],
            'tenant_name' => ['required', 'string', 'max:150'],
        ]);

        Artisan::call('config:clear');
        Artisan::call('migrate', ['--force' => true]);
        DB::transaction(function () use ($data): void {
            $tenant = Tenant::query()->create(['public_id' => Str::uuid(), 'name' => $data['tenant_name'], 'slug' => Str::slug($data['tenant_name']).'-'.Str::lower(Str::random(5)), 'status' => 'active', 'plan' => 'enterprise']);
            $person = Person::query()->create(['public_id' => Str::uuid(), 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email']]);
            $user = User::query()->create(['person_id' => $person->id, 'current_tenant_id' => $tenant->id, 'email' => $data['email'], 'password' => Hash::make($data['password']), 'email_verified_at' => now(), 'is_super_admin' => true]);
            $tenant->users()->attach($user->id, ['status' => 'active']);
        });
        file_put_contents(storage_path('app/installed'), json_encode(['version' => '0.1.0', 'installed_at' => now()->toIso8601String()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);

        return redirect()->route('install.show', 'finish');
    }

    private function requirements(): array
    {
        return [
            ['label' => 'PHP 8.4 oder neuer', 'value' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.4.0', '>=')],
            ['label' => 'PDO MySQL', 'value' => extension_loaded('pdo_mysql') ? 'verfügbar' : 'fehlt', 'ok' => extension_loaded('pdo_mysql')],
            ['label' => 'Mbstring', 'value' => extension_loaded('mbstring') ? 'verfügbar' : 'fehlt', 'ok' => extension_loaded('mbstring')],
            ['label' => 'OpenSSL', 'value' => extension_loaded('openssl') ? 'verfügbar' : 'fehlt', 'ok' => extension_loaded('openssl')],
            ['label' => 'Storage beschreibbar', 'value' => is_writable(storage_path()) ? 'ja' : 'nein', 'ok' => is_writable(storage_path())],
            ['label' => 'Cache beschreibbar', 'value' => is_writable(base_path('bootstrap/cache')) ? 'ja' : 'nein', 'ok' => is_writable(base_path('bootstrap/cache'))],
        ];
    }
}
