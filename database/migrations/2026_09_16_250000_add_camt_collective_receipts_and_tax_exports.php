<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_import_batches')) {
            foreach ([
                'file_type' => fn (Blueprint $table) => $table->string('file_type', 24)->default('csv'),
                'message_id' => fn (Blueprint $table) => $table->string('message_id', 180)->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('bank_import_batches', $column)) {
                    Schema::table('bank_import_batches', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }

        if (Schema::hasTable('bank_transactions')) {
            foreach ([
                'end_to_end_id' => fn (Blueprint $table) => $table->string('end_to_end_id', 80)->nullable(),
                'mandate_reference' => fn (Blueprint $table) => $table->string('mandate_reference', 80)->nullable(),
                'bank_transaction_code' => fn (Blueprint $table) => $table->string('bank_transaction_code', 120)->nullable(),
                'return_reason_code' => fn (Blueprint $table) => $table->string('return_reason_code', 24)->nullable(),
                'return_reason_text' => fn (Blueprint $table) => $table->string('return_reason_text', 500)->nullable(),
                'raw_details' => fn (Blueprint $table) => $table->json('raw_details')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('bank_transactions', $column)) {
                    Schema::table('bank_transactions', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }

            if (! $this->indexExists('bank_transactions', 'bank_tx_e2e_idx')) {
                Schema::table('bank_transactions', function (Blueprint $table): void {
                    $table->index(['tenant_id', 'end_to_end_id'], 'bank_tx_e2e_idx');
                });
            }
        }

        if (Schema::hasTable('finance_accounts') && ! Schema::hasColumn('finance_accounts', 'datev_account')) {
            Schema::table('finance_accounts', function (Blueprint $table): void {
                $table->string('datev_account', 20)->nullable();
            });
        }

        if (Schema::hasTable('finance_categories') && ! Schema::hasColumn('finance_categories', 'datev_account')) {
            Schema::table('finance_categories', function (Blueprint $table): void {
                $table->string('datev_account', 20)->nullable();
            });
        }

        if (Schema::hasTable('finance_settings')) {
            foreach ([
                'datev_consultant_number' => fn (Blueprint $table) => $table->string('datev_consultant_number', 20)->nullable(),
                'datev_client_number' => fn (Blueprint $table) => $table->string('datev_client_number', 20)->nullable(),
                'datev_chart' => fn (Blueprint $table) => $table->string('datev_chart', 12)->nullable(),
                'datev_account_length' => fn (Blueprint $table) => $table->unsignedTinyInteger('datev_account_length')->default(4),
            ] as $column => $definition) {
                if (! Schema::hasColumn('finance_settings', $column)) {
                    Schema::table('finance_settings', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }

        if (! Schema::hasTable('finance_donation_collective_certificates')) {
            Schema::create('finance_donation_collective_certificates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('certificate_number', 80);
                $table->date('issue_date');
                $table->date('period_from');
                $table->date('period_to');
                $table->string('status', 20)->default('issued');
                $table->decimal('total_amount', 12, 2);
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
                $table->unique(['tenant_id', 'certificate_number'], 'fin_collective_cert_number_uq');
                $table->index(['tenant_id', 'status', 'issue_date'], 'fin_collective_cert_status_idx');
            });
        }

        if (! Schema::hasTable('finance_donation_collective_items')) {
            Schema::create('finance_donation_collective_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('collective_certificate_id')->constrained('finance_donation_collective_certificates')->cascadeOnDelete();
                $table->foreignId('finance_donation_id')->constrained('finance_donations')->restrictOnDelete();
                $table->date('donation_date');
                $table->decimal('amount', 12, 2);
                $table->string('donation_kind', 32);
                $table->string('purpose', 500);
                $table->boolean('expense_waiver')->default(false);
                $table->timestamps();
                $table->unique(['collective_certificate_id', 'finance_donation_id'], 'fin_collective_item_donation_uq');
                $table->index(['tenant_id', 'finance_donation_id'], 'fin_collective_donation_idx');
            });
        }

        $permission = [
            'key' => 'finance.tax_export',
            'module' => 'finance',
            'action' => 'tax_export',
            'name' => 'Steuerberater-Export erstellen',
            'description' => 'Finanzjournal mit Kontenzuordnung für Steuerberatung und DATEV-nahe Weiterverarbeitung exportieren.',
        ];
        DB::table('permissions')->updateOrInsert(
            ['key' => $permission['key']],
            [...$permission, 'updated_at' => now(), 'created_at' => now()],
        );

        $permissionId = DB::table('permissions')->where('key', 'finance.tax_export')->value('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
            if ($roleId && $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'finance.tax_export')->delete();
        Schema::dropIfExists('finance_donation_collective_items');
        Schema::dropIfExists('finance_donation_collective_certificates');

        if (Schema::hasTable('finance_settings')) {
            $columns = ['datev_consultant_number', 'datev_client_number', 'datev_chart', 'datev_account_length'];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('finance_settings', $column)));
            if ($existing !== []) {
                Schema::table('finance_settings', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
        if (Schema::hasTable('finance_categories') && Schema::hasColumn('finance_categories', 'datev_account')) {
            Schema::table('finance_categories', fn (Blueprint $table) => $table->dropColumn('datev_account'));
        }
        if (Schema::hasTable('finance_accounts') && Schema::hasColumn('finance_accounts', 'datev_account')) {
            Schema::table('finance_accounts', fn (Blueprint $table) => $table->dropColumn('datev_account'));
        }
        if (Schema::hasTable('bank_transactions')) {
            if ($this->indexExists('bank_transactions', 'bank_tx_e2e_idx')) {
                Schema::table('bank_transactions', fn (Blueprint $table) => $table->dropIndex('bank_tx_e2e_idx'));
            }
            $columns = ['end_to_end_id', 'mandate_reference', 'bank_transaction_code', 'return_reason_code', 'return_reason_text', 'raw_details'];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('bank_transactions', $column)));
            if ($existing !== []) {
                Schema::table('bank_transactions', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
        if (Schema::hasTable('bank_import_batches')) {
            $columns = ['file_type', 'message_id'];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('bank_import_batches', $column)));
            if ($existing !== []) {
                Schema::table('bank_import_batches', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect(Schema::getIndexes($table))->contains(fn (array $item): bool => ($item['name'] ?? null) === $index);
    }
};
