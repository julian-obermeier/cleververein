<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contribution_rates')) {
            Schema::create('contribution_rates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 140);
                $table->string('code', 40);
                $table->decimal('amount', 12, 2);
                $table->string('interval', 20)->default('yearly');
                $table->unsignedTinyInteger('billing_month')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'code'], 'contrib_rates_tenant_code_uq');
                $table->index(['tenant_id', 'is_active'], 'contrib_rates_active_idx');
            });
        }

        if (! Schema::hasTable('contribution_rules')) {
            Schema::create('contribution_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contribution_rate_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_type_id')->nullable()->constrained('member_types')->nullOnDelete();
                $table->foreignId('organization_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('min_age')->nullable();
                $table->unsignedTinyInteger('max_age')->nullable();
                $table->unsignedSmallInteger('priority')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['tenant_id', 'is_active', 'priority'], 'contrib_rules_active_idx');
                $table->index(['tenant_id', 'member_type_id'], 'contrib_rules_member_type_idx');
            });
        }

        if (! Schema::hasTable('contribution_overrides')) {
            Schema::create('contribution_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contribution_rate_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2)->nullable();
                $table->boolean('is_exempt')->default(false);
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('reason', 255)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'member_id'], 'contrib_overrides_member_idx');
                $table->index(['tenant_id', 'valid_until'], 'contrib_overrides_valid_idx');
            });
        }

        if (! Schema::hasTable('sepa_mandates')) {
            Schema::create('sepa_mandates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained()->cascadeOnDelete();
                $table->string('mandate_reference', 80);
                $table->string('account_holder', 180);
                $table->text('iban');
                $table->text('bic')->nullable();
                $table->date('signed_at');
                $table->date('revoked_at')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['tenant_id', 'mandate_reference'], 'sepa_tenant_reference_uq');
                $table->index(['tenant_id', 'member_id', 'status'], 'sepa_member_status_idx');
            });
        }

        if (! Schema::hasTable('finance_sequences')) {
            Schema::create('finance_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('sequence_key', 40);
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('next_value')->default(1);
                $table->timestamps();
                $table->unique(['tenant_id', 'sequence_key', 'year'], 'finance_seq_tenant_key_year_uq');
            });
        }

        if (! Schema::hasTable('finance_invoices')) {
            Schema::create('finance_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->string('invoice_number', 80)->nullable();
                $table->string('status', 24)->default('draft');
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('gross_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'invoice_number'], 'finance_invoice_number_uq');
                $table->index(['tenant_id', 'status', 'due_date'], 'finance_invoice_status_due_idx');
                $table->index(['tenant_id', 'member_id'], 'finance_invoice_member_idx');
            });
        }

        if (! Schema::hasTable('finance_invoice_items')) {
            Schema::create('finance_invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_invoice_id')->constrained('finance_invoices')->cascadeOnDelete();
                $table->foreignId('contribution_rate_id')->nullable()->constrained()->nullOnDelete();
                $table->string('description', 255);
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('net_amount', 12, 2);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('gross_amount', 12, 2);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['tenant_id', 'finance_invoice_id'], 'finance_items_invoice_idx');
            });
        }

        if (! Schema::hasTable('finance_payments')) {
            Schema::create('finance_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->date('paid_at');
                $table->string('method', 30)->default('bank_transfer');
                $table->string('reference', 180)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tenant_id', 'paid_at'], 'finance_payments_date_idx');
                $table->index(['tenant_id', 'finance_invoice_id'], 'finance_payments_invoice_idx');
            });
        }

        if (! Schema::hasTable('finance_dunnings')) {
            Schema::create('finance_dunnings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_invoice_id')->constrained('finance_invoices')->cascadeOnDelete();
                $table->unsignedTinyInteger('level')->default(1);
                $table->date('dunned_at');
                $table->decimal('fee', 12, 2)->default(0);
                $table->string('status', 20)->default('sent');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tenant_id', 'finance_invoice_id', 'level'], 'finance_dunnings_invoice_idx');
            });
        }

        $permissions = [
            ['key' => 'finance.view', 'module' => 'finance', 'action' => 'view', 'name' => 'Finanzen einsehen', 'description' => 'Beiträge, Rechnungen, Zahlungen und offene Posten einsehen.'],
            ['key' => 'finance.manage', 'module' => 'finance', 'action' => 'manage', 'name' => 'Finanzen verwalten', 'description' => 'Beitragsregeln, Rechnungen und Zahlungen verwalten.'],
            ['key' => 'finance.sepa', 'module' => 'finance', 'action' => 'sepa', 'name' => 'SEPA-Mandate verwalten', 'description' => 'SEPA-Mandate und Bankverbindungen verwalten.'],
            ['key' => 'finance.dunning', 'module' => 'finance', 'action' => 'dunning', 'name' => 'Mahnwesen verwalten', 'description' => 'Mahnstufen und Mahnvorgänge verwalten.'],
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
        DB::table('permissions')->whereIn('key', ['finance.view', 'finance.manage', 'finance.sepa', 'finance.dunning'])->delete();

        Schema::dropIfExists('finance_dunnings');
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_invoice_items');
        Schema::dropIfExists('finance_invoices');
        Schema::dropIfExists('finance_sequences');
        Schema::dropIfExists('sepa_mandates');
        Schema::dropIfExists('contribution_overrides');
        Schema::dropIfExists('contribution_rules');
        Schema::dropIfExists('contribution_rates');
    }
};
