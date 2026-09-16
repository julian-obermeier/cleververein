<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_accounts')) {
            Schema::create('finance_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 140);
                $table->string('code', 40);
                $table->string('type', 20)->default('bank');
                $table->string('currency', 3)->default('EUR');
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'code'], 'finance_accounts_tenant_code_uq');
                $table->index(['tenant_id', 'type', 'is_active'], 'finance_accounts_type_idx');
            });
        }

        if (! Schema::hasTable('finance_categories')) {
            Schema::create('finance_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 140);
                $table->string('code', 50);
                $table->string('direction', 16);
                $table->decimal('default_tax_rate', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'code'], 'finance_categories_tenant_code_uq');
                $table->index(['tenant_id', 'direction', 'is_active'], 'finance_categories_direction_idx');
            });
        }

        if (! Schema::hasTable('finance_entries')) {
            Schema::create('finance_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('entry_number', 80);
                $table->date('booking_date');
                $table->date('value_date')->nullable();
                $table->string('direction', 16);
                $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
                $table->foreignId('finance_category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();
                $table->foreignId('finance_payment_id')->nullable()->constrained('finance_payments')->nullOnDelete();
                $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
                $table->decimal('net_amount', 14, 2);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('gross_amount', 14, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->string('description', 255);
                $table->string('reference', 180)->nullable();
                $table->string('source_type', 30)->default('manual');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('status', 20)->default('posted');
                $table->foreignId('reversal_of_id')->nullable()->constrained('finance_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'entry_number'], 'finance_entry_number_uq');
                $table->unique(['tenant_id', 'finance_payment_id'], 'finance_entry_payment_uq');
                $table->unique(['tenant_id', 'bank_transaction_id'], 'finance_entry_bank_tx_uq');
                $table->index(['tenant_id', 'booking_date', 'direction'], 'finance_entries_date_direction_idx');
                $table->index(['tenant_id', 'finance_account_id', 'booking_date'], 'finance_entries_account_date_idx');
                $table->index(['tenant_id', 'finance_category_id', 'booking_date'], 'finance_entries_category_date_idx');
            });
        }

        $permissions = [
            ['key' => 'finance.accounting', 'module' => 'finance', 'action' => 'accounting', 'name' => 'Finanzbuchungen verwalten', 'description' => 'Konten, Kategorien und Einnahmen-/Ausgabenbuchungen verwalten.'],
            ['key' => 'finance.reports', 'module' => 'finance', 'action' => 'reports', 'name' => 'Finanzberichte einsehen', 'description' => 'Finanzauswertungen, Journal und Exporte einsehen.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [...$permission, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $accounts = [
                ['name' => 'Bank', 'code' => 'BANK', 'type' => 'bank', 'is_default' => true, 'sort_order' => 10],
                ['name' => 'Kasse', 'code' => 'KASSE', 'type' => 'cash', 'is_default' => false, 'sort_order' => 20],
                ['name' => 'Verrechnung', 'code' => 'VERRECHNUNG', 'type' => 'clearing', 'is_default' => false, 'sort_order' => 90],
            ];
            foreach ($accounts as $account) {
                DB::table('finance_accounts')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'code' => $account['code']],
                    [...$account, 'tenant_id' => $tenantId, 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $categories = [
                ['name' => 'Mitgliedsbeiträge', 'code' => 'MITGLIEDSBEITRAEGE', 'direction' => 'income', 'sort_order' => 10],
                ['name' => 'Spenden', 'code' => 'SPENDEN', 'direction' => 'income', 'sort_order' => 20],
                ['name' => 'Sonstige Einnahmen', 'code' => 'SONSTIGE_EINNAHMEN', 'direction' => 'income', 'sort_order' => 90],
                ['name' => 'Betriebsausgaben', 'code' => 'BETRIEBSAUSGABEN', 'direction' => 'expense', 'sort_order' => 110],
                ['name' => 'Gebühren', 'code' => 'GEBUEHREN', 'direction' => 'expense', 'sort_order' => 120],
                ['name' => 'Sonstige Ausgaben', 'code' => 'SONSTIGE_AUSGABEN', 'direction' => 'expense', 'sort_order' => 190],
            ];
            foreach ($categories as $category) {
                DB::table('finance_categories')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'code' => $category['code']],
                    [...$category, 'tenant_id' => $tenantId, 'default_tax_rate' => 0, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }

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
        DB::table('permissions')->whereIn('key', ['finance.accounting', 'finance.reports'])->delete();
        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('finance_accounts');
    }
};
