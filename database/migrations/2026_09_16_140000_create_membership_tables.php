<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            Schema::create('members', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
                $table->string('member_number', 64)->nullable();
                $table->string('status', 32)->default('active')->index();
                $table->date('joined_at')->nullable();
                $table->date('left_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'person_id']);
                $table->unique(['tenant_id', 'member_number']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'joined_at']);
            });
        }

        if (! Schema::hasTable('memberships')) {
            Schema::create('memberships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->string('membership_type', 100)->default('Ordentliches Mitglied');
                $table->string('status', 32)->default('active')->index();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'member_id', 'status']);
                $table->index(['tenant_id', 'organization_unit_id', 'status']);
            });
        }

        $permissions = [
            ['key' => 'members.view', 'module' => 'members', 'action' => 'view', 'name' => 'Mitglieder anzeigen', 'description' => 'Mitgliederlisten und Mitgliederdetails anzeigen.'],
            ['key' => 'members.create', 'module' => 'members', 'action' => 'create', 'name' => 'Mitglieder anlegen', 'description' => 'Neue Personen und Mitgliedschaften anlegen.'],
            ['key' => 'members.update', 'module' => 'members', 'action' => 'update', 'name' => 'Mitglieder bearbeiten', 'description' => 'Stamm- und Mitgliedsdaten bearbeiten.'],
            ['key' => 'members.archive', 'module' => 'members', 'action' => 'archive', 'name' => 'Mitglieder archivieren', 'description' => 'Mitglieder austreten lassen, archivieren und wiederherstellen.'],
            ['key' => 'members.memberships', 'module' => 'members', 'action' => 'memberships', 'name' => 'Mitgliedschaften verwalten', 'description' => 'Zuordnungen zu Organisationseinheiten verwalten.'],
            ['key' => 'organization.view', 'module' => 'organization', 'action' => 'view', 'name' => 'Organisation anzeigen', 'description' => 'Organisationsstruktur und Gliederungen anzeigen.'],
            ['key' => 'organization.manage', 'module' => 'organization', 'action' => 'manage', 'name' => 'Organisation verwalten', 'description' => 'Organisationstypen und Gliederungen anlegen und bearbeiten.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [...$permission, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $defaultTypes = [
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

        $permissionIds = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($defaultTypes as $type) {
                DB::table('organization_types')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'slug' => $type['slug']],
                    [...$type, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            DB::table('roles')->updateOrInsert(
                ['tenant_id' => $tenantId, 'slug' => 'administrator'],
                ['name' => 'Administrator', 'is_system' => true, 'updated_at' => now(), 'created_at' => now()],
            );
            $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }

            $superAdminIds = DB::table('tenant_user')
                ->join('users', 'users.id', '=', 'tenant_user.user_id')
                ->where('tenant_user.tenant_id', $tenantId)
                ->where('tenant_user.status', 'active')
                ->where('users.is_super_admin', true)
                ->pluck('users.id');
            foreach ($superAdminIds as $userId) {
                if (! DB::table('role_assignments')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('role_id', $roleId)->whereNull('organization_unit_id')->exists()) {
                    DB::table('role_assignments')->insert([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
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
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'members.view', 'members.create', 'members.update', 'members.archive', 'members.memberships',
            'organization.view', 'organization.manage',
        ])->delete();

        Schema::dropIfExists('memberships');
        Schema::dropIfExists('members');
    }
};
