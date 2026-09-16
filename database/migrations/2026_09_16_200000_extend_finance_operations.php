<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_settings')) {
            Schema::create('finance_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('creditor_name', 180)->nullable();
                $table->string('street', 180)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('country', 2)->default('DE');
                $table->string('tax_number', 80)->nullable();
                $table->string('vat_id', 40)->nullable();
                $table->string('creditor_id', 80)->nullable();
                $table->text('iban')->nullable();
                $table->text('bic')->nullable();
                $table->unsignedSmallInteger('payment_terms_days')->default(14);
                $table->text('invoice_footer')->nullable();
                $table->timestamps();
                $table->unique('tenant_id', 'finance_settings_tenant_uq');
            });
        }

        if (Schema::hasTable('contribution_rates') && ! Schema::hasColumn('contribution_rates', 'scope')) {
            Schema::table('contribution_rates', function (Blueprint $table): void {
                $table->string('scope', 20)->default('member')->after('interval');
                $table->index(['tenant_id', 'scope', 'is_active'], 'contrib_rates_scope_idx');
            });
        }

        if (Schema::hasTable('sepa_mandates') && ! Schema::hasColumn('sepa_mandates', 'collection_count')) {
            Schema::table('sepa_mandates', function (Blueprint $table): void {
                $table->unsignedInteger('collection_count')->default(0)->after('status');
                $table->date('last_collected_at')->nullable()->after('collection_count');
            });
        }

        if (Schema::hasTable('finance_invoices') && ! Schema::hasColumn('finance_invoices', 'household_id')) {
            Schema::table('finance_invoices', function (Blueprint $table): void {
                $table->foreignId('household_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
                $table->json('recipient_snapshot')->nullable()->after('notes');
                $table->string('pdf_disk', 40)->nullable()->after('recipient_snapshot');
                $table->string('pdf_path')->nullable()->after('pdf_disk');
                $table->unsignedBigInteger('pdf_size')->nullable()->after('pdf_path');
                $table->timestamp('pdf_generated_at')->nullable()->after('pdf_size');
                $table->index(['tenant_id', 'household_id'], 'finance_invoice_household_idx');
            });
        }

        if (Schema::hasTable('finance_dunnings') && ! Schema::hasColumn('finance_dunnings', 'pdf_disk')) {
            Schema::table('finance_dunnings', function (Blueprint $table): void {
                $table->string('pdf_disk', 40)->nullable();
                $table->string('pdf_path')->nullable();
                $table->unsignedBigInteger('pdf_size')->nullable();
                $table->timestamp('pdf_generated_at')->nullable();
            });
        }

        if (! Schema::hasTable('finance_credit_notes')) {
            Schema::create('finance_credit_notes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
                $table->string('credit_number', 80)->nullable();
                $table->string('status', 20)->default('issued');
                $table->date('credit_date');
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255);
                $table->json('recipient_snapshot')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('pdf_disk', 40)->nullable();
                $table->string('pdf_path')->nullable();
                $table->unsignedBigInteger('pdf_size')->nullable();
                $table->timestamp('pdf_generated_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'credit_number'], 'finance_credit_number_uq');
                $table->index(['tenant_id', 'finance_invoice_id', 'status'], 'finance_credit_invoice_idx');
            });
        }

        if (! Schema::hasTable('sepa_batches')) {
            Schema::create('sepa_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('batch_reference', 80);
                $table->date('collection_date');
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('transaction_count')->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('file_disk', 40)->nullable();
                $table->string('file_path')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'batch_reference'], 'sepa_batch_reference_uq');
                $table->index(['tenant_id', 'status', 'collection_date'], 'sepa_batch_status_date_idx');
            });
        }

        if (! Schema::hasTable('sepa_batch_items')) {
            Schema::create('sepa_batch_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sepa_batch_id')->constrained('sepa_batches')->cascadeOnDelete();
                $table->foreignId('finance_invoice_id')->constrained('finance_invoices')->cascadeOnDelete();
                $table->foreignId('sepa_mandate_id')->constrained('sepa_mandates')->restrictOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('sequence_type', 4)->default('RCUR');
                $table->string('end_to_end_id', 35);
                $table->string('status', 20)->default('pending');
                $table->timestamps();
                $table->unique(['sepa_batch_id', 'finance_invoice_id'], 'sepa_batch_invoice_uq');
                $table->index(['tenant_id', 'sepa_batch_id'], 'sepa_items_batch_idx');
            });
        }

        if (! Schema::hasTable('bank_import_batches')) {
            Schema::create('bank_import_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('original_name', 255);
                $table->string('file_hash', 64);
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['tenant_id', 'file_hash'], 'bank_import_hash_uq');
            });
        }

        if (! Schema::hasTable('bank_transactions')) {
            Schema::create('bank_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bank_import_batch_id')->constrained('bank_import_batches')->cascadeOnDelete();
                $table->date('booking_date');
                $table->date('value_date')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('EUR');
                $table->string('payer_name', 180)->nullable();
                $table->text('payer_iban')->nullable();
                $table->text('reference')->nullable();
                $table->string('external_id', 120);
                $table->string('status', 20)->default('unmatched');
                $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('finance_payment_id')->nullable()->constrained('finance_payments')->nullOnDelete();
                $table->unsignedTinyInteger('match_confidence')->nullable();
                $table->string('match_reason', 255)->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'external_id'], 'bank_transaction_external_uq');
                $table->index(['tenant_id', 'status', 'booking_date'], 'bank_transaction_status_idx');
            });
        }

        $permissions = [
            ['key' => 'finance.documents', 'module' => 'finance', 'action' => 'documents', 'name' => 'Finanzdokumente erzeugen', 'description' => 'Rechnungen, Gutschriften und Mahnungen als PDF erzeugen und laden.'],
            ['key' => 'finance.bank', 'module' => 'finance', 'action' => 'bank', 'name' => 'Bankabgleich verwalten', 'description' => 'Bankumsätze importieren und Zahlungen zuordnen.'],
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
        DB::table('permissions')->whereIn('key', ['finance.documents', 'finance.bank'])->delete();
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_import_batches');
        Schema::dropIfExists('sepa_batch_items');
        Schema::dropIfExists('sepa_batches');
        Schema::dropIfExists('finance_credit_notes');

        if (Schema::hasTable('finance_dunnings') && Schema::hasColumn('finance_dunnings', 'pdf_disk')) {
            Schema::table('finance_dunnings', function (Blueprint $table): void {
                $table->dropColumn(['pdf_disk', 'pdf_path', 'pdf_size', 'pdf_generated_at']);
            });
        }
        if (Schema::hasTable('finance_invoices') && Schema::hasColumn('finance_invoices', 'household_id')) {
            Schema::table('finance_invoices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('household_id');
                $table->dropColumn(['recipient_snapshot', 'pdf_disk', 'pdf_path', 'pdf_size', 'pdf_generated_at']);
            });
        }
        if (Schema::hasTable('sepa_mandates') && Schema::hasColumn('sepa_mandates', 'collection_count')) {
            Schema::table('sepa_mandates', function (Blueprint $table): void {
                $table->dropColumn(['collection_count', 'last_collected_at']);
            });
        }
        if (Schema::hasTable('contribution_rates') && Schema::hasColumn('contribution_rates', 'scope')) {
            Schema::table('contribution_rates', function (Blueprint $table): void {
                $table->dropIndex('contrib_rates_scope_idx');
                $table->dropColumn('scope');
            });
        }
        Schema::dropIfExists('finance_settings');
    }
};
