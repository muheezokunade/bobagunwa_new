<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial ledger. All money is integer kobo (1 NGN = 100 kobo).
 *
 * Source-of-truth rules (see docs/06-financial-rules.md):
 *  - posted assessments create member obligations;
 *  - verified payments create member funds;
 *  - allocations apply funds to obligations;
 *  - adjustments and reversals are append-only linked records;
 *  - balances are always derived, never stored.
 *
 * Composite (id, member_id) keys make cross-member allocation structurally
 * impossible; triggers in the integrity migration enforce the remaining rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->enum('kind', ['dues', 'levy', 'other']);
            $table->string('description', 255)->nullable();
            $table->bigInteger('default_amount_kobo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE fee_categories ADD CONSTRAINT fee_categories_default_amount_positive CHECK (default_amount_kobo IS NULL OR default_amount_kobo > 0)');

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->bigInteger('amount_kobo');
            $table->date('due_on')->nullable();
            $table->date('effective_on');
            $table->string('period_label', 60)->nullable();
            $table->enum('origin', ['native', 'historical', 'opening_balance'])->default('native');
            $table->enum('status', ['draft', 'posted', 'discarded'])->default('draft');
            $table->enum('target_mode', ['all_active', 'selected']);
            $table->json('target_member_ids')->nullable();
            $table->unsignedInteger('target_count')->default(0);
            $table->bigInteger('total_kobo')->default(0);
            $table->foreignId('import_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'effective_on']);
        });
        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_amount_positive CHECK (amount_kobo > 0), ADD CONSTRAINT assessments_total_non_negative CHECK (total_kobo >= 0)');

        Schema::create('member_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_kobo');
            $table->date('effective_on');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['assessment_id', 'member_id']);
            $table->unique(['id', 'member_id']);
            $table->index(['member_id', 'effective_on']);
        });
        DB::statement('ALTER TABLE member_obligations ADD CONSTRAINT member_obligations_amount_positive CHECK (amount_kobo > 0)');

        Schema::create('obligation_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('member_obligation_id');
            $table->unsignedBigInteger('member_id');
            // Signed: negative reduces (waiver/correction/cancellation), positive increases.
            $table->bigInteger('amount_kobo');
            $table->enum('kind', ['waiver', 'correction', 'cancellation', 'increase']);
            $table->string('reason', 500);
            $table->date('effective_on');
            $table->string('idempotency_key', 80)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign(['member_obligation_id', 'member_id'], 'adjustments_obligation_member_fk')->references(['id', 'member_id'])->on('member_obligations')->restrictOnDelete();
            $table->index(['member_id', 'effective_on']);
        });
        DB::statement('ALTER TABLE obligation_adjustments ADD CONSTRAINT obligation_adjustments_non_zero CHECK (amount_kobo <> 0)');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_kobo');
            $table->char('currency', 3)->default('NGN');
            $table->enum('method', ['bank_transfer', 'cash', 'pos', 'other']);
            $table->date('received_on');
            $table->string('external_reference', 120)->nullable()->index();
            $table->string('payer_name', 190)->nullable();
            $table->string('narrative', 255)->nullable();
            $table->enum('origin', ['native', 'historical'])->default('native');
            $table->enum('status', ['pending', 'verified', 'rejected', 'reversed'])->default('pending');
            // Policy snapshot at recording time; enforced by trigger on verification.
            $table->boolean('requires_distinct_verifier')->default(true);
            $table->foreignId('evidence_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_note', 255)->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['id', 'member_id']);
            $table->index(['status', 'received_on']);
            $table->index(['member_id', 'received_on']);
            $table->index(['member_id', 'amount_kobo', 'received_on']);
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount_kobo > 0), ADD CONSTRAINT payments_currency_ngn CHECK (currency = 'NGN')");
        // A bank/receipt reference may appear once among payments that count or have counted;
        // a payment rejected as recorded in error releases its reference for correct re-entry.
        DB::statement("ALTER TABLE payments ADD COLUMN live_external_reference VARCHAR(120) AS (IF(status <> 'rejected', external_reference, NULL)) PERSISTENT, ADD UNIQUE KEY payments_external_reference_unique (live_external_reference)");

        Schema::create('financial_reversals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->enum('kind', ['payment', 'allocation']);
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('payment_allocation_id')->nullable()->unique();
            $table->bigInteger('amount_kobo');
            $table->string('reason', 500);
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->index(['payment_id', 'kind']);
        });
        DB::statement("ALTER TABLE financial_reversals
            ADD CONSTRAINT financial_reversals_amount_positive CHECK (amount_kobo > 0),
            ADD CONSTRAINT financial_reversals_kind_shape CHECK ((kind = 'allocation' AND payment_allocation_id IS NOT NULL) OR (kind = 'payment' AND payment_allocation_id IS NULL)),
            ADD COLUMN payment_reversal_key BIGINT UNSIGNED AS (IF(kind = 'payment', payment_id, NULL)) PERSISTENT,
            ADD UNIQUE KEY financial_reversals_one_per_payment (payment_reversal_key)");

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('member_obligation_id');
            $table->unsignedBigInteger('member_id');
            $table->bigInteger('amount_kobo');
            $table->enum('status', ['active', 'reversed'])->default('active');
            $table->string('batch_key', 80)->nullable();
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('reversal_id')->nullable()->unique();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->nullable();
            $table->foreign(['payment_id', 'member_id'], 'allocations_payment_member_fk')->references(['id', 'member_id'])->on('payments')->restrictOnDelete();
            $table->foreign(['member_obligation_id', 'member_id'], 'allocations_obligation_member_fk')->references(['id', 'member_id'])->on('member_obligations')->restrictOnDelete();
            $table->foreign('reversal_id')->references('id')->on('financial_reversals')->restrictOnDelete();
            $table->index(['payment_id', 'status']);
            $table->index(['member_obligation_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index('batch_key');
        });
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_positive CHECK (amount_kobo > 0)');

        Schema::table('financial_reversals', function (Blueprint $table) {
            $table->foreign('payment_allocation_id')->references('id')->on('payment_allocations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_reversals', fn (Blueprint $t) => $t->dropForeign(['payment_allocation_id']));
        foreach (['payment_allocations', 'financial_reversals', 'payments', 'obligation_adjustments', 'member_obligations', 'assessments', 'fee_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
