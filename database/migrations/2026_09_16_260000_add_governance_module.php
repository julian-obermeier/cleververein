<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('governance_committees')) {
            Schema::create('governance_committees', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('organization_unit_id')->nullable();
                $table->string('name', 180);
                $table->string('short_name', 80)->nullable();
                $table->string('committee_type', 40)->default('committee');
                $table->text('description')->nullable();
                $table->string('status', 24)->default('active');
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id', 'gov_comm_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organization_unit_id', 'gov_comm_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->index(['tenant_id', 'status'], 'gov_comm_tenant_status_idx');
                $table->index(['tenant_id', 'organization_unit_id'], 'gov_comm_tenant_org_idx');
            });
        }

        if (! Schema::hasTable('governance_committee_members')) {
            Schema::create('governance_committee_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('committee_id');
                $table->foreignId('member_id');
                $table->string('role_name', 120)->nullable();
                $table->boolean('is_chair')->default(false);
                $table->boolean('has_voting_right')->default(true);
                $table->string('status', 24)->default('active');
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'gov_cm_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('committee_id', 'gov_cm_committee_fk')->references('id')->on('governance_committees')->cascadeOnDelete();
                $table->foreign('member_id', 'gov_cm_member_fk')->references('id')->on('members')->cascadeOnDelete();
                $table->index(['tenant_id', 'committee_id', 'status'], 'gov_cm_tenant_comm_status_idx');
                $table->index(['tenant_id', 'member_id'], 'gov_cm_tenant_member_idx');
            });
        }

        if (! Schema::hasTable('governance_meetings')) {
            Schema::create('governance_meetings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('committee_id')->nullable();
                $table->foreignId('organization_unit_id')->nullable();
                $table->string('title', 220);
                $table->string('meeting_type', 40)->default('meeting');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('location', 220)->nullable();
                $table->string('online_url', 500)->nullable();
                $table->string('status', 24)->default('planned');
                $table->unsignedInteger('quorum_required')->nullable();
                $table->boolean('quorum_met')->nullable();
                $table->string('minutes_status', 24)->default('draft');
                $table->longText('minutes_text')->nullable();
                $table->timestamp('minutes_approved_at')->nullable();
                $table->foreignId('minutes_approved_by')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id', 'gov_meet_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('committee_id', 'gov_meet_committee_fk')->references('id')->on('governance_committees')->nullOnDelete();
                $table->foreign('organization_unit_id', 'gov_meet_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('minutes_approved_by', 'gov_meet_approved_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by', 'gov_meet_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'starts_at'], 'gov_meet_tenant_start_idx');
                $table->index(['tenant_id', 'status'], 'gov_meet_tenant_status_idx');
            });
        }

        if (! Schema::hasTable('governance_meeting_participants')) {
            Schema::create('governance_meeting_participants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('meeting_id');
                $table->foreignId('member_id')->nullable();
                $table->string('external_name', 180)->nullable();
                $table->string('participant_role', 40)->default('participant');
                $table->string('attendance_status', 24)->default('invited');
                $table->boolean('has_voting_right')->default(false);
                $table->timestamps();

                $table->foreign('tenant_id', 'gov_mp_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('meeting_id', 'gov_mp_meeting_fk')->references('id')->on('governance_meetings')->cascadeOnDelete();
                $table->foreign('member_id', 'gov_mp_member_fk')->references('id')->on('members')->nullOnDelete();
                $table->index(['tenant_id', 'meeting_id'], 'gov_mp_tenant_meet_idx');
                $table->unique(['meeting_id', 'member_id'], 'gov_mp_meet_member_uq');
            });
        }

        if (! Schema::hasTable('governance_agenda_items')) {
            Schema::create('governance_agenda_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('meeting_id');
                $table->foreignId('parent_id')->nullable();
                $table->unsignedInteger('position')->default(10);
                $table->string('item_number', 30)->nullable();
                $table->string('title', 220);
                $table->text('description')->nullable();
                $table->string('item_type', 32)->default('discussion');
                $table->unsignedInteger('planned_minutes')->nullable();
                $table->string('status', 24)->default('open');
                $table->timestamps();

                $table->foreign('tenant_id', 'gov_ag_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('meeting_id', 'gov_ag_meeting_fk')->references('id')->on('governance_meetings')->cascadeOnDelete();
                $table->foreign('parent_id', 'gov_ag_parent_fk')->references('id')->on('governance_agenda_items')->nullOnDelete();
                $table->index(['tenant_id', 'meeting_id', 'position'], 'gov_ag_tenant_meet_pos_idx');
            });
        }

        if (! Schema::hasTable('governance_motions')) {
            Schema::create('governance_motions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('meeting_id');
                $table->foreignId('agenda_item_id')->nullable();
                $table->foreignId('organization_unit_id')->nullable();
                $table->foreignId('proposer_member_id')->nullable();
                $table->string('motion_number', 80);
                $table->string('title', 220);
                $table->longText('motion_text');
                $table->text('rationale')->nullable();
                $table->string('proposer_name', 180)->nullable();
                $table->string('status', 24)->default('submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'gov_mot_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('meeting_id', 'gov_mot_meeting_fk')->references('id')->on('governance_meetings')->cascadeOnDelete();
                $table->foreign('agenda_item_id', 'gov_mot_agenda_fk')->references('id')->on('governance_agenda_items')->nullOnDelete();
                $table->foreign('organization_unit_id', 'gov_mot_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('proposer_member_id', 'gov_mot_member_fk')->references('id')->on('members')->nullOnDelete();
                $table->unique(['tenant_id', 'motion_number'], 'gov_mot_tenant_number_uq');
                $table->index(['tenant_id', 'meeting_id', 'status'], 'gov_mot_meet_status_idx');
            });
        }

        if (! Schema::hasTable('governance_resolutions')) {
            Schema::create('governance_resolutions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('meeting_id');
                $table->foreignId('agenda_item_id')->nullable();
                $table->foreignId('motion_id')->nullable();
                $table->foreignId('organization_unit_id')->nullable();
                $table->string('resolution_number', 80);
                $table->string('title', 220);
                $table->longText('resolution_text');
                $table->string('decision_status', 24)->default('passed');
                $table->string('voting_method', 32)->default('open');
                $table->unsignedInteger('votes_yes')->default(0);
                $table->unsignedInteger('votes_no')->default(0);
                $table->unsignedInteger('votes_abstain')->default(0);
                $table->unsignedInteger('votes_invalid')->default(0);
                $table->date('effective_date')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'gov_res_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('meeting_id', 'gov_res_meeting_fk')->references('id')->on('governance_meetings')->cascadeOnDelete();
                $table->foreign('agenda_item_id', 'gov_res_agenda_fk')->references('id')->on('governance_agenda_items')->nullOnDelete();
                $table->foreign('motion_id', 'gov_res_motion_fk')->references('id')->on('governance_motions')->nullOnDelete();
                $table->foreign('organization_unit_id', 'gov_res_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('created_by', 'gov_res_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['tenant_id', 'resolution_number'], 'gov_res_tenant_number_uq');
                $table->index(['tenant_id', 'meeting_id'], 'gov_res_tenant_meet_idx');
            });
        }

        if (! Schema::hasTable('governance_tasks')) {
            Schema::create('governance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('meeting_id')->nullable();
                $table->foreignId('resolution_id')->nullable();
                $table->foreignId('organization_unit_id')->nullable();
                $table->foreignId('assigned_member_id')->nullable();
                $table->string('title', 220);
                $table->text('description')->nullable();
                $table->string('priority', 16)->default('normal');
                $table->string('status', 24)->default('open');
                $table->date('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'gov_task_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('meeting_id', 'gov_task_meeting_fk')->references('id')->on('governance_meetings')->nullOnDelete();
                $table->foreign('resolution_id', 'gov_task_res_fk')->references('id')->on('governance_resolutions')->nullOnDelete();
                $table->foreign('organization_unit_id', 'gov_task_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('assigned_member_id', 'gov_task_member_fk')->references('id')->on('members')->nullOnDelete();
                $table->foreign('created_by', 'gov_task_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'status', 'due_at'], 'gov_task_status_due_idx');
                $table->index(['tenant_id', 'assigned_member_id'], 'gov_task_member_idx');
            });
        }

        $permissions = [
            ['key' => 'governance.view', 'module' => 'governance', 'action' => 'view', 'name' => 'Gremien und Sitzungen ansehen', 'description' => 'Gremien, Sitzungen, Tagesordnungen, Anträge, Beschlüsse und Aufgaben ansehen.'],
            ['key' => 'governance.manage', 'module' => 'governance', 'action' => 'manage', 'name' => 'Gremien und Sitzungen verwalten', 'description' => 'Gremien, Mitglieder, Sitzungen, Tagesordnung und Teilnahmen verwalten.'],
            ['key' => 'governance.decisions', 'module' => 'governance', 'action' => 'decisions', 'name' => 'Anträge und Beschlüsse verwalten', 'description' => 'Anträge, Abstimmungsergebnisse, Beschlüsse und daraus entstehende Aufgaben verwalten.'],
            ['key' => 'governance.minutes', 'module' => 'governance', 'action' => 'minutes', 'name' => 'Protokolle verwalten', 'description' => 'Sitzungsprotokolle bearbeiten, zur Prüfung stellen und freigeben.'],
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
        DB::table('permissions')->whereIn('key', ['governance.view', 'governance.manage', 'governance.decisions', 'governance.minutes'])->delete();
        Schema::dropIfExists('governance_tasks');
        Schema::dropIfExists('governance_resolutions');
        Schema::dropIfExists('governance_motions');
        Schema::dropIfExists('governance_agenda_items');
        Schema::dropIfExists('governance_meeting_participants');
        Schema::dropIfExists('governance_meetings');
        Schema::dropIfExists('governance_committee_members');
        Schema::dropIfExists('governance_committees');
    }
};
