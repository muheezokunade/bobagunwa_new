<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity, sessions, RBAC, audit and operational support tables.
 *
 * Users never get hard-deleted: they are suspended or disabled so that every
 * audit/finance actor reference stays resolvable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('email', 190)->unique();
            // Null until the account is activated through a single-use token.
            $table->string('password')->nullable();
            $table->unsignedBigInteger('member_id')->nullable()->unique();
            $table->enum('status', ['pending_activation', 'active', 'suspended', 'disabled'])->default('pending_activation');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 255);
            $table->boolean('is_staff')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('group', 40);
            $table->string('description', 255);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('granted_at')->useCurrent();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary', 255);
            $table->json('context')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('request_id', 36)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event', 60);
            $table->char('ip_hash', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 60);
            $table->string('key', 80);
            $table->char('request_hash', 64);
            $table->string('result_type', 60)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['scope', 'key']);
        });

        Schema::create('private_files', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 40);
            $table->string('path', 255)->unique();
            $table->string('original_name', 190);
            $table->string('mime', 100);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['backup', 'restore_verification']);
            $table->enum('status', ['succeeded', 'failed']);
            $table->string('file_name', 190)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('details')->nullable();
            $table->string('error', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['backup_runs', 'private_files', 'idempotency_keys', 'settings', 'security_events', 'audit_events', 'role_user', 'permission_role', 'permissions', 'roles', 'cache_locks', 'cache', 'sessions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
