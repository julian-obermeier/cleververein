<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_tags')) {
            Schema::create('member_tags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('color', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'name'], 'member_tags_tenant_name_uq');
                $table->index(['tenant_id', 'is_active'], 'member_tags_tenant_active_idx');
            });
        }

        if (! Schema::hasTable('member_tag_assignments')) {
            Schema::create('member_tag_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_tag_id')->constrained('member_tags')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'member_id', 'member_tag_id'], 'member_tag_assignment_uq');
                $table->index(['tenant_id', 'member_tag_id'], 'member_tag_assignment_tag_idx');
            });
        }

        if (! Schema::hasTable('member_segments')) {
            Schema::create('member_segments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->json('criteria');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'name'], 'member_segments_tenant_name_uq');
                $table->index(['tenant_id', 'is_active'], 'member_segments_tenant_active_idx');
            });
        }

        if (! Schema::hasTable('member_documents')) {
            Schema::create('member_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 180);
                $table->string('category', 80)->nullable();
                $table->string('original_name', 255);
                $table->string('disk', 30)->default('local');
                $table->string('path', 500);
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->date('document_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'member_id', 'document_date'], 'member_docs_member_date_idx');
            });
        }

        if (! Schema::hasTable('member_communications')) {
            Schema::create('member_communications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('channel', 30);
                $table->string('direction', 20)->default('outbound');
                $table->string('subject', 180)->nullable();
                $table->text('body');
                $table->string('outcome', 120)->nullable();
                $table->dateTime('occurred_at');
                $table->timestamps();

                $table->index(['tenant_id', 'member_id', 'occurred_at'], 'member_comms_member_time_idx');
            });
        }

        $permissions = [
            ['key' => 'members.tags', 'module' => 'members', 'action' => 'tags', 'name' => 'Mitglieder-Tags verwalten', 'description' => 'Tags anlegen und Mitgliedern zuweisen.'],
            ['key' => 'members.segments', 'module' => 'members', 'action' => 'segments', 'name' => 'Mitglieder-Segmente verwalten', 'description' => 'Dynamische Mitgliedersegmente anhand gespeicherter Filter verwalten.'],
            ['key' => 'members.documents', 'module' => 'members', 'action' => 'documents', 'name' => 'Mitgliederdokumente verwalten', 'description' => 'Private Dokumente zu Mitgliedern hochladen, herunterladen und archivieren.'],
            ['key' => 'members.communications', 'module' => 'members', 'action' => 'communications', 'name' => 'Kommunikationshistorie verwalten', 'description' => 'Kontakte und Kommunikation zu Mitgliedern dokumentieren.'],
            ['key' => 'members.history', 'module' => 'members', 'action' => 'history', 'name' => 'Mitgliederverlauf einsehen', 'description' => 'Änderungs- und Aktivitätsverlauf eines Mitglieds einsehen.'],
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
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'members.tags', 'members.segments', 'members.documents', 'members.communications', 'members.history',
        ])->delete();

        Schema::dropIfExists('member_communications');
        Schema::dropIfExists('member_documents');
        Schema::dropIfExists('member_segments');
        Schema::dropIfExists('member_tag_assignments');
        Schema::dropIfExists('member_tags');
    }
};
