<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members report their own payments with a receipt (22 Nov 2026 demo). The
 * payment is pending until a payment verifier confirms it against the bank
 * alert; the member may say which levy it is for, to guide allocation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('submitted_by_member')->default(false)->after('origin');
            $table->foreignId('intended_obligation_id')->nullable()->after('submitted_by_member')->constrained('member_obligations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intended_obligation_id');
            $table->dropColumn('submitted_by_member');
        });
    }
};
