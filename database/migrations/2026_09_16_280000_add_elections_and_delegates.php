<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('elections')) {
            Schema::create('elections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('organization_unit_id')->nullable();
                $table->foreignId('governance_meeting_id')->nullable();
                $table->string('title', 220);
                $table->date('election_date');
                $table->string('status', 24)->default('draft');
                $table->string('voter_basis', 24)->default('members');
                $table->boolean('allow_proxies')->default(false);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id', 'el_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organization_unit_id', 'el_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('governance_meeting_id', 'el_meeting_fk')->references('id')->on('governance_meetings')->nullOnDelete();
                $table->foreign('created_by', 'el_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('finalized_by', 'el_finalizer_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'election_date'], 'el_tenant_date_idx');
                $table->index(['tenant_id', 'status'], 'el_tenant_status_idx');
            });
        }

        if (! Schema::hasTable('delegate_mandates')) {
            Schema::create('delegate_mandates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('member_id');
                $table->foreignId('represented_organization_unit_id');
                $table->foreignId('receiving_organization_unit_id')->nullable();
                $table->string('mandate_number', 80);
                $table->decimal('voting_weight', 10, 3)->default(1);
                $table->string('status', 24)->default('active');
                $table->date('starts_at');
                $table->date('ends_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'del_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('member_id', 'del_member_fk')->references('id')->on('members')->cascadeOnDelete();
                $table->foreign('represented_organization_unit_id', 'del_rep_org_fk')->references('id')->on('organization_units')->cascadeOnDelete();
                $table->foreign('receiving_organization_unit_id', 'del_recv_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->unique(['tenant_id', 'mandate_number'], 'del_tenant_number_uq');
                $table->index(['tenant_id', 'status', 'starts_at'], 'del_tenant_status_start_idx');
                $table->index(['tenant_id', 'member_id'], 'del_tenant_member_idx');
            });
        }

        if (! Schema::hasTable('election_offices')) {
            Schema::create('election_offices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('election_id');
                $table->foreignId('function_definition_id')->nullable();
                $table->string('name', 180);
                $table->unsignedInteger('seats')->default(1);
                $table->string('voting_method', 24)->default('secret');
                $table->string('majority_type', 32)->default('absolute');
                $table->string('majority_basis', 40)->default('valid_votes');
                $table->unsignedInteger('max_rounds')->default(3);
                $table->boolean('allow_abstention')->default(true);
                $table->boolean('sync_function_assignments')->default(false);
                $table->date('term_starts_at')->nullable();
                $table->date('term_ends_at')->nullable();
                $table->unsignedInteger('position')->default(10);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'el_off_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_id', 'el_off_election_fk')->references('id')->on('elections')->cascadeOnDelete();
                $table->foreign('function_definition_id', 'el_off_function_fk')->references('id')->on('function_definitions')->nullOnDelete();
                $table->index(['tenant_id', 'election_id', 'position'], 'el_off_election_pos_idx');
            });
        }

        if (! Schema::hasTable('election_voters')) {
            Schema::create('election_voters', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('election_id');
                $table->foreignId('member_id');
                $table->foreignId('delegate_mandate_id')->nullable();
                $table->string('source', 24)->default('manual');
                $table->decimal('voting_weight', 10, 3)->default(1);
                $table->string('status', 24)->default('eligible');
                $table->timestamp('checked_in_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'el_voter_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_id', 'el_voter_election_fk')->references('id')->on('elections')->cascadeOnDelete();
                $table->foreign('member_id', 'el_voter_member_fk')->references('id')->on('members')->cascadeOnDelete();
                $table->foreign('delegate_mandate_id', 'el_voter_delegate_fk')->references('id')->on('delegate_mandates')->nullOnDelete();
                $table->unique(['election_id', 'member_id'], 'el_voter_election_member_uq');
                $table->index(['tenant_id', 'election_id', 'status'], 'el_voter_status_idx');
            });
        }

        if (! Schema::hasTable('election_proxies')) {
            Schema::create('election_proxies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('election_id');
                $table->foreignId('grantor_member_id');
                $table->foreignId('proxy_member_id');
                $table->decimal('voting_weight', 10, 3)->default(1);
                $table->string('status', 24)->default('active');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'el_proxy_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_id', 'el_proxy_election_fk')->references('id')->on('elections')->cascadeOnDelete();
                $table->foreign('grantor_member_id', 'el_proxy_grantor_fk')->references('id')->on('members')->cascadeOnDelete();
                $table->foreign('proxy_member_id', 'el_proxy_holder_fk')->references('id')->on('members')->cascadeOnDelete();
                $table->unique(['election_id', 'grantor_member_id'], 'el_proxy_grantor_uq');
                $table->index(['tenant_id', 'election_id', 'status'], 'el_proxy_status_idx');
            });
        }

        if (! Schema::hasTable('election_candidates')) {
            Schema::create('election_candidates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('election_office_id');
                $table->foreignId('member_id');
                $table->foreignId('nominated_by_member_id')->nullable();
                $table->string('status', 24)->default('nominated');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->text('statement')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'el_can_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_office_id', 'el_can_office_fk')->references('id')->on('election_offices')->cascadeOnDelete();
                $table->foreign('member_id', 'el_can_member_fk')->references('id')->on('members')->cascadeOnDelete();
                $table->foreign('nominated_by_member_id', 'el_can_nominator_fk')->references('id')->on('members')->nullOnDelete();
                $table->unique(['election_office_id', 'member_id'], 'el_can_office_member_uq');
                $table->index(['tenant_id', 'election_office_id', 'status'], 'el_can_status_idx');
            });
        }

        if (! Schema::hasTable('election_rounds')) {
            Schema::create('election_rounds', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('election_office_id');
                $table->unsignedInteger('round_number');
                $table->string('status', 24)->default('planned');
                $table->decimal('eligible_weight', 12, 3)->default(0);
                $table->decimal('cast_weight', 12, 3)->default(0);
                $table->decimal('invalid_weight', 12, 3)->default(0);
                $table->decimal('abstain_weight', 12, 3)->default(0);
                $table->string('result_status', 24)->default('pending');
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('finalized_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'el_round_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_office_id', 'el_round_office_fk')->references('id')->on('election_offices')->cascadeOnDelete();
                $table->foreign('finalized_by', 'el_round_finalizer_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['election_office_id', 'round_number'], 'el_round_office_number_uq');
                $table->index(['tenant_id', 'status'], 'el_round_status_idx');
            });
        }

        if (! Schema::hasTable('election_candidate_results')) {
            Schema::create('election_candidate_results', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('election_round_id');
                $table->foreignId('election_candidate_id');
                $table->decimal('votes', 12, 3)->default(0);
                $table->unsignedInteger('rank')->nullable();
                $table->boolean('is_elected')->default(false);
                $table->timestamps();

                $table->foreign('tenant_id', 'el_res_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_round_id', 'el_res_round_fk')->references('id')->on('election_rounds')->cascadeOnDelete();
                $table->foreign('election_candidate_id', 'el_res_candidate_fk')->references('id')->on('election_candidates')->cascadeOnDelete();
                $table->unique(['election_round_id', 'election_candidate_id'], 'el_res_round_candidate_uq');
                $table->index(['tenant_id', 'election_round_id'], 'el_res_round_idx');
            });
        }

        if (! Schema::hasTable('election_protocols')) {
            Schema::create('election_protocols', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id');
                $table->foreignId('election_id');
                $table->unsignedInteger('version')->default(1);
                $table->json('snapshot');
                $table->string('disk', 32)->default('local');
                $table->string('path', 500);
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('generated_by')->nullable();
                $table->timestamp('generated_at');
                $table->timestamps();

                $table->foreign('tenant_id', 'el_proto_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('election_id', 'el_proto_election_fk')->references('id')->on('elections')->cascadeOnDelete();
                $table->foreign('generated_by', 'el_proto_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['election_id', 'version'], 'el_proto_election_version_uq');
                $table->index(['tenant_id', 'generated_at'], 'el_proto_tenant_time_idx');
            });
        }

        $permissions = [
            ['key' => 'elections.view', 'module' => 'elections', 'action' => 'view', 'name' => 'Wahlen ansehen', 'description' => 'Wahlen, Ämter, Kandidaturen, Ergebnisse und Protokolle ansehen.'],
            ['key' => 'elections.manage', 'module' => 'elections', 'action' => 'manage', 'name' => 'Wahlen verwalten', 'description' => 'Wahlen, Wahlberechtigte, Ämter, Kandidaturen und Vollmachten verwalten.'],
            ['key' => 'elections.conduct', 'module' => 'elections', 'action' => 'conduct', 'name' => 'Wahlen durchführen', 'description' => 'Wahlgänge öffnen, Ergebnisse erfassen und Wahlgänge abschließen.'],
            ['key' => 'elections.finalize', 'module' => 'elections', 'action' => 'finalize', 'name' => 'Wahlen feststellen', 'description' => 'Wahlen endgültig feststellen, Amtsübernahmen auslösen und Wahlprotokolle erzeugen.'],
            ['key' => 'delegates.manage', 'module' => 'delegates', 'action' => 'manage', 'name' => 'Delegiertenmandate verwalten', 'description' => 'Delegiertenmandate, Stimmgewichte und Gültigkeitszeiträume verwalten.'],
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
        DB::table('permissions')->whereIn('key', [
            'elections.view', 'elections.manage', 'elections.conduct', 'elections.finalize', 'delegates.manage',
        ])->delete();

        Schema::dropIfExists('election_protocols');
        Schema::dropIfExists('election_candidate_results');
        Schema::dropIfExists('election_rounds');
        Schema::dropIfExists('election_candidates');
        Schema::dropIfExists('election_proxies');
        Schema::dropIfExists('election_voters');
        Schema::dropIfExists('election_offices');
        Schema::dropIfExists('delegate_mandates');
        Schema::dropIfExists('elections');
    }
};
