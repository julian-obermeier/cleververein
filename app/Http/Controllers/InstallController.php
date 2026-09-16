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
            $this->provisionTenantDefaults($tenant->id, $user->id);
        });
        file_put_contents(storage_path('app/installed'), json_encode(['version' => '0.10.0', 'installed_at' => now()->toIso8601String()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);

        return redirect()->route('install.show', 'finish');
    }

    private function provisionTenantDefaults(int $tenantId, int $administratorId): void
    {
        $types = [
            ['name' => 'Dachverband', 'slug' => 'dachverband', 'sort_order' => 10],
            ['name' => 'Bundesverband', 'slug' => 'bundesverband', 'sort_order' => 20],
            ['name' => 'Landesverband', 'slug' => 'landesverband', 'sort_order' => 30],
            ['name' => 'Bezirksverband', 'slug' => 'bezirksverband', 'sort_order' => 40],
            ['name' => 'Kreisverband', 'slug' => 'kreisverband', 'sort_order' => 50],
            ['name' => 'Ortsverband', 'slug' => 'ortsverband', 'sort_order' => 60],
            ['name' => 'Verein', 'slug' => 'verein', 'sort_order' => 70],
            ['name' => 'Abteilung / Sparte', 'slug' => 'abteilung-sparte', 'sort_order' => 80],
            ['name' => 'Gruppe', 'slug' => 'gruppe', 'sort_order' => 90],
        ];
        foreach ($types as $type) {
            DB::table('organization_types')->updateOrInsert(
                ['tenant_id' => $tenantId, 'slug' => $type['slug']],
                [...$type, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach ([
            ['name' => 'Ordentliches Mitglied', 'code' => 'ORDENTLICH', 'sort_order' => 10],
            ['name' => 'Jugendmitglied', 'code' => 'JUGEND', 'sort_order' => 20],
            ['name' => 'Fördermitglied', 'code' => 'FOERDER', 'sort_order' => 30],
            ['name' => 'Passives Mitglied', 'code' => 'PASSIV', 'sort_order' => 40],
            ['name' => 'Ehrenmitglied', 'code' => 'EHRE', 'sort_order' => 50],
        ] as $memberType) {
            DB::table('member_types')->updateOrInsert(
                ['tenant_id' => $tenantId, 'name' => $memberType['name']],
                [...$memberType, 'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach ([
            ['name' => 'Vorsitz', 'code' => 'VORSITZ', 'category' => 'Vorstand', 'sort_order' => 10],
            ['name' => 'Stellvertretender Vorsitz', 'code' => 'STV_VORSITZ', 'category' => 'Vorstand', 'sort_order' => 20],
            ['name' => 'Kassenführung', 'code' => 'KASSE', 'category' => 'Vorstand', 'sort_order' => 30],
            ['name' => 'Schriftführung', 'code' => 'SCHRIFT', 'category' => 'Vorstand', 'sort_order' => 40],
            ['name' => 'Beisitz', 'code' => 'BEISITZ', 'category' => 'Vorstand', 'sort_order' => 50],
        ] as $function) {
            DB::table('function_definitions')->updateOrInsert(
                ['tenant_id' => $tenantId, 'name' => $function['name']],
                [...$function, 'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach ([
            ['name' => 'Bank', 'code' => 'BANK', 'type' => 'bank', 'is_default' => true, 'sort_order' => 10],
            ['name' => 'Kasse', 'code' => 'KASSE', 'type' => 'cash', 'is_default' => false, 'sort_order' => 20],
            ['name' => 'Verrechnung', 'code' => 'VERRECHNUNG', 'type' => 'clearing', 'is_default' => false, 'sort_order' => 90],
        ] as $account) {
            DB::table('finance_accounts')->updateOrInsert(
                ['tenant_id' => $tenantId, 'code' => $account['code']],
                [...$account, 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach ([
            ['name' => 'Mitgliedsbeiträge', 'code' => 'MITGLIEDSBEITRAEGE', 'direction' => 'income', 'sort_order' => 10],
            ['name' => 'Spenden', 'code' => 'SPENDEN', 'direction' => 'income', 'sort_order' => 20],
            ['name' => 'Sonstige Einnahmen', 'code' => 'SONSTIGE_EINNAHMEN', 'direction' => 'income', 'sort_order' => 90],
            ['name' => 'Betriebsausgaben', 'code' => 'BETRIEBSAUSGABEN', 'direction' => 'expense', 'sort_order' => 110],
            ['name' => 'Gebühren', 'code' => 'GEBUEHREN', 'direction' => 'expense', 'sort_order' => 120],
            ['name' => 'Sonstige Ausgaben', 'code' => 'SONSTIGE_AUSGABEN', 'direction' => 'expense', 'sort_order' => 190],
        ] as $category) {
            DB::table('finance_categories')->updateOrInsert(
                ['tenant_id' => $tenantId, 'code' => $category['code']],
                [...$category, 'default_tax_rate' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['tenant_id' => $tenantId, 'slug' => 'administrator'],
            ['name' => 'Administrator', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
        DB::table('role_assignments')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $administratorId,
            'role_id' => $roleId,
            'organization_unit_id' => null,
            'scope' => 'organization',
            'include_descendants' => true,
            'valid_from' => null,
            'valid_until' => null,
            'granted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
