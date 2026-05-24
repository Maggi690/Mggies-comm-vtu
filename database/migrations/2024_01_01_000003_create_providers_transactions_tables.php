<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->text('api_key');
            $table->text('secret_key');
            $table->string('endpoint', 255);
            $table->text('webhook_secret')->nullable();
            $table->enum('status', ['active','inactive','maintenance'])->default('active');
            $table->unsignedSmallInteger('priority')->default(1);
            $table->json('services')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_default')->default(false);
            $table->decimal('success_rate', 5, 2)->default(100);
            $table->decimal('failure_rate', 5, 2)->default(0);
            $table->decimal('avg_response_time', 10, 2)->default(0);
            $table->unsignedBigInteger('total_requests')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->string('service_type', 50);
            $table->string('network', 30)->nullable();
            $table->enum('fee_type', ['flat','percentage'])->default('flat');
            $table->decimal('fee_value', 10, 4)->default(0);
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['provider_id', 'service_type', 'network']);
        });

        Schema::create('provider_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('action', 100);
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->enum('status', ['success','failed','timeout'])->default('success');
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['provider_id', 'status']);
            $table->index(['provider_id', 'created_at']);
        });

        Schema::create('provider_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->decimal('balance', 18, 2)->default(0);
            $table->string('currency', 10)->default('NGN');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ulid', 26)->unique();
            $table->string('reference', 100)->unique();
            $table->enum('type', ['debit','credit'])->default('debit');
            $table->enum('service_type', ['airtime','data','cable','electricity','exam','wallet_transfer','funding'])->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('cashback', 10, 2)->default(0);
            $table->enum('status', ['pending','processing','successful','failed','refunded','reversed'])->default('pending')->index();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->json('provider_response')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('beneficiary', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('retries')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'service_type']);
            $table->index(['user_id', 'created_at']);
            $table->index('reference');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('provider_balances');
        Schema::dropIfExists('provider_logs');
        Schema::dropIfExists('provider_services');
        Schema::dropIfExists('providers');
    }
};
