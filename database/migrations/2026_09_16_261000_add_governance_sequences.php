<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('governance_sequences')) {
            return;
        }

        Schema::create('governance_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('sequence_key', 60);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->foreign('tenant_id', 'gov_seq_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'sequence_key', 'year'], 'gov_seq_tenant_key_year_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_sequences');
    }
};
