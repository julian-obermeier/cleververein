<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_field_definitions')) {
            Schema::create('custom_field_definitions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('entity_type', 50)->default('member');
                $table->string('name', 120);
                $table->string('key', 80);
                $table->string('field_type', 30)->default('text');
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'entity_type', 'key']);
                $table->index(['tenant_id', 'entity_type', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('custom_field_values')) {
            Schema::create('custom_field_values', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('custom_field_definition_id')->constrained()->cascadeOnDelete();
                $table->string('entity_type', 50)->default('member');
                $table->unsignedBigInteger('entity_id');
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'custom_field_definition_id', 'entity_type', 'entity_id'], 'custom_field_value_unique');
                $table->index(['tenant_id', 'entity_type', 'entity_id']);
            });
        }

        $permissions = [
            ['key' => 'members.bulk', 'module' => 'members', 'action' => 'bulk', 'name' => 'Mitglieder gesammelt bearbeiten', 'description' => 'Mehrere Mitglieder in einer Aktion bearbeiten oder archivieren.'],
            ['key' => 'members.custom_fields', 'module' => 'members', 'action' => 'custom_fields', 'name' => 'Benutzerdefinierte Mitgliederfelder verwalten', 'description' => 'Zusätzliche Mitgliedsfelder definieren und verwenden.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [...$permission, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', ['members.bulk', 'members.custom_fields'])->delete();
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
