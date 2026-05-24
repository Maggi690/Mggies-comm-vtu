<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 191)->unique();
            $table->string('phone', 20)->unique();
            $table->string('username', 60)->unique()->nullable();
            $table->string('password');
            $table->string('transaction_pin')->nullable();
            $table->enum('user_type', ['user','agent','vendor','sub_reseller','admin','assistant_admin','customer_support','api_user'])->default('user');
            $table->string('referral_code', 20)->unique()->nullable();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->enum('status', ['active','suspended','banned'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->enum('kyc_status', ['none','pending','verified','rejected'])->default('none');
            $table->string('bvn', 20)->nullable();
            $table->string('nin', 20)->nullable();
            $table->string('avatar')->nullable();
            $table->string('device_token')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->boolean('api_access_enabled')->default(false);
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('referred_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'user_type']);
            $table->index('referral_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
