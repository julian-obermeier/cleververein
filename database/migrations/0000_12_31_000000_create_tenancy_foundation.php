<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('trial')->index();
            $table->string('plan')->default('basis');
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('salutation')->nullable();
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->json('contact_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
        Schema::dropIfExists('tenants');
    }
};
