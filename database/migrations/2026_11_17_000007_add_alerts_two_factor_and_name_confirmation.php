<?php

use App\Support\IntegrityTriggers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening additions (additive only):
 *  - integrity check history and non-dismissible system alerts with exposure
 *    receipts and honest delivery records;
 *  - TOTP two-factor fields (secrets encrypted with APP_KEY);
 *  - import rows whose only conflict is a same-name match an officer may confirm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrity_check_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('trigger', ['schedule', 'manual', 'restore']);
            $table->enum('status', ['passed', 'failed', 'error']);
            $table->unsignedInteger('triggers_installed')->default(0);
            $table->json('missing_triggers')->nullable();
            $table->json('exception_counts')->nullable();
            $table->string('error', 255)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at', 6);
            $table->timestamp('finished_at', 6)->nullable();
            $table->index(['status', 'started_at']);
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->enum('severity', ['critical', 'warning']);
            $table->string('fingerprint', 64);
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->string('title', 190);
            // Plain-language summary with counts only: never amounts, names or references.
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('condition_cleared_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgement_note', 500)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });
        // At most one unresolved alert per condition: repeated detections increment it.
        DB::statement("ALTER TABLE system_alerts ADD COLUMN open_fingerprint VARCHAR(64) AS (IF(status <> 'resolved', fingerprint, NULL)) PERSISTENT, ADD UNIQUE KEY system_alerts_open_fingerprint_unique (open_fingerprint)");

        Schema::create('alert_receipts', function (Blueprint $table) {
            $table->foreignId('system_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->primary(['system_alert_id', 'user_id']);
        });

        Schema::create('alert_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('channel', ['email', 'in_app']);
            $table->enum('status', ['accepted_by_mail_server', 'failed', 'not_configured', 'displayed']);
            $table->string('error', 255)->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->index(['system_alert_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });

        DB::statement("ALTER TABLE import_rows MODIFY status ENUM('valid', 'invalid', 'duplicate', 'name_match') NOT NULL");
        Schema::table('import_batches', function (Blueprint $table) {
            $table->unsignedInteger('name_match_count')->default(0)->after('duplicate_count');
        });

        // Install the triggers for the new tables (and reinstall the rest).
        foreach (IntegrityTriggers::definitions() as $name => $sql) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        foreach (['boba_integrity_check_runs_block_update', 'boba_integrity_check_runs_block_delete', 'boba_system_alerts_block_delete', 'boba_alert_notifications_block_update', 'boba_alert_notifications_block_delete'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
        Schema::table('import_batches', fn (Blueprint $t) => $t->dropColumn('name_match_count'));
        DB::statement("ALTER TABLE import_rows MODIFY status ENUM('valid', 'invalid', 'duplicate') NOT NULL");
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']));
        foreach (['alert_notifications', 'alert_receipts', 'system_alerts', 'integrity_check_runs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
