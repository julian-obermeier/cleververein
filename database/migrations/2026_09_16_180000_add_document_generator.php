<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 150);
                $table->string('category', 80)->nullable();
                $table->text('description')->nullable();
                $table->string('page_size', 20)->default('A4');
                $table->string('orientation', 20)->default('portrait');
                $table->json('layout');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'name'], 'doc_tpl_tenant_name_uq');
                $table->index(['tenant_id', 'is_active'], 'doc_tpl_tenant_active_idx');
            });
        }

        if (! Schema::hasTable('generated_documents')) {
            Schema::create('generated_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 180);
                $table->string('file_name', 255);
                $table->string('disk', 30)->default('local');
                $table->string('path', 500);
                $table->string('mime_type', 120)->default('application/pdf');
                $table->unsignedBigInteger('size')->default(0);
                $table->json('context')->nullable();
                $table->dateTime('generated_at');
                $table->timestamps();

                $table->index(['tenant_id', 'generated_at'], 'gen_docs_tenant_time_idx');
                $table->index(['tenant_id', 'member_id'], 'gen_docs_member_idx');
            });
        }

        $permissions = [
            ['key' => 'documents.view', 'module' => 'documents', 'action' => 'view', 'name' => 'Dokumentvorlagen einsehen', 'description' => 'Dokumentvorlagen und erzeugte Dokumente einsehen.'],
            ['key' => 'documents.manage', 'module' => 'documents', 'action' => 'manage', 'name' => 'Dokumentvorlagen verwalten', 'description' => 'Vorlagen anlegen, bearbeiten, duplizieren und deaktivieren.'],
            ['key' => 'documents.generate', 'module' => 'documents', 'action' => 'generate', 'name' => 'Dokumente erzeugen', 'description' => 'Dokumente aus Vorlagen und Mitgliedsdaten als PDF erzeugen.'],
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
        $permissionIds = DB::table('permissions')->whereIn('key', [
            'documents.view', 'documents.manage', 'documents.generate',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_templates');
    }
};
