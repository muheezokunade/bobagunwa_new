<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membership applications, the member register, permanent membership numbers,
 * account tokens, message delivery records and bulk imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_number_sequences', function (Blueprint $table) {
            $table->string('name', 40)->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamp('updated_at')->nullable();
        });

        // Every issued number lives here forever. Rows can never be updated or
        // deleted (see integrity triggers), so a number can never be reused.
        Schema::create('membership_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->enum('source', ['sequence', 'provided']);
            $table->unsignedBigInteger('sequence_value')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->useCurrent();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->enum('kind', ['members', 'finance_transactions', 'opening_balances']);
            $table->enum('status', ['previewed', 'committed', 'cancelled'])->default('previewed');
            $table->string('original_filename', 190);
            $table->string('file_sha256', 64);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->json('summary')->nullable();
            $table->date('cutoff_date')->nullable();
            $table->string('source_approval_reference', 190)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('committed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });
        // The same file can only ever be committed once per import kind.
        DB::statement("ALTER TABLE import_batches ADD COLUMN committed_file_sha256 VARCHAR(64) AS (IF(status = 'committed', file_sha256, NULL)) PERSISTENT, ADD UNIQUE KEY import_batches_committed_file_unique (kind, committed_file_sha256)");

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->json('normalized')->nullable();
            $table->enum('status', ['valid', 'invalid', 'duplicate']);
            $table->json('messages')->nullable();
            $table->string('result_type', 40)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->unique(['import_batch_id', 'row_number']);
        });

        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->char('status_token_hash', 64)->unique();
            $table->timestamp('status_token_expires_at');
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('other_names', 120)->nullable();
            $table->string('email', 190);
            $table->string('email_normalized', 190);
            $table->string('phone', 40);
            $table->string('phone_normalized', 20);
            $table->date('date_of_birth');
            $table->string('ijebu_town', 120);
            $table->string('city', 120);
            $table->string('occupation', 120);
            $table->text('reason');
            $table->enum('eligibility_status', ['eligible', 'flagged']);
            $table->json('eligibility_flags');
            $table->string('screening_version', 40);
            $table->string('privacy_notice_version', 40);
            $table->timestamp('declaration_accepted_at');
            $table->timestamp('privacy_accepted_at');
            $table->enum('status', ['submitted', 'under_review', 'changes_requested', 'approved', 'rejected'])->default('submitted');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('member_id')->nullable()->unique();
            $table->char('submitted_ip_hash', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('eligibility_status');
            $table->index('email_normalized');
            $table->index('phone_normalized');
        });
        // Only one in-progress application per email/phone. Closed applications
        // compute NULL and therefore never collide (MariaDB allows many NULLs).
        DB::statement("ALTER TABLE membership_applications
            ADD COLUMN active_email_key VARCHAR(190) AS (IF(status IN ('submitted','under_review','changes_requested'), email_normalized, NULL)) PERSISTENT,
            ADD COLUMN active_phone_key VARCHAR(20) AS (IF(status IN ('submitted','under_review','changes_requested'), phone_normalized, NULL)) PERSISTENT,
            ADD UNIQUE KEY applications_active_email_unique (active_email_key),
            ADD UNIQUE KEY applications_active_phone_unique (active_phone_key)");

        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['submitted', 'status_changed', 'note', 'applicant_response']);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('private_note')->nullable();
            $table->text('applicant_message')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['membership_application_id', 'created_at']);
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number', 32)->unique();
            $table->string('full_name', 190);
            $table->string('name_normalized', 190);
            $table->string('preferred_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('email_normalized', 190)->nullable()->unique();
            $table->string('phone', 40)->nullable();
            $table->string('phone_normalized', 20)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('ijebu_town', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->string('status_reason', 255)->nullable();
            $table->enum('origin', ['application', 'import', 'manual']);
            $table->foreignId('membership_application_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('joined_on')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign('membership_number')->references('number')->on('membership_numbers')->restrictOnDelete();
            $table->index(['status', 'full_name']);
            $table->index('name_normalized');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
        });
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
        });

        Schema::create('account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('purpose', ['activation', 'password_reset']);
            // Only the SHA-256 hash is stored; the raw token is shown once.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->enum('issued_via', ['approval', 'admin', 'self_service', 'cli']);
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'purpose']);
        });

        // Prepared notifications. Delivery attestation is recorded separately
        // from token issuance; bodies never contain raw tokens.
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 40);
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('recipient_name', 190);
            $table->string('recipient_email', 190)->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->string('subject', 190);
            $table->text('body');
            $table->enum('channel', ['manual', 'smtp'])->default('manual');
            $table->enum('status', ['pending', 'accepted_by_mail_server', 'failed', 'delivered_manually', 'cancelled'])->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->foreignId('attested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('attested_at')->nullable();
            $table->string('attestation_note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('recovery_requests', function (Blueprint $table) {
            $table->id();
            $table->char('identifier_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'issued', 'dismissed'])->default('pending');
            $table->char('requested_ip_hash', 64)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['member_id']));
        foreach (['recovery_requests', 'outbound_messages', 'account_tokens'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('membership_applications', fn (Blueprint $t) => $t->dropForeign(['member_id']));
        foreach (['members', 'application_events', 'membership_applications', 'import_rows', 'import_batches', 'membership_numbers', 'membership_number_sequences'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
