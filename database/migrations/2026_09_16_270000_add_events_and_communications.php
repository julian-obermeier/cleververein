<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_series')) {
            Schema::create('event_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('organization_unit_id')->nullable();
                $table->uuid('public_id')->unique();
                $table->string('title', 220);
                $table->string('event_type', 40)->default('event');
                $table->text('description')->nullable();
                $table->string('location', 220)->nullable();
                $table->string('online_url', 500)->nullable();
                $table->string('recurrence_type', 20)->default('none');
                $table->unsignedSmallInteger('recurrence_interval')->default(1);
                $table->unsignedSmallInteger('recurrence_count')->nullable();
                $table->date('recurrence_until')->nullable();
                $table->boolean('registration_enabled')->default(true);
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('waitlist_enabled')->default(true);
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'evt_series_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organization_unit_id', 'evt_series_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('created_by', 'evt_series_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'status'], 'evt_series_tenant_status_idx');
            });
        }

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('event_series_id')->nullable();
                $table->foreignId('organization_unit_id')->nullable();
                $table->uuid('public_id')->unique();
                $table->string('title', 220);
                $table->string('event_type', 40)->default('event');
                $table->text('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('location', 220)->nullable();
                $table->string('online_url', 500)->nullable();
                $table->string('status', 20)->default('scheduled');
                $table->boolean('registration_enabled')->default(true);
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('waitlist_enabled')->default(true);
                $table->dateTime('registration_deadline')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'events_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('event_series_id', 'events_series_fk')->references('id')->on('event_series')->nullOnDelete();
                $table->foreign('organization_unit_id', 'events_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('created_by', 'events_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'starts_at', 'status'], 'events_tenant_date_status_idx');
            });
        }

        if (! Schema::hasTable('event_registrations')) {
            Schema::create('event_registrations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('event_id');
                $table->foreignId('member_id')->nullable();
                $table->uuid('public_id')->unique();
                $table->string('guest_name', 180)->nullable();
                $table->string('guest_email', 255)->nullable();
                $table->string('status', 24)->default('invited');
                $table->string('attendance_status', 24)->default('unknown');
                $table->string('response_token', 64)->unique();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('checked_in_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'evt_reg_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('event_id', 'evt_reg_event_fk')->references('id')->on('events')->cascadeOnDelete();
                $table->foreign('member_id', 'evt_reg_member_fk')->references('id')->on('members')->nullOnDelete();
                $table->unique(['event_id', 'member_id'], 'evt_reg_event_member_uq');
                $table->index(['tenant_id', 'event_id', 'status'], 'evt_reg_event_status_idx');
            });
        }

        if (! Schema::hasTable('communication_templates')) {
            Schema::create('communication_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->uuid('public_id')->unique();
                $table->string('name', 150);
                $table->string('subject', 255);
                $table->longText('body');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'comm_tpl_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('created_by', 'comm_tpl_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['tenant_id', 'name'], 'comm_tpl_tenant_name_uq');
            });
        }

        if (! Schema::hasTable('communication_campaigns')) {
            Schema::create('communication_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('event_id')->nullable();
                $table->foreignId('template_id')->nullable();
                $table->foreignId('member_segment_id')->nullable();
                $table->foreignId('organization_unit_id')->nullable();
                $table->uuid('public_id')->unique();
                $table->string('name', 180);
                $table->string('channel', 24)->default('email');
                $table->string('target_type', 32)->default('all_active');
                $table->string('subject', 255);
                $table->longText('body');
                $table->string('status', 24)->default('draft');
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->timestamp('prepared_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'comm_campaign_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('event_id', 'comm_campaign_event_fk')->references('id')->on('events')->nullOnDelete();
                $table->foreign('template_id', 'comm_campaign_tpl_fk')->references('id')->on('communication_templates')->nullOnDelete();
                $table->foreign('member_segment_id', 'comm_campaign_segment_fk')->references('id')->on('member_segments')->nullOnDelete();
                $table->foreign('organization_unit_id', 'comm_campaign_org_fk')->references('id')->on('organization_units')->nullOnDelete();
                $table->foreign('created_by', 'comm_campaign_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'status', 'created_at'], 'comm_campaign_status_idx');
            });
        }

        if (! Schema::hasTable('communication_recipients')) {
            Schema::create('communication_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('campaign_id');
                $table->foreignId('member_id')->nullable();
                $table->foreignId('event_registration_id')->nullable();
                $table->string('recipient_name', 180);
                $table->string('recipient_email', 255);
                $table->string('status', 24)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'comm_rec_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('campaign_id', 'comm_rec_campaign_fk')->references('id')->on('communication_campaigns')->cascadeOnDelete();
                $table->foreign('member_id', 'comm_rec_member_fk')->references('id')->on('members')->nullOnDelete();
                $table->foreign('event_registration_id', 'comm_rec_event_reg_fk')->references('id')->on('event_registrations')->nullOnDelete();
                $table->unique(['campaign_id', 'recipient_email'], 'comm_rec_campaign_email_uq');
                $table->index(['tenant_id', 'campaign_id', 'status'], 'comm_rec_status_idx');
            });
        }

        foreach ([
            ['key' => 'events.view', 'module' => 'events', 'action' => 'view', 'name' => 'Veranstaltungen ansehen'],
            ['key' => 'events.manage', 'module' => 'events', 'action' => 'manage', 'name' => 'Veranstaltungen verwalten'],
            ['key' => 'events.registrations', 'module' => 'events', 'action' => 'registrations', 'name' => 'Anmeldungen verwalten'],
            ['key' => 'events.attendance', 'module' => 'events', 'action' => 'attendance', 'name' => 'Anwesenheit erfassen'],
            ['key' => 'communications.view', 'module' => 'communications', 'action' => 'view', 'name' => 'Kommunikation ansehen'],
            ['key' => 'communications.manage', 'module' => 'communications', 'action' => 'manage', 'name' => 'Kommunikation verwalten'],
            ['key' => 'communications.send', 'module' => 'communications', 'action' => 'send', 'name' => 'Kommunikation versenden'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [...$permission, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('key', [
            'events.view', 'events.manage', 'events.registrations', 'events.attendance',
            'communications.view', 'communications.manage', 'communications.send',
        ])->pluck('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
            if ($roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'events.view', 'events.manage', 'events.registrations', 'events.attendance',
            'communications.view', 'communications.manage', 'communications.send',
        ])->delete();
        Schema::dropIfExists('communication_recipients');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_series');
    }
};
