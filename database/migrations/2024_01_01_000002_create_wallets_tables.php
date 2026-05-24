<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('ulid', 26)->unique();
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('ledger_balance', 18, 2)->default(0);
            $table->decimal('frozen_balance', 18, 2)->default(0);
            $table->string('currency', 10)->default('NGN');
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamp('last_transaction_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('status');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->string('ulid', 26)->unique();
            $table->enum('type', ['credit','debit','funding','refund','admin_credit','admin_debit','transfer','cashback','commission'])->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->decimal('ledger_balance_before', 18, 2);
            $table->decimal('ledger_balance_after', 18, 2);
            $table->string('reference', 100)->unique();
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->enum('status', ['pending','successful','failed','reversed'])->default('successful');
            $table->nullableMorphs('transactable');
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            $table->index(['wallet_id', 'type']);
            $table->index(['wallet_id', 'created_at']);
            $table->index('reference');
        });

        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->decimal('amount', 18, 2);
            $table->string('reason', 255);
            $table->string('reference', 100);
            $table->enum('status', ['active', 'released', 'deducted'])->default('active');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
        });

        Schema::create('wallet_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 18, 2);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->enum('status', ['pending','completed','failed'])->default('pending');
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_refunds');
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
