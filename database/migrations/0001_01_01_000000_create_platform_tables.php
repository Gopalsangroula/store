<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform core: people who sign in, client stores, their domains and settings,
 * and the framework's own tables (sessions, cache, jobs, API tokens).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone', 30)->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->boolean('is_super_admin')->default(false);
            $t->string('status', 20)->default('active'); // active | disabled
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->integer('total_jobs');
            $t->integer('pending_jobs');
            $t->integer('failed_jobs');
            $t->longText('failed_job_ids');
            $t->mediumText('options')->nullable();
            $t->integer('cancelled_at')->nullable();
            $t->integer('created_at');
            $t->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('description')->nullable();
            $t->decimal('price_monthly', 19, 4)->default(0);
            $t->decimal('price_yearly', 19, 4)->default(0);
            $t->decimal('setup_fee', 19, 4)->default(0);
            $t->unsignedInteger('max_products')->nullable();   // null = unlimited
            $t->unsignedInteger('max_staff')->nullable();
            $t->unsignedInteger('max_orders_per_month')->nullable();
            $t->json('features')->nullable();                   // list of enabled module keys
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $t->string('status', 20)->default('active'); // active | suspended | closed
            $t->string('suspended_reason')->nullable();
            $t->char('base_currency', 3)->default('NPR');
            $t->string('timezone', 64)->default('Asia/Kathmandu');
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('whatsapp', 30)->nullable();
            $t->string('address')->nullable();
            $t->string('pan_vat_number', 30)->nullable();
            $t->string('logo_path')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index('status');
        });

        Schema::create('store_domains', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('domain')->unique();
            $t->boolean('is_primary')->default(false);
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('store_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('key', 100);
            $t->text('value')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'key']);
        });

        Schema::create('store_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('plans');
            $t->string('billing_cycle', 10)->default('monthly'); // monthly | yearly
            $t->decimal('price', 19, 4);
            $t->string('status', 20)->default('active');        // active | expired | cancelled
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['store_id', 'status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number')->unique();
            $t->string('description');
            $t->decimal('amount', 19, 4);
            $t->decimal('paid_amount', 19, 4)->default(0);
            $t->string('status', 20)->default('unpaid'); // unpaid | paid | void
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->date('due_on');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'due_on']);
        });

        Schema::create('subscription_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 19, 4);
            $t->string('method', 30);
            $t->string('reference')->nullable();
            $t->dateTime('paid_at');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $t) {
            $t->string('key', 100)->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('platform_sequences', function (Blueprint $t) {
            $t->string('key', 50)->primary();
            $t->unsignedBigInteger('next_number')->default(1);
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 100);
            $t->nullableMorphs('subject');
            $t->json('properties')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['store_id', 'created_at']);
        });

        Schema::create('login_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        // Store-scoped roles: a role with store_id null is a template copied into each new store.
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug', 50);
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique(['store_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 100)->unique();
            $t->string('name');
            $t->string('group', 50);
        });

        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });

        Schema::create('store_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained('roles');
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->unique(['store_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['store_staff', 'role_permissions', 'permissions', 'roles', 'login_histories', 'activity_logs',
            'platform_sequences', 'platform_settings', 'subscription_payments', 'subscription_invoices',
            'store_subscriptions', 'store_settings', 'store_domains', 'stores', 'plans', 'personal_access_tokens',
            'failed_jobs', 'job_batches', 'jobs', 'cache_locks', 'cache', 'sessions', 'password_reset_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
