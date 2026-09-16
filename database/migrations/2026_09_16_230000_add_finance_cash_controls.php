<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_receipts')) {
            Schema::create('finance_receipts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_entry_id')->constrained('finance_entries')->cascadeOnDelete();
                $table->string('original_name', 255);
                $table->string('disk', 40)->default('local');
                $table->string('path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->date('document_date')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason', 500)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'finance_entry_id', 'status'], 'finance_receipts_entry_idx');
            });
        }

        if (! Schema::hasTable('finance_period_locks')) {
            Schema::create('finance_period_locks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('reason', 500);
                $table->string('status', 20)->default('locked');
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at');
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status', 'period_start', 'period_end'], 'finance_period_locks_idx');
            });
        }

        if (! Schema::hasTable('finance_cash_closings')) {
            Schema::create('finance_cash_closings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_account_id')->constrained('finance_accounts')->cascadeOnDelete();
                $table->date('closing_date');
                $table->decimal('system_balance', 14, 2);
                $table->decimal('counted_balance', 14, 2);
                $table->decimal('difference', 14, 2);
                $table->json('denomination_counts')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('closed');
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at');
                $table->timestamps();
                $table->unique(['tenant_id', 'finance_account_id', 'closing_date'], 'finance_cash_closing_uq');
                $table->index(['tenant_id', 'finance_account_id', 'status'], 'finance_cash_closing_idx');
            });
        }

        if (! Schema::hasTable('finance_cash_audits')) {
            Schema::create('finance_cash_audits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('result', 20);
                $table->unsignedInteger('entry_count')->default(0);
                $table->decimal('expected_balance', 14, 2)->nullable();
                $table->decimal('counted_balance', 14, 2)->nullable();
                $table->decimal('difference', 14, 2)->nullable();
                $table->text('findings')->nullable();
                $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('audited_at');
                $table->timestamps();
                $table->index(['tenant_id', 'period_end', 'result'], 'finance_cash_audits_idx');
            });
        }

        $permissions = [
            ['key' => 'finance.receipts', 'module' => 'finance', 'action' => 'receipts', 'name' => 'Finanzbelege verwalten', 'description' => 'Belege zu Buchungen hochladen, einsehen und ungültig markieren.'],
            ['key' => 'finance.cash', 'module' => 'finance', 'action' => 'cash', 'name' => 'Kassenbuch verwalten', 'description' => 'Kassenkonten, Kassenabschlüsse und Kassenstände verwalten.'],
            ['key' => 'finance.periods', 'module' => 'finance', 'action' => 'periods', 'name' => 'Buchungsperioden sperren', 'description' => 'Buchungszeiträume sperren und kontrolliert wieder öffnen.'],
            ['key' => 'finance.audit', 'module' => 'finance', 'action' => 'audit', 'name' => 'Kassenprüfungen verwalten', 'description' => 'Kassenprüfungen dokumentieren und Ergebnisse festhalten.'],
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
        DB::table('permissions')->whereIn('key', ['finance.receipts', 'finance.cash', 'finance.periods', 'finance.audit'])->delete();
        Schema::dropIfExists('finance_cash_audits');
        Schema::dropIfExists('finance_cash_closings');
        Schema::dropIfExists('finance_period_locks');
        Schema::dropIfExists('finance_receipts');
    }
};
