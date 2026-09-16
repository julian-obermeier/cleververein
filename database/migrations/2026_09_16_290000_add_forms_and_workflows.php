<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('form_definitions')) {
            Schema::create('form_definitions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('organization_unit_id')->nullable();
                $table->string('name', 180);
                $table->string('slug', 120);
                $table->text('description')->nullable();
                $table->string('form_type', 24)->default('internal');
                $table->string('status', 24)->default('draft');
                $table->string('public_token', 80)->nullable()->unique();
                $table->boolean('allow_anonymous')->default(false);
                $table->boolean('require_member')->default(false);
                $table->string('submission_prefix', 12)->default('FM');
                $table->text('success_message')->nullable();
                $table->json('settings')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id', 'form_def_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organization_unit_id', 'form_def_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('created_by', 'form_def_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'form_def_updater_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['tenant_id', 'slug'], 'form_def_tenant_slug_uq');
                $table->index(['tenant_id', 'status', 'form_type'], 'form_def_status_type_idx');
            });
        }

        if (! Schema::hasTable('form_fields')) {
            Schema::create('form_fields', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('form_definition_id');
                $table->string('field_key', 100);
                $table->string('label', 180);
                $table->string('field_type', 32);
                $table->unsignedInteger('position')->default(10);
                $table->boolean('is_required')->default(false);
                $table->string('placeholder', 255)->nullable();
                $table->text('help_text')->nullable();
                $table->json('options')->nullable();
                $table->json('validation')->nullable();
                $table->json('condition')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_field_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_definition_id', 'form_field_form_fk')->references('id')->on('form_definitions')->cascadeOnDelete();
                $table->unique(['form_definition_id', 'field_key'], 'form_field_form_key_uq');
                $table->index(['tenant_id', 'form_definition_id', 'position'], 'form_field_order_idx');
            });
        }

        if (! Schema::hasTable('form_workflows')) {
            Schema::create('form_workflows', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('form_definition_id');
                $table->string('name', 180);
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_wf_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_definition_id', 'form_wf_form_fk')->references('id')->on('form_definitions')->cascadeOnDelete();
                $table->index(['tenant_id', 'form_definition_id', 'is_active'], 'form_wf_active_idx');
            });
        }

        if (! Schema::hasTable('form_workflow_steps')) {
            Schema::create('form_workflow_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('form_workflow_id');
                $table->unsignedInteger('position')->default(10);
                $table->string('name', 180);
                $table->string('step_type', 24)->default('review');
                $table->foreignId('assigned_user_id')->nullable();
                $table->foreignId('assigned_role_id')->nullable();
                $table->unsignedSmallInteger('due_days')->nullable();
                $table->boolean('decision_required')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_wfs_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_workflow_id', 'form_wfs_wf_fk')->references('id')->on('form_workflows')->cascadeOnDelete();
                $table->foreign('assigned_user_id', 'form_wfs_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('assigned_role_id', 'form_wfs_role_fk')->references('id')->on('roles')->nullOnDelete();
                $table->index(['tenant_id', 'form_workflow_id', 'position'], 'form_wfs_order_idx');
            });
        }

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('form_definition_id');
                $table->foreignId('member_id')->nullable();
                $table->foreignId('submitted_by_user_id')->nullable();
                $table->foreignId('current_step_id')->nullable();
                $table->string('reference_number', 50);
                $table->string('submitter_name', 180)->nullable();
                $table->string('submitter_email', 255)->nullable();
                $table->string('status', 24)->default('submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_sub_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_definition_id', 'form_sub_form_fk')->references('id')->on('form_definitions')->restrictOnDelete();
                $table->foreign('member_id', 'form_sub_member_fk')->references('id')->on('members')->nullOnDelete();
                $table->foreign('submitted_by_user_id', 'form_sub_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['tenant_id', 'reference_number'], 'form_sub_ref_uq');
                $table->index(['tenant_id', 'status', 'submitted_at'], 'form_sub_status_idx');
                $table->index(['tenant_id', 'form_definition_id'], 'form_sub_form_idx');
            });
        }

        if (! Schema::hasTable('form_answers')) {
            Schema::create('form_answers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('form_submission_id');
                $table->foreignId('form_field_id')->nullable();
                $table->string('field_key', 100);
                $table->longText('value_text')->nullable();
                $table->json('value_json')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_ans_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_submission_id', 'form_ans_sub_fk')->references('id')->on('form_submissions')->cascadeOnDelete();
                $table->foreign('form_field_id', 'form_ans_field_fk')->references('id')->on('form_fields')->nullOnDelete();
                $table->unique(['form_submission_id', 'field_key'], 'form_ans_sub_key_uq');
            });
        }

        if (! Schema::hasTable('form_attachments')) {
            Schema::create('form_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('form_submission_id');
                $table->foreignId('form_field_id')->nullable();
                $table->string('field_key', 100)->nullable();
                $table->string('original_name', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('disk', 32)->default('local');
                $table->string('path', 500);
                $table->foreignId('created_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_att_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_submission_id', 'form_att_sub_fk')->references('id')->on('form_submissions')->cascadeOnDelete();
                $table->foreign('form_field_id', 'form_att_field_fk')->references('id')->on('form_fields')->nullOnDelete();
                $table->foreign('created_by_user_id', 'form_att_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'form_submission_id'], 'form_att_sub_idx');
            });
        }

        if (! Schema::hasTable('form_submission_steps')) {
            Schema::create('form_submission_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('form_submission_id');
                $table->foreignId('form_workflow_step_id');
                $table->string('status', 24)->default('pending');
                $table->foreignId('assigned_user_id')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->foreignId('decision_by_user_id')->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'form_ss_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_submission_id', 'form_ss_sub_fk')->references('id')->on('form_submissions')->cascadeOnDelete();
                $table->foreign('form_workflow_step_id', 'form_ss_step_fk')->references('id')->on('form_workflow_steps')->restrictOnDelete();
                $table->foreign('assigned_user_id', 'form_ss_assignee_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('decision_by_user_id', 'form_ss_decider_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['form_submission_id', 'form_workflow_step_id'], 'form_ss_sub_step_uq');
                $table->index(['tenant_id', 'status', 'assigned_user_id'], 'form_ss_status_user_idx');
            });
        }

        if (! Schema::hasTable('form_submission_events')) {
            Schema::create('form_submission_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('form_submission_id');
                $table->string('event_type', 80);
                $table->foreignId('user_id')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->foreign('tenant_id', 'form_evt_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('form_submission_id', 'form_evt_sub_fk')->references('id')->on('form_submissions')->cascadeOnDelete();
                $table->foreign('user_id', 'form_evt_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'form_submission_id', 'occurred_at'], 'form_evt_sub_time_idx');
            });
        }

        if (! Schema::hasTable('form_sequences')) {
            Schema::create('form_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->string('sequence_key', 80);
                $table->unsignedSmallInteger('year');
                $table->unsignedBigInteger('next_value')->default(1);
                $table->timestamps();

                $table->foreign('tenant_id', 'form_seq_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unique(['tenant_id', 'sequence_key', 'year'], 'form_seq_key_year_uq');
            });
        }

        if (Schema::hasTable('form_submissions') && Schema::hasColumn('form_submissions', 'current_step_id')) {
            Schema::table('form_submissions', function (Blueprint $table): void {
                $table->foreign('current_step_id', 'form_sub_current_step_fk')->references('id')->on('form_submission_steps')->nullOnDelete();
            });
        }

        $permissions = [
            ['key' => 'forms.view', 'module' => 'forms', 'action' => 'view', 'name' => 'Formulare ansehen', 'description' => 'Formulare, Felder und veröffentlichte Formulare ansehen.'],
            ['key' => 'forms.manage', 'module' => 'forms', 'action' => 'manage', 'name' => 'Formulare verwalten', 'description' => 'Formulare, Felder, Veröffentlichungen und Einstellungen verwalten.'],
            ['key' => 'forms.workflows', 'module' => 'forms', 'action' => 'workflows', 'name' => 'Formular-Workflows verwalten', 'description' => 'Bearbeitungs- und Genehmigungsworkflows für Formulare verwalten.'],
            ['key' => 'forms.submissions', 'module' => 'forms', 'action' => 'submissions', 'name' => 'Einreichungen ansehen', 'description' => 'Formulareinreichungen, Antworten und Anlagen ansehen.'],
            ['key' => 'forms.process', 'module' => 'forms', 'action' => 'process', 'name' => 'Einreichungen bearbeiten', 'description' => 'Workflow-Schritte bearbeiten, genehmigen, ablehnen und abschließen.'],
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
        DB::table('permissions')->whereIn('key', ['forms.view', 'forms.manage', 'forms.workflows', 'forms.submissions', 'forms.process'])->delete();
        Schema::dropIfExists('form_sequences');
        Schema::dropIfExists('form_submission_events');
        Schema::dropIfExists('form_submission_steps');
        Schema::dropIfExists('form_attachments');
        Schema::dropIfExists('form_answers');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_workflow_steps');
        Schema::dropIfExists('form_workflows');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_definitions');
    }
};
