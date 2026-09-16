<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->primary(['tenant_id', 'user_id']);
        });

        Schema::create('organization_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug');
            $table->string('status')->default('active')->index();
            $table->date('founded_at')->nullable();
            $table->date('dissolved_at')->nullable();
            $table->json('contact_data')->nullable();
            $table->json('registry_data')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::create('organization_closure', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ancestor_id')->constrained('organization_units')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('organization_units')->cascadeOnDelete();
            $table->unsignedInteger('depth');
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['tenant_id', 'descendant_id', 'depth']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('module');
            $table->string('action');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope')->default('organization');
            $table->boolean('include_descendants')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'valid_until']);
        });

        Schema::create('permission_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('allowed')->default(true);
            $table->boolean('include_descendants')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'permission_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->nullableMorphs('subject');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['system_settings', 'audit_logs', 'permission_assignments', 'role_assignments', 'permission_role', 'roles', 'permissions', 'organization_closure', 'organization_units', 'organization_types', 'tenant_user'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
