<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_types')) {
            Schema::create('member_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 40)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasColumn('memberships', 'member_type_id')) {
            Schema::table('memberships', function (Blueprint $table): void {
                $table->foreignId('member_type_id')->nullable()->after('organization_unit_id')->constrained('member_types')->nullOnDelete();
                $table->index(['tenant_id', 'member_type_id', 'status']);
            });
        }

        if (! Schema::hasTable('households')) {
            Schema::create('households', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->json('contact_data')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'name']);
            });
        }

        if (! Schema::hasTable('household_members')) {
            Schema::create('household_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('household_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->string('relationship', 80)->nullable();
                $table->boolean('is_primary_contact')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'household_id', 'member_id']);
                $table->index(['tenant_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('function_definitions')) {
            Schema::create('function_definitions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('code', 40)->nullable();
                $table->string('category', 80)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('function_assignments')) {
            Schema::create('function_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->foreignId('function_definition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'member_id', 'ends_at']);
                $table->index(['tenant_id', 'organization_unit_id', 'function_definition_id']);
            });
        }

        $permissions = [
            ['key' => 'members.master_data', 'module' => 'members', 'action' => 'master_data', 'name' => 'Mitglieder-Stammdaten verwalten', 'description' => 'Mitgliedsarten und Funktionskatalog verwalten.'],
            ['key' => 'members.households', 'module' => 'members', 'action' => 'households', 'name' => 'Haushalte verwalten', 'description' => 'Haushalte und Familienzuordnungen verwalten.'],
            ['key' => 'members.functions', 'module' => 'members', 'action' => 'functions', 'name' => 'Funktionen verwalten', 'description' => 'Ämter und Funktionen Mitgliedern zuweisen.'],
            ['key' => 'members.import_export', 'module' => 'members', 'action' => 'import_export', 'name' => 'Mitglieder importieren/exportieren', 'description' => 'CSV-Importe und Exporte durchführen.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [...$permission, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $defaultMemberTypes = [
            ['name' => 'Ordentliches Mitglied', 'code' => 'ORDENTLICH', 'sort_order' => 10],
            ['name' => 'Jugendmitglied', 'code' => 'JUGEND', 'sort_order' => 20],
            ['name' => 'Fördermitglied', 'code' => 'FOERDER', 'sort_order' => 30],
            ['name' => 'Passives Mitglied', 'code' => 'PASSIV', 'sort_order' => 40],
            ['name' => 'Ehrenmitglied', 'code' => 'EHRE', 'sort_order' => 50],
        ];

        $defaultFunctions = [
            ['name' => 'Vorsitz', 'code' => 'VORSITZ', 'category' => 'Vorstand', 'sort_order' => 10],
            ['name' => 'Stellvertretender Vorsitz', 'code' => 'STV_VORSITZ', 'category' => 'Vorstand', 'sort_order' => 20],
            ['name' => 'Kassenführung', 'code' => 'KASSE', 'category' => 'Vorstand', 'sort_order' => 30],
            ['name' => 'Schriftführung', 'code' => 'SCHRIFT', 'category' => 'Vorstand', 'sort_order' => 40],
            ['name' => 'Beisitz', 'code' => 'BEISITZ', 'category' => 'Vorstand', 'sort_order' => 50],
        ];

        $permissionIds = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($defaultMemberTypes as $type) {
                DB::table('member_types')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'name' => $type['name']],
                    [...$type, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
            foreach ($defaultFunctions as $function) {
                DB::table('function_definitions')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'name' => $function['name']],
                    [...$function, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
            if ($roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }

            $ordinaryId = DB::table('member_types')->where('tenant_id', $tenantId)->where('name', 'Ordentliches Mitglied')->value('id');
            if ($ordinaryId) {
                DB::table('memberships')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('member_type_id')
                    ->where('membership_type', 'Ordentliches Mitglied')
                    ->update(['member_type_id' => $ordinaryId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'members.master_data', 'members.households', 'members.functions', 'members.import_export',
        ])->delete();

        Schema::dropIfExists('function_assignments');
        Schema::dropIfExists('function_definitions');
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');

        if (Schema::hasColumn('memberships', 'member_type_id')) {
            Schema::table('memberships', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('member_type_id');
            });
        }

        Schema::dropIfExists('member_types');
    }
};
