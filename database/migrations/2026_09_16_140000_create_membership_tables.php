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
