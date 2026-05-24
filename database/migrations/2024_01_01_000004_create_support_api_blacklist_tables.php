<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── API Keys ─────────────────────────────────────────────────────────────
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 100);
            $table->string('public_key', 100)->unique();
            $table->string('secret_key', 100);
            $table->json('ip_whitelist')->nullable();
            $table->json('allowed_services')->nullable();
            $table->enum('status', ['active','revoked','suspended'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('daily_limit')->default(100);
            $table->unsignedInteger('monthly_limit')->default(3000);
            $table->unsignedInteger('daily_usage')->default(0);
            $table->unsignedInteger('monthly_usage')->default(0);
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('api_key_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_key_id');
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('status', 20)->default('processed');
            $table->timestamps();

            $table->foreign('api_key_id')->references('id')->on('api_keys')->cascadeOnDelete();
            $table->index(['api_key_id', 'created_at']);
        });

        // ─── Data Plans ───────────────────────────────────────────────────────────
        Schema::create('data_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->enum('network', ['mtn','airtel','glo','9mobile'])->index();
            $table->string('plan_type', 30)->nullable(); // sme, sme2, cg, cg2
            $table->string('plan_id', 60)->nullable();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->decimal('size', 10, 3)->nullable();
            $table->string('size_unit', 10)->default('GB');
            $table->unsignedSmallInteger('validity')->nullable();
            $table->string('validity_unit', 20)->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->string('provider_plan_id', 100)->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
            $table->index(['network', 'status']);
        });

        // ─── Webhooks ─────────────────────────────────────────────────────────────
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->json('payload');
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['received','processed','failed'])->default('received');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'status']);
            $table->index('created_at');
        });

        // ─── Support Tickets ─────────────────────────────────────────────────────
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ticket_number', 30)->unique();
            $table->string('subject', 255);
            $table->string('category', 50)->nullable();
            $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
            $table->enum('status', ['open','pending_support','pending_user','resolved','closed'])->default('open');
            $table->string('transaction_reference', 100)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->enum('sender', ['user','support','system'])->default('user');
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
        });

        // ─── Referrals ────────────────────────────────────────────────────────────
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referee_id')->unique();
            $table->enum('status', ['pending','qualified','rewarded'])->default('pending');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users');
            $table->foreign('referee_id')->references('id')->on('users');
            $table->index('referrer_id');
        });

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referee_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->enum('type', ['signup','transaction'])->default('signup');
            $table->enum('status', ['pending','paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users');
        });

        // ─── Blacklists ───────────────────────────────────────────────────────────
        Schema::create('blacklisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('blacklisted_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blacklisted_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('blacklisted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('blacklisted_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('blacklisted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('blacklisted_bvn', function (Blueprint $table) {
            $table->id();
            $table->string('bvn', 20)->unique();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('blacklisted_by')->nullable();
            $table->timestamps();
        });

        // ─── User Devices ─────────────────────────────────────────────────────────
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('device_name', 100)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'ip_address']);
        });

        // ─── Reports ─────────────────────────────────────────────────────────────
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->unsignedInteger('successful_transactions')->default(0);
            $table->unsignedInteger('failed_transactions')->default(0);
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_funding', 18, 2)->default(0);
            $table->json('service_breakdown')->nullable();
            $table->json('provider_breakdown')->nullable();
            $table->timestamps();
        });

        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // 2024-01
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_funding', 18, 2)->default(0);
            $table->json('service_breakdown')->nullable();
            $table->timestamps();
        });

        // ─── Settings ─────────────────────────────────────────────────────────────
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('blacklisted_bvn');
        Schema::dropIfExists('blacklisted_numbers');
        Schema::dropIfExists('blacklisted_emails');
        Schema::dropIfExists('blacklisted_ips');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('data_plans');
        Schema::dropIfExists('api_key_logs');
        Schema::dropIfExists('api_keys');
    }
};
