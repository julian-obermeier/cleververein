<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_settings')) {
            $columns = [
                'donation_receipts_enabled' => fn (Blueprint $table) => $table->boolean('donation_receipts_enabled')->default(false),
                'tax_notice_type' => fn (Blueprint $table) => $table->string('tax_notice_type', 40)->nullable(),
                'tax_office' => fn (Blueprint $table) => $table->string('tax_office', 180)->nullable(),
                'tax_notice_date' => fn (Blueprint $table) => $table->date('tax_notice_date')->nullable(),
                'tax_notice_reference' => fn (Blueprint $table) => $table->string('tax_notice_reference', 180)->nullable(),
                'tax_notice_years' => fn (Blueprint $table) => $table->string('tax_notice_years', 120)->nullable(),
                'tax_exempt_purposes' => fn (Blueprint $table) => $table->text('tax_exempt_purposes')->nullable(),
                'membership_contributions_deductible' => fn (Blueprint $table) => $table->boolean('membership_contributions_deductible')->default(false),
            ];

            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn('finance_settings', $column)) {
                    Schema::table('finance_settings', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }

        if (! Schema::hasTable('finance_payment_adjustments')) {
            Schema::create('finance_payment_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_payment_id')->constrained('finance_payments')->restrictOnDelete();
                $table->foreignId('finance_invoice_id')->constrained('finance_invoices')->restrictOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 24);
                $table->decimal('amount', 12, 2);
                $table->decimal('fee_amount', 12, 2)->default(0);
                $table->date('adjustment_date');
                $table->string('reason', 255);
                $table->string('reference', 180)->nullable();
                $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
                $table->foreignId('reversal_entry_id')->nullable()->constrained('finance_entries')->nullOnDelete();
                $table->foreignId('fee_entry_id')->nullable()->constrained('finance_entries')->nullOnDelete();
                $table->string('status', 20)->default('posted');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tenant_id', 'finance_payment_id', 'status'], 'fin_pay_adj_payment_idx');
                $table->index(['tenant_id', 'adjustment_date', 'type'], 'fin_pay_adj_date_type_idx');
            });
        }

        if (! Schema::hasTable('finance_donations')) {
            Schema::create('finance_donations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('donation_number', 80);
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->string('donor_name', 180);
                $table->string('donor_street', 180)->nullable();
                $table->string('donor_postal_code', 20)->nullable();
                $table->string('donor_city', 120)->nullable();
                $table->string('donor_country', 2)->default('DE');
                $table->string('donor_email', 180)->nullable();
                $table->string('donation_kind', 32)->default('money');
                $table->decimal('amount', 12, 2);
                $table->date('donation_date');
                $table->string('purpose', 500);
                $table->boolean('expense_waiver')->default(false);
                $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
                $table->foreignId('finance_entry_id')->nullable()->constrained('finance_entries')->nullOnDelete();
                $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
                $table->string('reference', 180)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('received');
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['tenant_id', 'donation_number'], 'fin_donation_number_uq');
                $table->unique(['tenant_id', 'finance_entry_id'], 'fin_donation_entry_uq');
                $table->index(['tenant_id', 'donation_date', 'status'], 'fin_donation_date_idx');
                $table->index(['tenant_id', 'member_id'], 'fin_donation_member_idx');
            });
        }

        if (! Schema::hasTable('finance_donation_certificates')) {
            Schema::create('finance_donation_certificates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_donation_id')->constrained('finance_donations')->restrictOnDelete();
                $table->string('certificate_number', 80);
                $table->date('issue_date');
                $table->string('status', 20)->default('issued');
                $table->decimal('amount', 12, 2);
                $table->date('donation_date');
                $table->string('donation_kind', 32);
                $table->string('purpose', 500);
                $table->boolean('expense_waiver')->default(false);
                $table->json('donor_snapshot');
                $table->json('recipient_snapshot');
                $table->json('tax_snapshot');
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at');
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason', 500)->nullable();
                $table->string('pdf_disk', 40)->nullable();
                $table->string('pdf_path')->nullable();
                $table->unsignedBigInteger('pdf_size')->nullable();
                $table->timestamp('pdf_generated_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'certificate_number'], 'fin_donation_cert_number_uq');
                $table->index(['tenant_id', 'finance_donation_id', 'status'], 'fin_donation_cert_status_idx');
            });
        }

        $permissions = [
            ['key' => 'finance.adjustments', 'module' => 'finance', 'action' => 'adjustments', 'name' => 'Rücklastschriften und Erstattungen verwalten', 'description' => 'Zahlungskorrekturen, Rücklastschriften, Erstattungen und Gebühren buchen.'],
            ['key' => 'finance.donations', 'module' => 'finance', 'action' => 'donations', 'name' => 'Spenden verwalten', 'description' => 'Geldzuwendungen und zugehörige Spenderdaten erfassen und auswerten.'],
            ['key' => 'finance.donation_certificates', 'module' => 'finance', 'action' => 'donation_certificates', 'name' => 'Zuwendungsbestätigungen ausstellen', 'description' => 'Zuwendungsbestätigungen nach konfigurierter steuerlicher Grundlage erzeugen und stornieren.'],
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
            'finance.adjustments', 'finance.donations', 'finance.donation_certificates',
        ])->delete();

        Schema::dropIfExists('finance_donation_certificates');
        Schema::dropIfExists('finance_donations');
        Schema::dropIfExists('finance_payment_adjustments');

        if (Schema::hasTable('finance_settings')) {
            $columns = [
                'donation_receipts_enabled', 'tax_notice_type', 'tax_office', 'tax_notice_date',
                'tax_notice_reference', 'tax_notice_years', 'tax_exempt_purposes',
                'membership_contributions_deductible',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('finance_settings', $column)));
            if ($existing !== []) {
                Schema::table('finance_settings', function (Blueprint $table) use ($existing): void {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
